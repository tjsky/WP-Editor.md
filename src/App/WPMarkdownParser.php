<?php

namespace EditormdApp;

use Michelf\MarkdownExtra;
use EditormdUtils\Config;

class WPMarkdownParser extends MarkdownExtra {
    /**
     * Preserve shortcodes, untouched by Markdown.
     * This requires use within a WordPress installation.
     *
     * @var boolean
     */
    public $preserve_shortcodes = true;

    /**
     * Preserve single-line <code> blocks.
     *
     * @var boolean
     */
    public $preserve_inline_code_blocks = true;

    /**
     * Strip paragraphs from the output. This is the right default for WordPress,
     * which generally wants to create its own paragraphs with `wpautop`
     *
     * @var boolean
     */
    public $strip_paras = true;

    // Stores shortcodes we remove and then replace
    public $preserve_text_hash = array();

    // Override base class definition
    public $contain_span_tags_re = "p|h[1-6]|li|dd|dt|td|th|legend|address";

    /**
     * Set environment defaults based on presence of key functions/classes.
     */
    public function __construct() {
        /**
         * Allow processing shortcode contents.
         *
         * @module markdown
         *
         * @param boolean $preserve_shortcodes Defaults to $this->preserve_shortcodes.
         *
         * @since  4.4.0
         *
         */
        $this->preserve_shortcodes = apply_filters("jetpack_markdown_preserve_shortcodes", $this->preserve_shortcodes) && function_exists("get_shortcode_regex");
        $this->strip_paras = function_exists("wpautop");

        $this->span_gamut += array(
            "doStrikethrough" => 55,
            "doHighlight"     => 55
        );

        parent::__construct();
    }

    /**
     * 重载方法：将标题更严格
     * Overload to specify heading styles only if the hash has space(s) after it. This is actually in keeping with
     * the documentation and eases the semantic overload of the hash character.
     * #Will Not Produce a Heading 1    不会生效
     * # This Will Produce a Heading 1  标准格式
     *
     * @param string $text Markdown text
     *
     * @return string       HTML-transformed text
     */
    public function transform($text) {
        // Preserve anything inside a single-line <code> element
        if ($this->preserve_inline_code_blocks) {
            $text = $this->single_line_code_preserve($text);
        }
        // Remove all shortcodes so their interiors are left intact
        if ($this->preserve_shortcodes) {
            $text = $this->shortcode_hash($text);
        }

        // escape line-beginning # chars that do not have a space after them.
        $text = preg_replace_callback("|^#{1,6}( )?|um", array($this, "_doEscapeForHashWithoutSpacing"), $text);
        /**
         * Allow third-party plugins to define custom patterns that won't be processed by Markdown.
         *
         * @module markdown
         *
         * @param array $custom_patterns Array of custom patterns to be ignored by Markdown.
         *
         * @since  3.9.2
         *
         */
        $custom_patterns = apply_filters("jetpack_markdown_preserve_pattern", array());
        if (is_array($custom_patterns) && !empty($custom_patterns)) {
            foreach ($custom_patterns as $pattern) {
                $text = preg_replace_callback($pattern, array($this, "_doRemoveText"), $text);
            }
        }

        if (Config::get_option("html_decode", "editor_basics") == "off") {
            // Ignore comments before encoding html
            $text = $this->comment_hash($text);

            // 兼容性修复：原实现未显式传 flags，而 PHP 8.1 起 htmlspecialchars() 的默认值
            // 已由 ENT_COMPAT 变为 ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML401——单引号会被转义成
            // &#039;，会改变存量内容的渲染结果。这里显式传 ENT_COMPAT 以冻结既有行为。
            $text = htmlspecialchars($text, ENT_COMPAT);
            $text = $this->codeblock_hash($text);
        }

        // run through core Markdown
        $text = parent::transform($text);

        // Occasionally Markdown Extra chokes on a para structure, producing odd paragraphs.
        $text = str_replace("<p>&lt;</p>\n\n<p>p>", "<p>", $text);

        // put start-of-line # chars back in place
        $text = $this->restore_leading_hash($text);

        // Strip paras if set
        if ($this->strip_paras) {
            $text = $this->unp($text);
        }

        // Restore preserved things like shortcodes/LaTeX
        $text = $this->do_restore($text);

        return $text;
    }

