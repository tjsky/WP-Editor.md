<?php

namespace EditormdFront;

use EditormdUtils\Config;

class FrontStyle {
    /**
     * @var string 插件名称
     */
    private $plugin_name;

    /**
     * @var string 插件版本号
     */
    private $version;

    /**
     * @var string 翻译文本域
     */
    private $text_domain;

    /**
     * @var string 静态资源地址
     */
    private $front_static_url;

    /**
     * Controller constructor 初始化类并设置其属性
     *
     * @param $plugin_name
     * @param $version
     * @param $ioption
     */
    public function __construct() {
        $this->plugin_name      = "WP Editor.md";
        $this->text_domain      = "editormd";
        $this->version          = WP_EDITORMD_VER;
        $this->front_static_url = Config::get_option("editor_addres", "editor_style");

        add_action("wp_enqueue_scripts", array($this, "enqueue_front_scripts"));
        add_action("wp_enqueue_scripts", array($this, "dequeue_bloat_scripts"));
    }

    /**
     * 注册脚本文件
     */
    public function enqueue_front_scripts() {
        wp_enqueue_script("jquery");
        $jQueryName = "jquery";

        wp_enqueue_style("Front_Style", $this->front_static_url . "/assets/FrontStyle/FrontStyle.min.css", array(), WP_EDITORMD_VER, "all");
        wp_enqueue_script("Front_Style", $this->front_static_url . "/assets/FrontStyle/FrontStyle.min.js", array($jQueryName), $this->version, true);

        wp_localize_script("Front_Style", "FrontStyle", array(
            "openLinkInNewTab"  =>  Config::get_option("open_in_new_tab", "editor_basics")        // 在新标签页打开链接
        ));
    }

    /**
     * 禁用不需要的脚本文件（Gutenberg引入）
     * Ref issue: https://github.com/LuRenJiasWorld/WP-Editor.md/issues/560
     */
    public function dequeue_bloat_scripts() {
        if (function_exists("wp_is_block_theme") && wp_is_block_theme()) {
            return;
        }

        if (is_singular() && function_exists("has_blocks") && has_blocks()) {
            return;
        }

        if (! apply_filters("editormd_dequeue_block_styles", true)) {
            return;
        }

        wp_dequeue_style("wp-block-library");
        wp_dequeue_style("wp-block-library-theme");
        wp_dequeue_style("wc-blocks-style");
    }
}
