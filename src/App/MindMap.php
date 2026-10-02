<?php

namespace EditormdApp;

use EditormdUtils\Config;

class MindMap {

    public function __construct() {

        add_action("wp_enqueue_scripts", array($this, "mindmap_enqueue_scripts"));
        if (!isset($GLOBALS["pagenow"]) || !in_array($GLOBALS["pagenow"], array("wp-login.php", "wp-register.php"))) {
            add_action("wp_print_footer_scripts", array($this, "mindmap_wp_footer_script"));
        }
    }

    public function mindmap_enqueue_scripts() {

        wp_enqueue_script("jquery");

        $mindmap_url = Config::get_option("customize_mindmap", "editor_mindmap");

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