    /**
     * 重载方法：为<a>标签增加一个class，便于在前台js执行任务（如在新窗口打开链接）的时候区分文章内链接与页面内链接。
     * Callback for inline anchors
     * @param  array $matches
     * @return string
     */
    protected function _doAnchors_inline_callback($matches) {
        $whole_match    =  $matches[1];
        $link_text      =  $this->runSpanGamut($matches[2]);
        $url            =  $matches[3] == "" ? $matches[4] : $matches[3];
        $title          =& $matches[7];
        $attr  = $this->doExtraAttributes("a", $dummy =& $matches[8]);

        // if the URL was of the form <s p a c e s> it got caught by the HTML
        // tag parser and hashed. Need to reverse the process before using the URL.
        $unhashed = $this->unhash($url);
        if ($unhashed != $url)
            $url = preg_replace("/^<(.*)>$/", "\1", $unhashed);

        $url = $this->encodeURLAttribute($url);

        $result = "<a class=\"wp-editor-md-post-content-link\" href=\"$url\"";
        if (isset($title)) {
            $title = $this->encodeAttribute($title);
            $result .=  " title=\"$title\"";
        }
        $result .= $attr;

        $link_text = $this->runSpanGamut($link_text);
        $result .= ">$link_text</a>";

        return $this->hashPart($result);
    }

    /**
     * Prevents blocks like <code>__this__</code> from turning into <code><strong>this</strong></code>
     *
     * @param string $text Text that may need preserving
     *
     * @return string       Text that was preserved if needed
     */
    public function single_line_code_preserve($text) {
        return preg_replace_callback("|<code\b[^>]*>(.*?)</code>|", array($this, "do_single_line_code_preserve"), $text);
    }

    /**
     * Regex callback for inline code presevation
     *
     * @param array $matches Regex matches
     *
     * @return string         Hashed content for later restoration
     */
    public function do_single_line_code_preserve($matches) {
        return "<code>" . $this->hash_block($matches[1]) . "</code>";
    }

    /**
     * Preserve code block contents by HTML encoding them. Useful before getting to KSES stripping.
     *
     * @param string $text Markdown/HTML content
     *
     * @return string       Markdown/HTML content with escaped code blocks
     */
    public function codeblock_preserve($text) {
        return preg_replace_callback("/^([`~]{3})([^`\n]+)?\n([^`~]+)(\\1)/m", array($this, "do_codeblock_preserve"), $text);
    }

    /**
     * Regex callback for code block preservation.
     *
     * @param array $matches Regex matches
     *
     * @return string         Codeblock with escaped interior
     */
    public function do_codeblock_preserve($matches) {
        $block = esc_html($matches[3]);
        $open = $matches[1] . $matches[2] . "\n";

        return $open . $block . $matches[4];
    }

    /**
     * Restore previously preserved (i.e. escaped) code block contents.
     *
     * @param string $text Markdown/HTML content with escaped code blocks
     *
     * @return string       Markdown/HTML content
     */
    public function codeblock_restore($text) {
        return preg_replace_callback("/^([`~]{3})([^`\n]+)?\n([^`~]+)(\\1)/m", array($this, "do_codeblock_restore"), $text);
    }

    /**
     * Regex callback for code block restoration (unescaping).
     *
     * @param array $matches Regex matches
     *
     * @return string         Codeblock with unescaped interior
     */
    public function do_codeblock_restore($matches) {
        $block = html_entity_decode($matches[3], ENT_QUOTES);
        $open = $matches[1] . $matches[2] . "\n";

        return $open . $block . $matches[4];
    }

