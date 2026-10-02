<?php

namespace EditormdUtils;

class PluginMeta {
    
    protected $text_domain;

    public function __construct($text_domain) {
        $this->text_domain = $text_domain;

        // Add settings link to plugins page
        add_filter("plugin_action_links_" . WP_EDITORMD_NAME, array($this, "add_settings_link"));

        // Add settings meta to plugins page
        add_filter("plugin_row_meta", array($this, "add_plugin_row_meta"), 10, 2);

    }

    /**
     * Add settings link to plugin list table
     *
     * @param  array $links Existing links
     *
     * @return array        Modified links
     */
    public function add_settings_link($actions) {
        return array_merge(
            array(
                '<a href="' . esc_url(admin_url("options-general.php?page=wp-editormd-settings")) . '" rel="nofollow">' . esc_html__("Settings", $this->text_domain) . "</a>",
                '<a href="https://github.com/tjsky/WP-Editor.md" target="_blank" rel="nofollow noopener">' . esc_html__("Github", $this->text_domain) . "</a>"
            ),
            $actions
        );
    }

    /**
     * 插件设置标签链接
     *
     * @param $links
     * @param $file
     *
     * @return array
     */
    public function add_plugin_row_meta($links, $file) {
        if (strpos($file, WP_EDITORMD_NAME) !== false) {
            $new_links = array(
                "Blog"   => '<a href="https://untitled.pw" target="_blank" rel="nofollow noopener">' . esc_html__("Blog", $this->text_domain) . "</a>",
                "Issues" => '<a href="https://github.com/tjsky/WP-Editor.md/issues" target="_blank" rel="nofollow noopener">' . esc_html__("Issues", $this->text_domain) . "</a>",
                "Docs"   => '<a href="https://github.com/LuRenJiasWorld/WP-Editor.md/wiki" target="_blank" rel="nofollow noopener">' . esc_html__("Docs", $this->text_domain) . "</a>"
            );
            $links     = array_merge($links, $new_links);
        }

        return $links;
    }

}