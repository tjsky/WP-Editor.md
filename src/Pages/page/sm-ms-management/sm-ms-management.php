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
 * 10.4.2：
 *   1. 令牌不再下发给浏览器（改由服务端代理注入），引导数据里只保留接口地址；
 *   2. 函数改用插件前缀命名，避免与其它代码的全局 display_page() 冲突。
 *
 * @param string $text_domain
 * @param string $config
 *
 * @return string
 */
function wp_editormd_render_sm_ms_management_page($text_domain, $config) {
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
        // 安全加固（10.4.2）：不再把 sm.ms 令牌下发给浏览器。
        // 令牌只在服务端使用，由下面的代理按操作类型自行注入 Authorization 头。
        // 浏览器一旦拿到令牌，其暴露面就不再受 WordPress 会话生命周期约束
        // （第三方后台脚本、浏览器扩展、其它后台 XSS 都可能读到它）。
        "serverSideToken" => true,
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
 * - url: 目标接口（必须是允许的操作之一）
 * - method: 请求方法(post, get)
 * - header: 兼容字段，**已被服务端忽略**
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
 *
 * 安全加固（10.4.2）：
 *   6. 令牌不再来自浏览器：Authorization 由服务端按配置注入，客户端传什么都被忽略；
 *   7. 路径白名单收紧为**固定操作集合**（profile / upload_history / delete/{hash}），
 *      方法也由服务端按操作决定，不再是「任意 path + 任意 method」的通用代理。
 */
function wp_editormd_entry_sm_ms_proxy() {
    // 权限兜底校验（入口 renderer() 已校验一次）
    if (! current_user_can("manage_options")) {
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
    $body   = (isset($postData["body"]) && is_array($postData["body"])) ? $postData["body"] : array();

    // 安全修复 6：客户端自带的 Authorization 一律忽略（连同其它请求头）
    // 安全修复 7：只允许固定操作，方法与地址都由服务端决定
    $operation = wp_editormd_sm_ms_resolve_operation($url, $method);
    if (false === $operation) {
        wp_die("Forbidden upstream", "Forbidden upstream", array("response" => 403));
    }

    // 安全修复 6：令牌只在服务端使用
    $token = "";
    if (class_exists("\EditormdUtils\Config")) {
        $token = (string) \EditormdUtils\Config::get_option("imagepaste_sm_token", "editor_basics");
    }

    $forwardHeaders = array();
    if ("" !== $token) {
        $forwardHeaders[] = "Authorization: " . $token;
    }

    $args = array(
        "method"      => $operation["method"],
        "timeout"     => 30,
        "redirection" => 0,
        "sslverify"   => true,
        "headers"     => $forwardHeaders,
        "user-agent"  => "WP-Editor.md/" . WP_EDITORMD_VER,
    );

    if ("POST" === $operation["method"] && ! empty($body)) {
        $args["body"] = $body;
    }

    // 安全修复 3：改用 WordPress 官方 HTTP API（自带证书校验与超时控制），
    // 替代原先关闭了证书校验的裸 curl
    $response = wp_remote_request($operation["url"], $args);

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
 * 解析并校验 sm.ms 操作
 *
 * 只放行前端界面实际用到的三个操作，方法由服务端固定：
 *   POST /api/v2/profile
 *   GET  /api/v2/upload_history[?page=N]
 *   GET  /api/v2/delete/{hash}
 *
 * @param string $url    客户端请求的目标地址
 * @param string $method 客户端请求的方法（仅用于记录，实际方法由本函数决定）
 *
 * @return array|false array("url" => 规范化地址, "method" => GET|POST)
 */
function wp_editormd_sm_ms_resolve_operation($url, $method) {
    if (! is_string($url) || "" === $url) {
        return false;
    }

    // 阻断 userinfo 混淆（https://smms.app@evil.com/...）
    if (false !== strpos($url, "@")) {
        return false;
    }

    $parts = wp_parse_url($url);
    if (empty($parts["scheme"]) || empty($parts["host"]) || empty($parts["path"])) {
        return false;
    }

    if ("https" !== strtolower($parts["scheme"]) || "smms.app" !== strtolower($parts["host"])) {
        return false;
    }

    $path = $parts["path"];

    // 阻断路径穿越写法
    if (false !== strpos($path, "..")) {
        return false;
    }

    if ("/api/v2/profile" === $path) {
        return array("url" => "https://smms.app/api/v2/profile", "method" => "POST");
    }

    if ("/api/v2/upload_history" === $path) {
        $query = "";
        if (! empty($parts["query"])) {
            // 只保留数字型 page 参数，其余一律丢弃
            if (preg_match("/(?:^|&)page=(\d{1,6})(?:&|$)/", $parts["query"], $m)) {
                $query = "?page=" . $m[1];
            }
        }

        return array("url" => "https://smms.app/api/v2/upload_history" . $query, "method" => "GET");
    }

    if (preg_match("#^/api/v2/delete/([A-Za-z0-9]{1,64})$#", $path, $m)) {
        return array("url" => "https://smms.app/api/v2/delete/" . $m[1], "method" => "GET");
    }

    return false;
}

