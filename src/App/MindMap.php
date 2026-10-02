<?php

namespace EditormdApp;

use EditormdUtils\Config;

class MindMap {

    public function __construct() {

        add_action("wp_enqueue_scripts", array($this, "mindmap_enqueue_scripts"));
        // 兼容性修复：$GLOBALS["pagenow"] 在部分上下文（CLI、REST）并不存在，补 isset 兜底
        if (!isset($GLOBALS["pagenow"]) || !in_array($GLOBALS["pagenow"], array("wp-login.php", "wp-register.php"))) {
            add_action("wp_print_footer_scripts", array($this, "mindmap_wp_footer_script"));
        }
    }

    public function mindmap_enqueue_scripts() {

        // jQuery 统一使用 WordPress 自带版本。
        // 安全修复：原「非兼容模式」会 wp_deregister_script("jquery") 并改为加载 jQuery 1.12.4，
        // 该版本存在已知 XSS 漏洞（如 CVE-2015-9251），且会破坏依赖 core jQuery 的其它插件。
        wp_enqueue_script("jquery");

        $mindmap_url = Config::get_option("customize_mindmap", "editor_mindmap");

        // 兼容性修复：该选项本身就是完整的脚本地址，为空时原实现会把
        // 空字符串交给 wp_enqueue_script()，在部分场景下等于让浏览器把当前页面地址当脚本加载。
        // 这里增加地址有效性校验（支持 http(s) 与协议相对地址）。
        if (is_string($mindmap_url) && "" !== trim($mindmap_url) && preg_match("#^(https?:)?//#i", trim($mindmap_url))) {
            wp_enqueue_script("MindMap", $mindmap_url, array(), WP_EDITORMD_VER, true);
        }
    }

    public function mindmap_wp_footer_script() {
        ?>
        <script type="text/javascript">
            (function ($) {
                $(document).ready(function () {
                    $(".mind p").remove();
                    $(".mind .mindTxt script").remove();
                    var mind = $(".mind");
                    if (mind.drawMind !== undefined) {
                        mind.drawMind();
                    }
                })
            })(jQuery)
        </script>
        <?php
    }
}
