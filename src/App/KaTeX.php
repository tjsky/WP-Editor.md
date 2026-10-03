<?php
/**
 * KaTeX support.
 *
 * Backward compatibility requires support for both "$$katex$$" shortcodes.
 *
 * 改造说明（相对原实现）：
 *   1. $...$ 与 $$...$$ 由两条过滤器合并为一条正则、一次遍历；
 *   2. 跳过区域由「精确匹配 <pre>」改为「标签名白名单 + 嵌套深度计数」；
 *   3. 内联公式增加误渲染防护（采纳上游 PR #603 的思路，但保留输出转义）。
 * 细节见各方法内注释。
 */

namespace EditormdApp;
use EditormdUtils\Config;

class KaTeX {

    /**
     * 解析过程中需要整体跳过的标签。
     *
     * 这些标签内的内容不是正文（代码、样式、脚本），不应参与公式替换。
     *
     * @var string[]
     */
    private $skip_tags = array("pre", "code", "style", "script", "textarea");

    public function __construct() {

        // 单次遍历统一处理 $...$ 与 $$...$$。
        //
        // 原实现拆成两个过滤器：katex_markup_double（优先级 8）先处理 $$...$$，
        // katex_markup_single（优先级 9）再处理 $...$。而 single 用来判断
        // 「是否已被 double 处理过」的条件是 '<div class="katex math'，
        // double 实际输出的却是 <span class="katex math multi-line"> —— 判断永不成立，
        // 于是块级公式被内联规则二次解析（上游 issue #597「多行公式诡异渲染两次」）。
        //
        // 合并为一条正则（$$ 分支在前）后，该问题从结构上消失，同时少跑一次
        // wp_html_split()，大篇幅内容的开销减半。
        add_filter("the_content", array($this, "katex_markup"), 9);
        add_filter("comment_text", array($this, "katex_markup"), 9);

        //前端加载资源
        add_action("wp_enqueue_scripts", array($this, "katex_enqueue_scripts"));

        // 兼容性修复：$GLOBALS["pagenow"] 在部分上下文（CLI、REST）并不存在，补 isset 兜底
        if (! isset($GLOBALS["pagenow"]) || ! in_array($GLOBALS["pagenow"], array("wp-login.php", "wp-register.php"))) {
            //执行公式渲染操作
            add_action("wp_print_footer_scripts", array($this, "katex_wp_footer_scripts"));
        }

    }

    /**
     * 公式解析主流程。
     *
     * @param string $content 已经过 Markdown 解析的正文 / 评论 HTML
     *
     * @return string
     */
    public function katex_markup($content) {
        if (! is_string($content) || false === strpos($content, '$')) {
            return $content;
        }

        /**
         * 统一正则：两条分支，$$ 分支在前以保证块级公式优先被识别
         * （否则 $$E=mc^2$$ 会被内联规则切成两个 $ 并各自匹配失败）。
         *
         * 公式内容用 [^$]+? 而不是原来的 [\S\s]+?：
         *   1. 防止一个未闭合的定界符跨过下一个公式去吞并中间正文；
         *   2. [^$] 是单字符类、外层无嵌套量词，不存在指数级回溯
         *      （原代码注释里已提过 LaTeX 正则的性能隐患，此处按可证明的
         *       线性行为来写：最坏情况每轮只有一次失败尝试）。
         */
        $regex = '/\$\$([^$]+?)\$\$|\$([^$]+?)\$/s';

        $textarr = wp_html_split($content);

        /**
         * 跳过区域用「标签名白名单 + 嵌套深度计数」维护。
         *
         * 原实现写的是 htmlspecialchars_decode($element) === "<pre>"，而实际代码块会被
         * 渲染成 <pre class="..."> 或 <pre><code> —— 精确等值判断永远不成立，
         * 跳过逻辑等于是失效的：这就是上游「$ 不显示 / 代码块被吃 / 奇数个 $ 导致整块消失」
         * 一系列 issue（#596 #538 #504 #488 #482 #536）的共同根因。
         *
         * 用深度计数而非布尔值，是为了正确处理 <pre><code>…</code></pre> 这类嵌套。
         */
        $skip_depth = 0;

        foreach ($textarr as &$element) {
            // HTML 标签本身不参与替换，但用于维护跳过状态
            if (isset($element[0]) && "<" === $element[0]) {
                if (preg_match('/^<\/?([a-z0-9]+)/i', $element, $m)
                     && in_array(strtolower($m[1]), $this->skip_tags, true)) {
                    $skip_depth = ("/" === substr($element, 1, 1))
                        ? max(0, $skip_depth - 1)
                        : $skip_depth + 1;
                }
                continue;
            }

            if ($skip_depth > 0 || "" === $element || false === strpos($element, '$')) {
                continue;
            }

            $replaced = preg_replace_callback($regex, array($this, "katex_universal_replace"), $element);

            // 正则返回 null 表示出错了（如回溯上限被触发）；此时保留原文，
            // 绝不能让整段内容变成空串
            if (null !== $replaced) {
                $element = $replaced;
            }
        }
        unset($element);

        return implode("", $textarr);
    }

