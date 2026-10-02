<?php
// sm.ms图片管理页面

/**
 * 渲染管理页面
 *
 * 健壮性修复：前端产物（webpack 构建出的 html/index.html）缺失时，
 * 原实现 file_get_contents() 会返回 false，导致 iframe 内出现空白页且无任何提示。
 *
 * 安全加固：原先 sm.ms 令牌与代理地址由前端拼进 iframe URL，会泄漏到浏览器历史、
 * Referer 与 Web 服务器访问日志。现改为由服务端注入 window.__EDITORMD_BOOTSTRAP__，
 * URL 上只保留用于 CSRF 校验的 nonce。
 *
 * @param string $text_domain
 * @param string $config
 *
 * @return string
 */
function display_page($text_domain, $config) {
    $template = __DIR__ . "/html/index.html";

    if (! file_exists($template)) {
        return '<div style="padding:24px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;">'
            . '<h2>' . esc_html__("sm.ms Image Management", "editormd") . '</h2>'
            . '<p>' . esc_html__(
                "The management page assets are missing. Please rebuild the plugin assets (npm run build inside src/Pages/page/sm-ms-management), or use the \"ImagePaste Upload Source\" option in the editor settings to upload to sm.ms directly.",
                "editormd"
            ) . '</p>'
            . '</div>';
    }

    $html = file_get_contents($template);
    if (false === $html) {
        return "";
    }

    // 兼容性修复：前端产物由 webpack 构建，构建期无法得知插件实际的 URL 路径，
    // 因此 vue.config.js 用 __EDITORMD_ASSET_BASE__ 作为 publicPath 占位符，
    // 在这里替换为插件真实的 URL 路径。这样插件目录被重命名或使用自定义插件目录时，
    // 页面 JS/CSS 也不会 404（原实现把 "../wp-content/plugins/wp-editormd/..." 写死在构建产物里）。
    $plugin_path = wp_parse_url(WP_EDITORMD_URL, PHP_URL_PATH);
    if (is_string($plugin_path) && "" !== $plugin_path) {
        $html = str_replace("__EDITORMD_ASSET_BASE__", ltrim($plugin_path, "/"), $html);
    }

    $bootstrap = array(
        "token"       => (string) $config::get_option("imagepaste_sm_token", "editor_basics"),
        "endpointUrl" => admin_url(
            "admin-ajax.php?action=wp_editormd_pages&page=sm-ms-management&entry=sm_ms_proxy&_wpnonce="
            . wp_create_nonce("wp_editormd_pages")
        ),
    );

    $script = '<script>window.__EDITORMD_BOOTSTRAP__ = '
        . wp_json_encode($bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . ';</script>';

    if (false !== stripos($html, "</head>")) {
        $html = preg_replace("#</head>#i", $script . "</head>", $html, 1);
    } else {
        $html = $script . $html;
    }

    return $html;
}

/**
 * wp_editormd_entry_sm_ms_proxy()
 * 由于sm.ms只允许后端请求，不允许前端请求，因此需要此代理
 * 允许sm-ms-management前端在授权情况下post到此接口，并携带如下参数：
 * - url: 代理地址
 * - method: 代理方法(post, get)
 * - header: 请求头(数组，不是字典)
 * - body: 请求体(字典)
 *
 * 安全加固（10.3.0）：
 *   原实现把请求体中的 url 与 header 原样交给 curl，并且显式关闭了
 *   CURLOPT_SSL_VERIFYPEER 与 CURLOPT_SSL_VERIFYHOST，等价于一个可访问任意
 *   内网地址与云元数据（169.254.169.254）的开放代理，同时可透传任意请求头。
 *   现在收紧为：
 *     1. 上游地址固定 host 白名单（仅 https://smms.app/api/v2/...）；
 *     2. 仅透传 Authorization 头，其它请求头一律丢弃；
 *     3. 开启 TLS 证书校验（改用 wp_remote_request）；
 *     4. 增加超时与请求体大小上限；
 *     5. 修复 $user_agent 未定义变量的问题。
 *   已核对前端（src/Pages/page/sm-ms-management）只会访问
 *   smms.app/api/v2/{profile,upload_history,delete/*} 且只发送 Authorization 头，
 *   因此上述收紧不影响原有功能。
 */
function wp_editormd_entry_sm_ms_proxy() {
    // 权限兜底校验（入口 renderer() 已校验一次）
    if (! current_user_can("manage_options")) {
        wp_die("Forbidden", "Forbidden", array("response" => 403));
    }

    // 安全修复：拒绝跨站发起的请求（同源请求会携带本机 Referer/Origin）
    $home          = wp_parse_url(home_url());
    $requestOrigin = isset($_SERVER["HTTP_ORIGIN"])
        ? wp_parse_url(esc_url_raw(wp_unslash($_SERVER["HTTP_ORIGIN"])))
        : array();

    if (! empty($home["host"]) && ! empty($requestOrigin["host"])
        && strtolower($requestOrigin["host"]) !== strtolower($home["host"])) {
        wp_die("Forbidden", "Forbidden", array("response" => 403));
    }

    // 安全修复：限制请求体大小，避免内存放大
    $raw = file_get_contents("php://input", false, null, 0, 1024 * 1024);
    if (false === $raw || "" === $raw) {
        wp_die("Bad Request", "Bad Request", array("response" => 400));
    }

    $postData = json_decode($raw, true);
    if (! is_array($postData)) {
        wp_die("Bad Request", "Bad Request", array("response" => 400));
    }

    $url    = isset($postData["url"]) ? (string) $postData["url"] : "";
    $method = isset($postData["method"]) ? strtolower((string) $postData["method"]) : "get";
    $header = (isset($postData["header"]) && is_array($postData["header"])) ? $postData["header"] : array();
    $body   = (isset($postData["body"]) && is_array($postData["body"])) ? $postData["body"] : array();

    // 安全修复 1：上游地址白名单
    if (! wp_editormd_sm_ms_url_allowed($url)) {
        wp_die("Forbidden upstream", "Forbidden upstream", array("response" => 403));
    }

    // 安全修复 2：仅透传 Authorization 头
    $forwardHeaders = array();
    foreach ($header as $item) {
        if (! is_string($item)) {
            continue;
        }

        $position = strpos($item, ":");
        if (false === $position) {
            continue;
        }

        if (0 === stripos(trim(substr($item, 0, $position)), "authorization")) {
            $forwardHeaders[] = "Authorization: " . trim(substr($item, $position + 1));
        }
    }

    $args = array(
        "method"      => ("post" === $method) ? "POST" : "GET",
        "timeout"     => 30,
        "redirection" => 0,
        "sslverify"   => true,
        "headers"     => $forwardHeaders,
        "user-agent"  => "WP-Editor.md/" . WP_EDITORMD_VER,
    );

    if ("post" === $method && ! empty($body)) {
        $args["body"] = $body;
    }

    // 安全修复 3：改用 WordPress 官方 HTTP API（自带证书校验与超时控制），
    // 替代原先关闭了证书校验的裸 curl
    $response = wp_remote_request($url, $args);

    header("Content-Type: application/json");

    if (is_wp_error($response)) {
        http_response_code(502);
        return wp_json_encode(array("error" => $response->get_error_message()));
    }

    $reqCode = (int) wp_remote_retrieve_response_code($response);
    http_response_code($reqCode > 0 ? $reqCode : 502);

    return wp_remote_retrieve_body($response);
}

/**
 * sm.ms 上游地址白名单校验
 *
 * 只允许 https://smms.app/api/v2/ 下的接口，
 * 阻断 file://、gopher://、内网地址、云元数据地址与 URL 混淆写法。
 *
 * @param string $url
 *
 * @return bool
 */
function wp_editormd_sm_ms_url_allowed($url) {
    if (! is_string($url) || "" === $url) {
        return false;
    }

    // 阻断 userinfo 混淆（https://smms.app@evil.com/...）
    if (false !== strpos($url, "@")) {
        return false;
    }

    $parts = wp_parse_url($url);
    if (empty($parts["scheme"]) || empty($parts["host"])) {
        return false;
    }

    if (strtolower($parts["scheme"]) !== "https") {
        return false;
    }

    if (strtolower($parts["host"]) !== "smms.app") {
        return false;
    }

    if (empty($parts["path"]) || 0 !== strpos($parts["path"], "/api/v2/")) {
        return false;
    }

    // 阻断路径穿越写法
    if (false !== strpos($parts["path"], "..")) {
        return false;
    }

    return true;
}
