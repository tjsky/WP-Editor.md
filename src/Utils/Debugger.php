<?php

namespace EditormdUtils;

class Debugger {

    private static $sensitive_keywords = array("token", "secret", "password", "passwd", "apikey", "api_key", "key");

    public static function editormd_debug($text_domain) {

        $user = wp_get_current_user();

        $option_groups = array(
            "editor_basics"       => "Basic Settings",
            "editor_style"        => "Editor Style Settings",
            "syntax_highlighting" => "Syntax Highlighting Settings",
            "editor_emoji"        => "Emoji Settings",
            "editor_toc"          => "TOC Settings",
            "editor_latex"        => "KaTeX Settings",
            "editor_mermaid"      => "Mermaid Settings",
            "editor_mindmap"      => "MindMap Settings",
            "editor_advanced"     => "Advanced Settings",
        );

        $debug_info = '<div class="debugger-wrap">';

        $debug_info .= '<hr />';

        $debug_info .= '<button style="margin: 10px;" id="debugger-download" class="button button-primary">'
            . esc_html__('Export debugging info', $text_domain) . '</button>';

        $debug_info .= '<div>';

        $debug_info .= '<table style="margin: 10px 10px 20px;">';

        $environment_rows = array(
            __("Operating System", $text_domain)     => PHP_OS,
            __("Operating Environment", $text_domain) => self::server_value("SERVER_SOFTWARE"),
            __("PHP Version", $text_domain)          => PHP_VERSION,
            __("PHP Operating Mode", $text_domain)   => php_sapi_name(),
            __("Browser Information", $text_domain)  => self::server_value("HTTP_USER_AGENT"),
            __("WordPress Version", $text_domain)    => isset($GLOBALS["wp_version"]) ? $GLOBALS["wp_version"] : "",
            __("WP Editor.md Version", $text_domain) => WP_EDITORMD_VER,
        );

        foreach ($environment_rows as $label => $value) {
            $debug_info .= '<tr><th>' . esc_html($label) . '</th><th>' . esc_html((string) $value) . '</th></tr>';
        }

        $debug_info .= '<tr><th>' . esc_html__("jQuery Version", $text_domain) . '</th><th id="jquery"></th></tr>';

        $roles = (is_object($user) && ! empty($user->roles) && is_array($user->roles))
            ? implode(", ", array_map("sanitize_text_field", $user->roles))
            : "";
        $debug_info .= '<tr><th>' . esc_html__("Current Roles", $text_domain) . '</th><th>' . esc_html($roles) . '</th></tr>';

        $debug_info .= '<tr><th>' . esc_html__("Site URL", $text_domain) . '</th><th>' . esc_html(site_url()) . '</th></tr>';
        $debug_info .= '<tr><th>' . esc_html__("Home URL", $text_domain) . '</th><th>' . esc_html(home_url()) . '</th></tr>';

        foreach ($option_groups as $option_name => $label) {
            $rows = self::render_option_rows((array) get_option($option_name));
            $debug_info .= '<tr><th>' . esc_html__($label, $text_domain) . '</th><th>' . $rows . '</th></tr>';
        }

        $plugins = array();
        foreach ((array) get_option("active_plugins") as $key => $value) {
            $plugins[] = $key . " => " . $value;
        }
        $debug_info .= '<tr><th>' . esc_html__("Enabled Plugins List", $text_domain) . '</th><th>'
            . esc_html(implode("\n", $plugins)) . '</th></tr>';

        $debug_info .= "</table>";

        $debug_info .= "</div>";

        $debug_info .= "</div>";

        return $debug_info;
    }

    private static function render_option_rows($options) {
        $rows = "";

        foreach ($options as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = wp_json_encode($value);
            }

            $rows .= esc_html($key) . " => " . esc_html(self::mask_sensitive($key, (string) $value)) . " <br>";
        }

        return $rows;
    }

    private static function mask_sensitive($key, $value) {
        $lower_key = strtolower((string) $key);

        foreach (self::$sensitive_keywords as $keyword) {
            if (false !== strpos($lower_key, $keyword)) {
                if ("" === $value) {
                    return "";
                }

                return substr($value, 0, 4) . "******";
            }
        }

        return $value;
    }

    private static function server_value($key) {
        if (! isset($_SERVER[$key])) {
            return "";
        }

        return wp_unslash((string) $_SERVER[$key]);
    }

}