    /**
     * Called to preserve WP shortcodes from being formatted by Markdown in any way.
     *
     * @param string $text Text in which to preserve shortcodes
     *
     * @return string       Text with shortcodes replaced by a hash that will be restored later
     */
    protected function shortcode_hash($text) {
        $text = preg_replace_callback($this->get_shortcode_regex(), array($this, "_doRemoveText"), $text);

        return $text;
    }

    protected function comment_hash($text) {
        $text = preg_replace_callback("/<!--(.*?)-->/mix", function($match) {
            return $this->_doRemoveText([$match[0]]);
        }, $text);

        return $text;
    }

    protected function codeblock_hash($text) {
        $text = preg_replace_callback('
        %
            (?:
                (`(?:``)?)              ### 0: Begin block
                ([a-zA-Z]*\r?\n)?       ### 1: Language Identifier
                ([^`]+?)                ### 2: Block Body
                (\n?`(?:``)?)           ### 3: End Block
            )
        %mix', function($match) {
            switch (trim($match[2])) {
                case "mermaid":
                case "mind":
                    $match[3] = preg_replace("/\t/", "    ", $match[3]);
                    $match[3] = base64_encode($match[3]);
                break;
            }
            return $match[1] . $match[2] . $this->_doRemoveText([$match[3]]) . $match[4];
        }, $text);

        return $text;
    }

    /**
     * Restores any text preserved by $this->hash_block()
     *
     * @param string $text Text that may have hashed preservation placeholders
     *
     * @return string       Text with hashed preseravtion placeholders replaced by original text
     */
    protected function do_restore($text) {
        // Reverse hashes to ensure nested blocks are restored.
        $hashes = array_reverse($this->preserve_text_hash, true);
        foreach ($hashes as $hash => $value) {
            $placeholder = $this->hash_maker($hash);
            $text = str_replace($placeholder, $value, $text);
        }
        // reset the hash
        $this->preserve_text_hash = array();

        return $text;
    }

    /**
     * Regex callback for text preservation
     *
     * @param array $m Regex $matches array
     *
     * @return string    A placeholder that will later be replaced by the original text
     */
    protected function _doRemoveText($m) {
        return $this->hash_block($m[0]);
    }

    /**
     * Call this to store a text block for later restoration.
     *
     * @param string $text Text to preserve for later
     *
     * @return string       Placeholder that will be swapped out later for the original text
     */
    protected function hash_block($text) {
        $hash = md5($text);
        $this->preserve_text_hash[$hash] = $text;
        $placeholder = $this->hash_maker($hash);

        return $placeholder;
    }

    /**
     * Less glamorous than the Keymaker
     *
     * @param string $hash An md5 hash
     *
     * @return string       A placeholder hash
     */
    protected function hash_maker($hash) {
        return "MARKDOWN_HASH" . $hash . "MARKDOWN_HASH";
    }

    /**
     * Remove bare <p> elements. <p>s with attributes will be preserved.
     *
     * @param string $text HTML content
     *
     * @return string       <p>-less content
     */
    public function unp($text) {
        return preg_replace("#<p>(.*?)</p>(\n|$)#ums", "$1$2", $text);
    }

    /**
     * A regex of all shortcodes currently registered by the current
     * WordPress installation
     *
     * @return string A regex for grabbing shortcodes.
     * @uses   get_shortcode_regex()
     */
    protected function get_shortcode_regex() {
        $pattern = get_shortcode_regex();

        // don"t match markdown link anchors that could be mistaken for shortcodes.
        $pattern .= "(?!\()";

        return "/$pattern/s";
    }

    /**
     * Since we escape unspaced #Headings, put things back later.
     *
     * @param string $text text with a leading escaped hash
     *
     * @return string       text with leading hashes unescaped
     */
    protected function restore_leading_hash($text) {
        return preg_replace("/^(<p>)?(&#35;|\\\\#)/um", "$1#", $text);
    }

    /**
     * Callback for pre-processing start of line hashes to slyly escape headings that don't
     * have a leading space
     *
     * @param array $m preg_match matches
     *
     * @return string    possibly escaped start of line hash
     */
    public function _doEscapeForHashWithoutSpacing($m) {
        if (!isset($m[1])) {
            $m[0] = "\\" . $m[0];
        }

        return $m[0];
    }

    /**
     * 根据当前配置返回对应的代码块初始化脚本
     */
    private function getCodeBlockScript($codeblock) {
        if (Config::get_option("html_decode", "editor_basics") == "off") {
            return 'window.atob && window.atob("' . $codeblock . '")';
        } else {
            return '"' . $codeblock . '"';
        }
    }

    /**
     * 重载方法，支持Prism语法高亮和科学公式
     */
    public function _doFencedCodeBlocks_callback($matches) {
        $classname = $matches[2];
        $attrs = $matches[3];
        $codeblock = $matches[4];

        if ($this->code_block_content_func) {
            $codeblock = call_user_func($this->code_block_content_func, $codeblock, $classname);
        } else {
            $codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);
        }

        $codeblock = preg_replace_callback("/^\n+/", array($this, "_doFencedCodeBlocks_newlines"), $codeblock);

        // 避免代码块前包含<br />导致解析出现错误
        $codeblock = preg_replace("/^(<br \/>)*/", "", $codeblock);

        switch ($classname) {
            //Mermaid
            case "mermaid":
                // 安全/兼容性修复（10.4.2）：
                // 原实现输出 <div class="mermaid"><script>document.write(...)</script></div>，
                // 存在两个问题：
                //   1. 网站内容过滤器（实测为 wptexturize）会把脚本里的直引号改写成
                //      &#8220; 之类的排版实体，导致内联脚本直接语法错误，图表在正文里
                //      根本渲染不出来（10.4.1 及以前一直如此）；
                //   2. 把用户可控的图表源码拼进脚本上下文，是审查报告 P1-01 明确指出的风险面。
                // 现改为数据驱动：图表源码放进 data-mermaid 属性（预处理阶段已 base64 编码），
                // 由前端脚本（assets/Mermaid/mermaid-compat.js）还原成文本后再交给 Mermaid 渲染。
                //
                // 说明：此处 $codeblock 是 MARKDOWN_HASH… 占位符，解析结束后才会被替换成
                // base64 文本；base64 字符集放进属性是安全的，esc_attr 不会破坏占位符。
                $codeblock = '<div class="mermaid mermaid-diagram no-emojify" data-mermaid="'
                    . esc_attr($codeblock) . '"></div>';
                break;
            //思维导图
            case "mind":
                $codeblock = preg_replace("/\n/", "\\n", $codeblock);
                $codeblock = '<div class="mind no-emojify" style="width:100%;overflow:auto"><canvas></canvas><div class="mindTxt" style="display:none"><script type="text/javascript">document.write(' . $this->getCodeBlockScript($codeblock) . ');</script></div></div>';
                break;
            //科学公式
            case "math":
                $codeblock = '<div class="katex math multi-line no-emojify">' . $codeblock . '</div>';
                break;
            //科学公式
            case "latex":
                $codeblock = '<div class="katex math multi-line no-emojify">' . $codeblock . '</div>';
                break;
            //科学公式
            case "katex":
                $codeblock = '<div class="katex math multi-line no-emojify">' . $codeblock . '</div>';
                break;
            //代码块
            default:
                $classes = array("wp-editormd-codeblock");
                $langname = "";
                if ($classname != "") {
                    if ($classname[0] == ".") {
                        $classname = substr($classname, 1);
                    }

                    //检验语言类型 判断归纳
                    switch ($classname) {
                        case "html" :
                            $classname = "markup";
                            $langname = "HTML";
                            break;
                        case "xml" :
                            $classname = "markup";
                            $langname = "XML";
                            break;
                        case "svg" :
                            $classname = "markup";
                            $langname = "SVG";
                            break;
                        case "mathml" :
                            $classname = "markup";
                            $langname = "MathML";
                            break;
                    }
                    $classes[] = $this->code_class_prefix . "language-" . $classname;
                }

                //添加Prism相关的类名
                $classes[] = Config::get_option("line_numbers", "syntax_highlighting") == "on" ? "line-numbers" : "";

                //更新语言标识符
                if ($langname !== "") {
                    $dataLanguage = " data-language=" . $langname;
                } else {
                    $dataLanguage = "";
                }

                $attr_str = $this->doExtraAttributes($this->code_attr_on_pre ? "pre" : "code", $attrs, null, $classes);
                $pre_attr_str = $this->code_attr_on_pre ? $attr_str : $dataLanguage;
                $code_attr_str = $this->code_attr_on_pre ? "" : $attr_str;
                $codeblock = "<pre$pre_attr_str><code$code_attr_str>$codeblock</code></pre>";
        }
        return "\n\n" . $this->hashBlock($codeblock) . "\n\n";
    }

     /**
     * 重载方法，修复#392，让列表中的多行代码不会退化为单行代码
     */
    protected function handleSpanToken($token, &$str) {
        switch ($token[0]) {
            case "\\":
                return $this->hashPart("&#". ord($token[1]). ";");
            case "`":
                if ($token === "```") {
                    $tmp = substr($str, 0);
                    $str = "";
                    $tmp = "\n```" . $tmp . "\n";
                    $html = $this->doFencedCodeBlocks($tmp);

                    return $this->hashPart($html);
                } else {
                    // Search for end marker in remaining text.
                    if (preg_match("/^(.*?[^`])".preg_quote($token)."(?!`)(.*)$/sm",
                        $str, $matches))
                    {
                        $str = $matches[2];
                        $codespan = $this->makeCodeSpan($matches[1]);
                        return $this->hashPart($codespan);
                    }
                }
                return $token; // Return as text since no ending marker found.
            default:
                return $this->hashPart($token);
        }
    }

    /**
     * 高亮文本段，如下所示：
     * > 这是一段文字，==这部分==需要被高亮
     * 思路来源于 https://github.com/LuRenJiasWorld/WP-Editor.md/issues/467
     * 在其他Markdown编辑器中有所实现
     * 注：此处应该使用更规范的匹配策略（Look behind/forward），但因为JS对这一功能支持不佳，因此为保证预览和渲染一致性，换用较为简单的正则表达式
     */
    protected function doHighlight($text) {
        // In: text ==highlight== from doc
        // Out: text <span class="text-highlighted-inline" style="background-color: #fffd38;">highlight</span>
        $parts = preg_split("/(==)/", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (count($parts) <= 1) {
            return $text;
        }
        $inTag = false;
        $beginHTML = '<span class="text-highlighted-inline" style="background-color: #fffd38;">';
        $endHTML   = '</span>';
        foreach ($parts as &$part) {
            if ($part == "==") {
                $part = ($inTag ? $endHTML : $beginHTML);
                $inTag = !$inTag;
            }
        }
        //no hanging delimiter
        if ($inTag) {
            $parts[] = $endHTML;
        }

        return implode("", $parts);
    }

    ###  Strikethrough ###
    protected function doStrikethrough($text) {
        #
        # Strikethrough:
        #    in:  text ~~deleted~~ from doc
        #    out: text <del>deleted</del> from doc
        #
        $parts = preg_split("/(?<![~])(~~)(?![~])/", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        //don't bother if nothing to do...
        if (count($parts) <= 1) {
            return $text;
        }
        $inTag = false;
        foreach ($parts as &$part) {
            if ($part == "~~") {
                $part = ($inTag ? "</del>" : "<del>");
                $inTag = !$inTag;
            }
        }
        //no hanging delimiter
        if ($inTag) {
            $parts[] = "</del>";
        }

        return implode("", $parts);
    }

    ###  Images (with optional size)  ###

    /**
     * 重载父类 MarkdownExtra::doImages()，增加图片尺寸语法支持。
     *
     * 语法（尺寸块放在右括号之前，可与 title 共存）：
     *   ![alt](img.jpg =600)              仅宽度
     *   ![alt](img.jpg =600x)             仅宽度（等价写法）
     *   ![alt](img.jpg =600x400)          宽度 + 高度
     *   ![alt](img.jpg =x400)             仅高度
     *   ![alt](img.jpg "title" =600x400){#id .class}
     *
     * 与上游 PR #602 的差别（#602 未采纳）：
     *   该 PR 直接复制了旧版 php-markdown 的整套实现，占用了父类用于
     *   {#id .class} 的 $matches[8]，并顺带丢掉了 MarkdownExtra 的
     *   属性语法（doExtraAttributes）与引用式图片的 ref_attr 支持。
     *   本实现把尺寸放到 $8/$9/$10，属性块顺移到 $11，能力不减。
     *
     * 为什么只输出 HTML 属性、不写内联 style：
     *   1. 属性优先级低于主题 CSS，主题的 img{max-width:100%;height:auto}
     *      仍然生效 —— 大图在小屏不会溢出，这是移动端最要紧的一点；
     *   2. 属性在图片加载完成前就给出尺寸占位，减少布局抖动（CLS）；
     *   3. 不干扰基于 getBoundingClientRect() 计算起点与终点的看图插件
     *      （如 Medium Zoom）：缩放动画依据的是元素实际渲染出来的矩形，
     *      属性只是决定了这个矩形的大小，语义上是「缩略图多大」。
     *   若某个主题需要强制尺寸，可用 editormd_image_size_attributes 过滤器改写。
     *
     * @param string $text
     *
     * @return string
     */
    protected function doImages($text) {
        // 引用式图片 ![alt][id] 交由父类处理 —— 该语法没有括号，无处书写尺寸
        $text = preg_replace_callback('{
            (				# wrap whole match in $1
              !\[
                (' . $this->nested_brackets_re . ')		# alt text = $2
              \]

              [ ]?				# one optional space
              (?:\n[ ]*)?		# one optional newline followed by spaces

              \[
                (.*?)		# id = $3
              \]

            )
            }xs',
            array($this, '_doImages_reference_callback'), $text);

        // 行内图片：在父类正则基础上，于 ) 之前插入可选的尺寸块
        $text = preg_replace_callback('{
            (				# wrap whole match in $1
              !\[
                (' . $this->nested_brackets_re . ')		# alt text = $2
              \]
              \s?			# One optional whitespace character
              \(			# literal paren
                [ \n]*
                (?:
                    <(\S*)>	# src url = $3
                |
                    (' . $this->nested_url_parenthesis_re . ')	# src url = $4
                )
                [ \n]*
                (			# $5
                  ([\'"])	# quote char = $6
                  (.*?)		# title = $7
                  \6		# matching quote
                  [ \n]*
                )?			# title is optional
                (?:			# optional size block
                    [ \n]+	# 必须与 URL 之间有空白，避免误伤 URL 中的 =（如 ?a=b）
                    =
                    [ \n]*
                    (?:
                        (\d{1,5})			# $8 width（=W / =Wx / =WxH）
                        (?:[ \n]* [xX] [ \n]* (\d{1,5})? )?	# $9 height（可省略）
                      |
                        [xX] [ \n]* (\d{1,5})	# $10 height（=xH）
                    )
                )?
              \)
              (?:[ ]? ' . $this->id_class_attr_catch_re . ' )?	 # $11 = id/class attributes
            )
            }xs',
            array($this, '_doImages_inline_callback'), $text);

        return $text;
    }

    /**
     * 行内图片回调：在父类实现基础上补输出 width / height。
     *
     * @param array $matches
     *
     * @return string
     */
    protected function _doImages_inline_callback($matches) {
        $alt_text    = $matches[2];
        $url         = $matches[3] === '' ? $matches[4] : $matches[3];
        $title_quote =& $matches[6];
        $title       =& $matches[7];

        // 属性块在父类中是 $8，此处被尺寸块占用，故顺移到 $11
        $attr = $this->doExtraAttributes("img", $dummy =& $matches[11]);

        // 尺寸：$8 宽；$9 高（=WxH 写法）；$10 高（=xH 写法）
        $width  = (isset($matches[8]) && '' !== $matches[8]) ? (int) $matches[8] : 0;
        $height = 0;
        if (isset($matches[9]) && '' !== $matches[9]) {
            $height = (int) $matches[9];
        }
        if (isset($matches[10]) && '' !== $matches[10]) {
            $height = (int) $matches[10];
        }

        $alt_text = $this->encodeAttribute($alt_text);
        $url      = $this->encodeURLAttribute($url);
        $result   = "<img src=\"$url\" alt=\"$alt_text\"";
        if (isset($title) && $title_quote) {
            $title   = $this->encodeAttribute($title);
            $result .= " title=\"$title\""; // $title already quoted
        }
        $result .= $attr;

        // 属性块里显式写了 width / height 时以它为准
        // （MarkdownExtra 支持 key=value 属性，如 ![alt](u.jpg =600){#id width=999}）
        if (preg_match('/\swidth\s*=/i', $attr)) {
            $width = 0;
        }
        if (preg_match('/\sheight\s*=/i', $attr)) {
            $height = 0;
        }

        /**
         * 尺寸输出采用「内联 style + width/height 属性」的组合。
         *
         * 为什么必须用内联 style：
         *   多数主题会对正文图片声明 width，例如
         *     .view-content img { width: auto; max-width: 90% }
         *   CSS 声明的优先级高于 HTML 的 width/height 属性，属性会被直接忽略，
         *   只输出属性的话尺寸功能在这类主题下完全不起作用。
         *
         * 各写法说明：
         *   max-width:100%  —— 窄屏不溢出（主题若已有更强规则则以主题为准）
         *   height:auto     —— 避免「宽度被 max-width 压缩、高度保持原值」造成变形
         *   aspect-ratio:W/H —— 高度随宽度等比缩放，宽屏下恰好等于指定高度；
         *                       现代浏览器支持，老浏览器降级为原图比例
         * 只给高度时不加 aspect-ratio（缺少宽度无从推导），此时以精确高度为准。
         *
         * 与 Medium Zoom 的关系：
         *   该库通过 getBoundingClientRect() 读取元素渲染后的矩形来定位放大动画，
         *   并 cloneNode() 原图取 src，不读写原图的 style，因此内联样式无副作用。
         */
        $style = "";
        if ($width > 0 && $height > 0) {
            $style = sprintf(
                "width:%dpx;max-width:100%%;height:auto;aspect-ratio:%d/%d",
                $width,
                $width,
                $height
            );
        } elseif ($width > 0) {
            $style = sprintf("width:%dpx;max-width:100%%;height:auto", $width);
        } elseif ($height > 0) {
            $style = sprintf("height:%dpx;width:auto;max-width:100%%", $height);
        }

        /**
         * 过滤尺寸样式，便于主题或用户接管（返回空串即改为只输出属性）。
         *
         * @param string $style  默认样式串
         * @param int    $width  指定宽度，未指定为 0
         * @param int    $height 指定高度，未指定为 0
         * @param string $url    已编码的图片地址
         */
        $style = apply_filters("editormd_image_size_style", $style, $width, $height, $url);

        // 属性同时输出：供不支持 aspect-ratio 的浏览器作降级，并便于主题/插件读取
        if ($width > 0) {
            $result .= ' width="' . $width . '"';
        }
        if ($height > 0) {
            $result .= ' height="' . $height . '"';
        }
        if ("" !== $style) {
            $result .= ' style="' . esc_attr($style) . '"';
        }

        $result .= $this->empty_element_suffix;

        return $this->hashPart($result);
    }
}