    /**
     * 统一正则的回调：区分块级 / 内联，做误渲染判断后输出。
     *
     * @param array $matches
     *
     * @return string
     */
    public function katex_universal_replace($matches) {
        $whole = $matches[0];

        // 用匹配到的原文前缀判断类型，不依赖未参与分组被省略的规则
        $is_multiline = (0 === strpos($whole, '$$'));
        $content      = $is_multiline
            ? (isset($matches[1]) ? $matches[1] : '')
            : (isset($matches[2]) ? $matches[2] : '');

        if ('' === $content) {
            return $whole;
        }

        // 公式里的 _ 会被 Markdown 先行解析成 <em>，这里还原回来
        // （沿用原 katex_src_replace 的处理，作用域仍在定界符内部，
        //   不会像 PR #603 那样作用于整个元素从而误伤正文的斜体）
        $content = str_replace(array("<em>", "</em>"), "_", $content);

        // 内联公式做误渲染防护；块级公式独立成行、误伤概率极低，不做限制
        if (! $is_multiline && ! $this->katex_looks_like_formula($content)) {
            return $whole;
        }

        $katex = $this->katex_entity_decode_editormd($content);

        // 安全修复（必须保留）：katex_entity_decode_editormd() 会把 &lt; / &gt; / &quot;
        // 等实体还原成真实字符，若直接拼接输出即构成存储型 XSS —— the_content 与
        // comment_text 都运行在保存期 kses 之后，wp_kses 拦不住，评论路径的可利用门槛更低。
        // KaTeX 前端用 .text() 取值渲染、浏览器会自行还原实体，因此 esc_html() 不影响公式显示。
        return '<span class="katex math ' . ($is_multiline ? 'multi-line' : 'inline') . '">'
            . esc_html( trim( $katex ) )
            . '</span>';
    }

    /**
     * 判断内联 $...$ 捕获到的内容「像不像公式」，用于压掉正文里的误渲染。
     *
     * 典型的误渲染：正文出现两个互不相关的美元符号，例如
     *   function update( $new_instance, $old_instance )   → 中间被当成公式
     *   $100 和 $200                                      → 同上
     * 这类内容是普通文本或代码片段，不是数学。
     *
     * @param string $content 定界符之间的原始内容（未解码）
     *
     * @return bool
     */
    private function katex_looks_like_formula($content) {

        /**
         * 规则一：定界符与内容之间有空白则不渲染（采纳上游 PR #603 的思路）。
         *
         * 规范写法 $x$ 的定界符是紧贴内容的，出现空格基本可判定是正文误配。
         * 但它同时会让「习惯写 $ x $ 的用户」失效，因此留一个过滤器开关。
         * 注：PR #603 仅靠这一条做防护，且未修输出转义 —— 那条绕过路径
         * （把空格挪到内容中间，如 $p &lt; img … &gt; q$）在本实现里由
         * 输出侧的 esc_html() 兜住，不依赖此规则。
         */
        if (apply_filters("editormd_katex_require_tight_delimiters", true)) {
            if (ctype_space(substr($content, 0, 1)) || ctype_space(substr($content, -1))) {
                return false;
            }
        }

        /**
         * 规则二：内容不含任何字母、数字或反斜杠时视为解析残留。
         *
         * 用来挡掉 ^、, 、- 这类碎片被单独立成公式（如正文里的 "^$" 与 "$,"）。
         * LaTeX 的反斜杠命令、变量名、数字至少会命中其中一类。
         */
        if (! preg_match('/[A-Za-z0-9\\\\]/', $content)) {
            return false;
        }

        return true;
    }

