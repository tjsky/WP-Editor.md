/**
 * Mermaid 10 兼容垫片（WP Editor.md）
 *
 * 背景：
 *   插件原先把 Mermaid 固定在 8.4.8，该版本存在已公开的高危漏洞
 *   （CVE-2021-43861 / GHSA-p3rp-vmj9-gv6v：恶意图表可通过 %%{init: ...}%%
 *   指令把 securityLevel 降级，从而执行注入内容）。10.3.0 的安全加固未覆盖它。
 *
 *   升级到 10.9.8 后（8.x / 9.x 各有一条公告在各自分支内无修复版本），
 *   上游移除了 mermaid.init()，而打包的 Editor.md 预览器仍在调用：
 *       mermaid.init({startOnLoad: true}, ".mermaid")
 *   直接升级会让编辑器内的图表预览失效，因此这里补一个小垫片。
 *
 * 垫片做两件事：
 *   1. 以旧签名实现 mermaid.init()，内部转发到 mermaid.initialize() + mermaid.run()；
 *   2. 无论配置来自哪里，都把 securityLevel 钉成 strict、startOnLoad 钉成 false，
 *      避免「用户配置或图表指令把安全等级调低」。
 *
 * 注意：本文件必须比 Mermaid 本体更早出现在页面上（见各处的 enqueue 依赖关系）。
 */
(function (global) {
    "use strict";

    var realMermaid;

    /* ------------------------------------------------------------------
     * 图表源码还原
     *
     * 服务端现在输出：  <div class="mermaid" data-mermaid="<base64>"></div>
     * （源码在保存阶段被 base64 编码，见 WPMarkdownParser::_doFencedCodeBlocks）
     *
     * 旧内容则是：      <div class="mermaid"><script>document.write(window.atob("…"))</script></div>
     * 那种写法会被站点的内容过滤器（wptexturize）改写引号而失效，这里一并还原，
     * 使升级前的历史文章也能正常渲染。
     * ------------------------------------------------------------------ */
    function base64ToText(b64) {
        var raw;
        try {
            raw = global.atob(b64);
        } catch (e) {
            return null;
        }

        // atob 返回的是「字节串」，含中文时需要按 UTF-8 再解一次
        try {
            if (global.TextDecoder) {
                var bytes = new Uint8Array(raw.length);
                for (var i = 0; i < raw.length; i++) {
                    bytes[i] = raw.charCodeAt(i);
                }
                return new global.TextDecoder("utf-8").decode(bytes);
            }
        } catch (e) {
            /* 回退到原始字节串 */
        }

        return raw;
    }

    function decodeEntities(text) {
        // textarea 的内容按 RCDATA 处理：实体被解码，标签不会被解析、也不会执行脚本
        var el = document.createElement("textarea");
        el.innerHTML = text;
        return el.value;
    }

    function prepare(root) {
        var nodes = (root || document).querySelectorAll(".mermaid");

        Array.prototype.forEach.call(nodes, function (node) {
            var b64 = node.getAttribute("data-mermaid");

            if (b64) {
                var text = base64ToText(b64);
                if (text !== null) {
                    node.textContent = decodeEntities(text);
                }
                node.removeAttribute("data-mermaid");
                return;
            }

            // 兼容旧内容
            var script = node.querySelector("script");
            if (!script) {
                return;
            }

            var pattern = /atob\([^)]*?["“”']([A-Za-z0-9+/=\s]+)["“”']/;
            var matched = pattern.exec(script.textContent || "");
            if (matched) {
                var legacy = base64ToText(matched[1].replace(/\s+/g, ""));
                if (legacy !== null) {
                    node.textContent = decodeEntities(legacy);
                }
            }

            if (script.parentNode) {
                script.parentNode.removeChild(script);
            }
        });
    }

    // 供前台渲染脚本（见 src/App/Mermaid.php）与编辑器预览共用
    global.wpEditormdMermaidPrepare = prepare;

    function installCompat(mermaid) {
        if (!mermaid || typeof mermaid !== "object") {
            return mermaid;
        }

        // 已经是带 init 的旧版本，或缺少 run()，都无需处理
        if (typeof mermaid.init === "function" || typeof mermaid.run !== "function") {
            return mermaid;
        }

        mermaid.init = function (config, selector) {
            var cfg = {};

            if (config && typeof config === "object") {
                for (var key in config) {
                    if (Object.prototype.hasOwnProperty.call(config, key)) {
                        cfg[key] = config[key];
                    }
                }
            }

            // 安全等级与启动方式由插件强制指定，任何调用方都无法覆盖
            cfg.startOnLoad = false;
            cfg.securityLevel = "strict";

            try {
                mermaid.initialize(cfg);
            } catch (e) {
                // 配置非法时继续尝试渲染已有节点
            }

            var nodes;
            if (typeof selector === "string") {
                nodes = document.querySelectorAll(selector);
            } else if (selector && typeof selector.length === "number") {
                nodes = selector;
            } else {
                nodes = document.querySelectorAll(".mermaid");
            }

            prepare(document);

            if (!nodes || !nodes.length) {
                return;
            }

            try {
                var pending = mermaid.run({ nodes: nodes, suppressErrors: true });
                if (pending && typeof pending.catch === "function") {
                    pending.catch(function () { /* 单张图表失败不影响页面 */ });
                }
            } catch (e) {
                // 忽略：渲染失败不应抛出到页面
            }
        };

        return mermaid;
    }

    // 用存取器拦截赋值：UMD 构建会执行 global.mermaid = ...，
    // 这样无论 Mermaid 何时被加载（编辑器是运行时动态加载的），都能装上垫片。
    try {
        Object.defineProperty(global, "mermaid", {
            configurable: true,
            get: function () {
                return realMermaid;
            },
            set: function (value) {
                realMermaid = installCompat(value);
            }
        });
    } catch (e) {
        var timer = global.setInterval(function () {
            if (global.mermaid) {
                installCompat(global.mermaid);
                global.clearInterval(timer);
            }
        }, 50);
        global.setTimeout(function () {
            global.clearInterval(timer);
        }, 20000);
    }

    if (global.mermaid) {
        installCompat(global.mermaid);
    }
})(window);
