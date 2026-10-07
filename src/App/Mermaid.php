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
        // 安全修复（10.4.2）：Mermaid 由 8.4.8 升级到 10.9.8（8.x / 9.x 各自的
        // CVE-2021-43861 一类问题在对应分支内均无修复版本）。
        // 10.x 移除了 mermaid.init()，而打包的 Editor.md 预览器仍在调用它，
        // 故加一层免构建的兼容垫片；垫片同时把 securityLevel 钉为 strict。
        $base = Config::get_option("editor_addres", "editor_style");

        wp_enqueue_script("Mermaid-Compat", $base . "/assets/Mermaid/mermaid-compat.js", array(), WP_EDITORMD_VER, true);
        wp_enqueue_script("Mermaid", $base . "/assets/Mermaid/mermaid.min.js", array("Mermaid-Compat"), WP_EDITORMD_VER, true);
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

        /**
         * 安全修复（10.4.2）：Mermaid 图表内容来自文章正文，属于「用户可控输入」，
         * 必须在渲染前把安全等级钉死：
         *   1. 升级到 Mermaid 10.9.8 —— 8.13.8 之前的版本存在 CVE-2021-43861，
         *      恶意图表可通过 %%{init: ...}%% 指令把 securityLevel 降级为 loose，
         *      进而执行注入的脚本 / 链接；
         *   2. securityLevel 与 startOnLoad 由插件**在合并用户配置之后**强制覆盖，
         *      用户配置（含文章里的指令）都无法再把安全等级调低；
         *   3. 显式调用 mermaid.run()，不再依赖库的自动启动时序。
         */
        ?>
        <script type="text/javascript">
            (function ($) {
                $(document).ready(function () {
                    if (typeof mermaid === "undefined") {
                        return;
                    }

                    // 先把 data-mermaid / 旧式 <script> 里的图表源码还原成节点文本
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
                        // 单张图表渲染失败不应中断页面
                    }
                })
            })(jQuery)
        </script>
        <?php
    }
}
