<?php

namespace EditormdUtils;

use EditormdApp\SimpleCommentMode;

/**
 * 调试信息面板
 *
 * 10.3.0 安全加固说明：
 *   原实现把全部选项值、$_SERVER 字段直接拼接进 HTML 输出，完全未做转义，
 *   且把第三方图床的访问令牌明文展示，配合「导出调试信息为 PNG」
 *   功能会把令牌带出站点。现改为：
 *     1. 所有输出一律经过 esc_html()；
 *     2. 敏感字段（token / secret / key / password）一律打码；
 *     3. $_SERVER 与角色数组加 isset 兜底（CLI 环境下这些键并不存在）。
 *   注：本面板仅管理员可见，属于输出卫生问题（与 CVE-2025-31035 同属后台侧），
 *   并非匿名可达漏洞，但仍按最佳实践修正。
 */
class Debugger {

    /**
     * 敏感字段名关键字，命中后打码显示
     *
     * @var array<int, string>
     */
    private static $sensitive_keywords = array("token", "secret", "password", "passwd", "apikey", "api_key", "key");

    /**
     * 渲染调试信息面板
     *
     * @param string $text_domain
     *
     * @return string
     */
    public static function editormd_debug($text_domain) {

        $user = wp_get_current_user();

        // 需要展示的选项分组
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

        // ---- 运行环境 ----
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

        // jQuery 版本由前端脚本回填
        $debug_info .= '<tr><th>' . esc_html__("jQuery Version", $text_domain) . '</th><th id="jquery"></th></tr>';

        // 当前角色
        $roles = (is_object($user) && ! empty($user->roles) && is_array($user->roles))
            ? implode(", ", array_map("sanitize_text_field", $user->roles))
            : "";
        $debug_info .= '<tr><th>' . esc_html__("Current Roles", $text_domain) . '</th><th>' . esc_html($roles) . '</th></tr>';

        $debug_info .= '<tr><th>' . esc_html__("Site URL", $text_domain) . '</th><th>' . esc_html(site_url()) . '</th></tr>';
        $debug_info .= '<tr><th>' . esc_html__("Home URL", $text_domain) . '</th><th>' . esc_html(home_url()) . '</th></tr>';

        // ---- 选项分组 ----
        foreach ($option_groups as $option_name => $label) {
            $rows = self::render_option_rows((array) get_option($option_name));
            $debug_info .= '<tr><th>' . esc_html__($label, $text_domain) . '</th><th>' . $rows . '</th></tr>';
        }

        // ---- 评论区的 KSES 白名单与实际生效的额外行为 ----
        // 这一块回答的是「为什么我在评论里写的 Markdown 有一部分不显示」：
        // 评论属于不可信输入，WordPress 只用一张很小的 KSES 白名单过滤它，
        // 插件工具栏上却可能提供了白名单之外的功能。把当前**实际生效**的表格
        // 摊开给管理员看，比在文档里描述更可信。
        $debug_info .= '<tr><th>' . esc_html__("Comment HTML Whitelist (KSES)", $text_domain) . '</th><th>'
            . self::render_comment_kses($text_domain) . '</th></tr>';

        $debug_info .= '<tr><th>' . esc_html__("Comment Behavior Added By This Plugin", $text_domain) . '</th><th>'
            . self::render_comment_extras($text_domain) . '</th></tr>';

        // ---- 已启用插件 ----
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

    /**
     * 渲染一组选项键值对（全部转义，敏感项打码）
     *
     * @param array $options
     *
     * @return string
     */
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

    /**
     * 渲染评论区当前生效的 KSES 白名单
     *
     * 取的是 `wp_kses_allowed_html("pre_comment_content")` —— 也就是
     * `wp_filter_kses` 在评论保存期真正使用的那张表，因此这里列出的就是
     * 「评论里能活下来的标签」。同时按 SimpleCommentMode::added_tags()
     * 标注哪些条目**不是 WordPress 原生的**，而是本插件为了简版模式加的。
     *
     * @param string $text_domain
     *
     * @return string
     */
    private static function render_comment_kses($text_domain) {
        $allowed = wp_kses_allowed_html("pre_comment_content");

        if (! is_array($allowed) || empty($allowed)) {
            return esc_html__("(unavailable on this site)", $text_domain);
        }

        $plugin_tags = SimpleCommentMode::added_tags();
        $native_tpl  = __("[WordPress native]", $text_domain);
        $plugin_tpl  = __("[added by this plugin]", $text_domain);

        ksort($allowed);

        $rows = "";

        foreach ($allowed as $tag => $attributes) {
            $attribute_names = array_keys((array) $attributes);
            sort($attribute_names);

            $rows .= esc_html($tag) . " => "
                . esc_html(empty($attribute_names)
                    ? __("(no attribute)", $text_domain)
                    : implode(", ", $attribute_names))
                . " " . esc_html(isset($plugin_tags[$tag]) ? $plugin_tpl : $native_tpl)
                . " <br>";
        }

        return $rows;
    }

    /**
     * 渲染本插件对评论区新增的行为（全部标注为非原生）
     *
     * @param string $text_domain
     *
     * @return string
     */
    private static function render_comment_extras($text_domain) {
        $on    = __("enabled", $text_domain);
        $off   = __("disabled", $text_domain);
        $state = SimpleCommentMode::enabled() ? $on : $off;

        $rows = array(
            sprintf(
                /* translators: %s: enabled/disabled */
                __('Images: WordPress does not allow <img> inside comments natively; this plugin adds it to the whitelist above. Current state: %s', $text_domain),
                $state
            ),
            sprintf(
                /* translators: %s: enabled/disabled */
                __('Links: external links inside comments are degraded to read-only text and no <a> tag is produced. Current state: %s', $text_domain),
                $state
            ),
            sprintf(
                /* translators: %s: output template used for degraded links */
                __('Degraded link output template: %s', $text_domain),
                SimpleCommentMode::link_template()
            ),
            sprintf(
                /* translators: 1: CSS class names, 2: enabled/disabled */
                __('Comment body wrapper (%1$s) scopes image width and turns off emoji shortcodes inside comments. Current state: %2$s', $text_domain),
                SimpleCommentMode::BODY_CLASS . " / " . SimpleCommentMode::NO_EMOJIFY_CLASS,
                $state
            ),
            sprintf(
                /* translators: %s: enabled/disabled */
                __('Formulas / task lists / code blocks / emoji inside comments are turned off. Current state: %s', $text_domain),
                $state
            ),
        );

        return implode("<br>", array_map("esc_html", $rows));
    }

    /**
     * 敏感字段打码
     *
     * @param string $key
     * @param string $value
     *
     * @return string
     */
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

    /**
     * 安全读取 $_SERVER 字段
     *
     * @param string $key
     *
     * @return string
     */
    private static function server_value($key) {
        if (! isset($_SERVER[$key])) {
            return "";
        }

        return wp_unslash((string) $_SERVER[$key]);
    }

}
