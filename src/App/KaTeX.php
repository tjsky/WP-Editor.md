<?php
/**
 * KaTeX support.
 *
 * Backward compatibility requires support for both "$$katex$$" shortcodes.
 *
 */

namespace EditormdApp;
use EditormdUtils\Config;

class KaTeX {

    private $skip_tags = array("pre", "code", "style", "script", "textarea");

    public function __construct() {

        add_filter("the_content", array($this, "katex_markup"), 9);
        add_filter("comment_text", array($this, "katex_markup"), 9);

        //前端加载资源
        add_action("wp_enqueue_scripts", array($this, "katex_enqueue_scripts"));

        if (! isset($GLOBALS["pagenow"]) || ! in_array($GLOBALS["pagenow"], array("wp-login.php", "wp-register.php"))) {
            //执行公式渲染操作
            add_action("wp_print_footer_scripts", array($this, "katex_wp_footer_scripts"));
        }

    }

    public function katex_markup($content) {
        if (! is_string($content) || false === strpos($content, '$')) {
            return $content;
        }

        $regex = '/\$\$([^$]+?)\$\$|\$([^$]+?)\$/s';

        $textarr = wp_html_split($content);

        $skip_depth = 0;

        foreach ($textarr as &$element) {
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

            if (null !== $replaced) {
                $element = $replaced;
            }
        }
        unset($element);

        return implode("", $textarr);
    }

    public function katex_universal_replace($matches) {
        $whole = $matches[0];

        $is_multiline = (0 === strpos($whole, '$$'));
        $content      = $is_multiline
            ? (isset($matches[1]) ? $matches[1] : '')
            : (isset($matches[2]) ? $matches[2] : '');

        if ('' === $content) {
            return $whole;
        }

        $content = str_replace(array("<em>", "</em>"), "_", $content);

        if (! $is_multiline && ! $this->katex_looks_like_formula($content)) {
            return $whole;
        }

        $katex = $this->katex_entity_decode_editormd($content);

        return '<span class="katex math ' . ($is_multiline ? 'multi-line' : 'inline') . '">'
            . esc_html( trim( $katex ) )
            . '</span>';
    }

    private function katex_looks_like_formula($content) {

        if (apply_filters("editormd_katex_require_tight_delimiters", true)) {
            if (ctype_space(substr($content, 0, 1)) || ctype_space(substr($content, -1))) {
                return false;
            }
        }

        if (! preg_match('/[A-Za-z0-9\\\\]/', $content)) {
            return false;
        }

        return true;
    }

    /**
     *
     * @return string|null
     */
    public function code_katex_src_replace($matches) {
        if (! empty($matches[1])) {
            $katex = $matches[1];
            $katex = $this->katex_entity_decode_editormd($katex);
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
