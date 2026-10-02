<?php

namespace EditormdApp;

use EditormdUtils\Config;

class Mermaid {

    public function __construct() {
        add_action("wp_enqueue_scripts", array($this, "mermaid_enqueue_scripts"));
        if (!isset($GLOBALS["pagenow"]) || !in_array($GLOBALS["pagenow"], array("wp-login.php", "wp-register.php"))) {
            add_action("wp_print_footer_scripts", array($this, "mermaid_wp_footer_script"));
        }
    }

    public function mermaid_enqueue_scripts() {
        wp_enqueue_script("Mermaid",  Config::get_option("editor_addres","editor_style") . "/assets/Mermaid/mermaid.min.js", array(), WP_EDITORMD_VER, true);
    }

    public function mermaid_wp_footer_script() {
        $config = Config::get_option("mermaid_config", "editor_mermaid");

        $decoded = json_decode((string) $config, true);
        if (! is_array($decoded)) {
            $decoded = array();
        }
        ?>
        <script type="text/javascript">
            (function ($) {
                $(document).ready(function () {
                    $(".mermaid script").remove();
                    mermaid.initialize(<?php echo wp_json_encode($decoded) ?>, ".mermaid");
                })
            })(jQuery)
        </script>
        <?php
    }
}
