<?php

namespace EditormdApp;

use EditormdUtils\Config;

class Mermaid {

    public function __construct() {
        add_action("wp_enqueue_scripts", array($this, "mermaid_enqueue_scripts"));
        // 兼容性修复：$GLOBALS["pagenow"] 在部分上下文（CLI、REST）并不存在，补 isset 兜底
        if (!isset($GLOBALS["pagenow"]) || !in_array($GLOBALS["pagenow"], array("wp-login.php", "wp-register.php"))) {
            add_action("wp_print_footer_scripts", array($this, "mermaid_wp_footer_script"));
        }
    }

    public function mermaid_enqueue_scripts() {
        wp_enqueue_script("Mermaid",  Config::get_option("editor_addres","editor_style") . "/assets/Mermaid/mermaid.min.js", array(), WP_EDITORMD_VER, true);
    }

    public function mermaid_wp_footer_script() {
        $config = Config::get_option("mermaid_config", "editor_mermaid");

        // 兼容性/安全修复：原实现把配置原样 echo 进 JS 表达式位置，
        // 配置为空时会生成 mermaid.initialize(, ".mermaid"); 这样的语法错误，
        // 导致整个脚本块失效；同时配置内容可注入脚本上下文。
        // 现改为：先做 JSON 校验，非法/为空则回退空配置，再统一用 wp_json_encode 输出。
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
