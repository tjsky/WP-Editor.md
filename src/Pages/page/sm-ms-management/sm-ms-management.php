<?php
// sm.ms图片管理页面

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

    $plugin_path = wp_parse_url(WP_EDITORMD_URL, PHP_URL_PATH);
    if (is_string($plugin_path) && "" !== $plugin_path) {
        $html = str_replace("__EDITORMD_ASSET_BASE__", ltrim($plugin_path, "/"), $html);
    }

    $bootstrap = array(
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
 * - url: 代理地址
 * - method: 代理方法(post, get)
 * - header: 请求头(数组，不是字典)
 * - body: 请求体(字典)
 */
function wp_editormd_entry_sm_ms_proxy() {
    if (! current_user_can("manage_options")) {
        wp_die("Forbidden", "Forbidden", array("response" => 403));
    }

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

    $operation = wp_editormd_sm_ms_resolve_operation($url, $method);
    if (false === $operation) {
        wp_die("Forbidden upstream", "Forbidden upstream", array("response" => 403));
    }

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

function wp_editormd_sm_ms_resolve_operation($url, $method) {
    if (! is_string($url) || "" === $url) {
        return false;
    }

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

    if (false !== strpos($path, "..")) {
        return false;
    }

    if ("/api/v2/profile" === $path) {
        return array("url" => "https://smms.app/api/v2/profile", "method" => "POST");
    }

    if ("/api/v2/upload_history" === $path) {
        $query = "";
        if (! empty($parts["query"])) {
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