    /**
     * code 内公式渲染（遗留接口，当前未被任何代码调用）。
     *
     * 保留是为了不破坏可能存在的第三方调用；主流程已改为在 <code> 内整体跳过。
     *
     * @param array $matches
     *
     * @return string|null
     */
    public function code_katex_src_replace($matches) {
        if (! empty($matches[1])) {
            $katex = $matches[1];
            $katex = $this->katex_entity_decode_editormd($katex);
            // 安全修复：输出转义（同上）。
            return '<span class="katex math inline">' . esc_html( trim( $katex ) ) . '</span>';
        }

        return null;
    }

    /**
     * 渲染转换
     * 
     * 解决特殊字符可能会与HTML标签冲突的问题
     * 需要注意的是转换后的html entities两边要带空格
     * 这也是为什么不直接使用htmlentities()的主要原因
     * 否则如果用户没有在符号两边加空格的习惯
     * 就会导致entities与LaTeX公式混在一起
     * 
     * @param $katex
     *
     * @return mixed
     */
    public function katex_entity_decode_editormd($katex) {
        return str_replace(
            array(" &lt; "  , " &gt; " , " &quot; ", " &#039; ", 
                  " &#038; ", " &amp; ", " \n "    , " \r "    , 
                  " &#60; " , " &#62; ", " &#40; " , " &#41; " ,
                  " &#95; " , " &#33; ", " &#123; ", " &#125; ", 
                  " &#94; " , " &#43; ", " &#92; "
                ),
                   
            array("<"       , ">"      , "\""      , "\'"      , 
                  "&"       , "&"      , " "       , " "       , 
                  "<"       , ">"      , "("       , ")"       , 
                  "_"       , "!"      , "{"       , "}"       , 
                  "^"       , "+"      , "\\\\"   
                ),
                   
            $katex);
    }

    public function katex_enqueue_scripts() {

        // jQuery 统一使用 WordPress 自带版本。
        // 安全修复：原「非兼容模式」会 wp_deregister_script("jquery") 并改为加载 jQuery 1.12.4，
        // 该版本存在已知 XSS 漏洞（如 CVE-2015-9251），且会破坏依赖 core jQuery 的其它插件，故移除该分支。
        wp_enqueue_script("jquery");

        wp_enqueue_style("Katex", Config::get_option("editor_addres","editor_style") . "/assets/KaTeX/katex.min.css", array(), WP_EDITORMD_VER, "all");
        wp_enqueue_script("Katex", Config::get_option("editor_addres","editor_style") . "/assets/KaTeX/katex.min.js", array(), WP_EDITORMD_VER, true);

    }

    public function katex_wp_footer_scripts() {
        ?>
        <script type="text/javascript">
            (function ($) {
                $(document).ready(function () {
                    $(".katex.math.inline").each(function () {
                        var parent = $(this).parent()[0];
                        if (parent.localName !== "code") {
                            var texTxt = $(this).text();
                            var el = $(this).get(0);
                            try {
                                katex.render(texTxt, el);
                            } catch (err) {
                                $(this).text(err);
                            }
                        } else {
                            $(this).parent().text($(this).parent().text());
                        }
                    });
                    $(".katex.math.multi-line").each(function () {
                        var texTxt = $(this).text();
                        var el = $(this).get(0);
                        try {
                            katex.render(texTxt, el, {displayMode: true})
                        } catch (err) {
                            $(this).text(err)
                        }
                    });
                })
            })(jQuery);
        </script>
        <?php
    }
}
