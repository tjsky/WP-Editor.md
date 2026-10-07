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
        $base = Config::get_option("editor_addres", "editor_style");

        wp_enqueue_script("Mermaid-Compat", $base . "/assets/Mermaid/mermaid-compat.js", array(), WP_EDITORMD_VER, true);
        wp_enqueue_script("Mermaid", $base . "/assets/Mermaid/mermaid.min.js", array("Mermaid-Compat"), WP_EDITORMD_VER, true);
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
                    if (typeof mermaid === "undefined") {
                        return;
                    }

                    if (typeof window.wpEditormdMermaidPrepare === "function") {
                        window.wpEditormdMermaidPrepare(document);
                    }

                    $(".mermaid script").remove();

                    var userConfig = <?php echo wp_json_encode($decoded) ?>;
                    if (!userConfig || typeof userConfig !== "object") {
                        userConfig = {};
                    }

                    var config = $.extend({}, userConfig, {
                        startOnLoad: false,
                        securityLevel: "strict"
                    });

                    mermaid.initialize(config);

                    var nodes = document.querySelectorAll(".mermaid");
                    if (!nodes.length) {
                        return;
                    }

                    try {
                        if (typeof mermaid.run === "function") {
                            var result = mermaid.run({ nodes: nodes, suppressErrors: true });
                            if (result && typeof result.catch === "function") {
                                result.catch(function () { /* 渲染失败不影响页面其余部分 */ });
                            }
                        } else if (typeof mermaid.init === "function") {
                            mermaid.init(undefined, nodes);
                        }
                    } catch (err) {
                    }
                })
            })(jQuery)
        </script>
        <?php
    }
}
