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
        // jQuery 统一使用 WordPress 自带版本。
        // 安全修复：原「非兼容模式」会 wp_deregister_script("jquery") 并改为加载 jQuery 1.12.4，
        // 该版本存在已知 XSS 漏洞（如 CVE-2015-9251），且会破坏依赖 core jQuery 的其它插件。
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
     *
     * 兼容性修复：原实现无条件移除 wp-block-library / wp-block-library-theme / wc-blocks-style，
     * 在区块主题（block theme）或内容本身含区块的站点上会直接破坏前台样式。
     * 现改为：仅在「非区块主题」且当前请求不是区块渲染上下文时才移除，
     * 并允许通过 editormd_dequeue_block_styles 过滤器关闭该行为。
     */
    public function dequeue_bloat_scripts() {
        if (function_exists("wp_is_block_theme") && wp_is_block_theme()) {
            return;
        }

        // 单篇内容若包含区块，同样需要保留区块样式
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
