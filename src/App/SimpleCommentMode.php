<?php

namespace EditormdApp;

use EditormdUtils\Config;

/**
 * 访客评论「简版编辑器」的服务端约束层
 *
 * 背景
 *   WordPress 对评论这类**不可信输入**使用的是一张很小的 KSES 标签白名单
 *   （`wp_kses_allowed_html('pre_comment_content')` 只放行 15 个标签）。
 *   编辑器工具栏却提供了图片、代码块、表格、标题、列表等按钮 ——
 *   它们在编辑器预览里正常显示（预览是纯前端的），但保存时会被
 *   `pre_comment_content` 链上的 `wp_filter_kses` 剥掉，于是出现
 *   「预览正常、发出去就没了」的落差。
 *
 * 本类做的事
 *   1. 按需为评论上下文放行 `<img>`（简版模式的图片功能依赖它）；
 *   2. 给评论正文套一层插件自有容器，同时承担两个职责：
 *      - `no-emojify`：emojify 的 TreeWalker 黑名单命中后返回 FILTER_REJECT，
 *        整棵子树被跳过 —— 这是「评论区不再替换 :name: 短代码」的实现方式；
 *      - `editormd-comment-body`：给评论内图片提供 `max-width` 的 CSS 作用域，
 *        不依赖具体主题的 DOM 结构。
 *
 * 约束
 *   - 只在 `simple_comment_editor` 与 `support_front` 同时开启时生效；
 *   - 白名单是**追加**的，不会移除 WordPress 原生的任何条目；
 *   - 提供 `editormd_simple_comment_enabled` 过滤器作为逃生口。
 */
class SimpleCommentMode {

    /**
     * 设置项名称（editor_basics 分组）
     */
    const OPTION = "simple_comment_editor";

    /**
     * KSES 上下文：评论内容的保存期过滤器名
     */
    const CONTEXT = "pre_comment_content";

    /**
     * 评论正文容器类名（CSS 作用域）
     */
    const BODY_CLASS = "editormd-comment-body";

    /**
     * emojify 黑名单类名（命中后整棵子树被跳过）
     */
    const NO_EMOJIFY_CLASS = "no-emojify";

    /**
     * 链接降级模板：`[文字](url)` → `「文字：<code>url</code>」`
     *
     * 「」与「：」是**字面字符**（按需求确认）。若要改成别的形态，
     * 覆写 `editormd_simple_comment_link_template` 过滤器即可。
     */
    const LINK_TEMPLATE = "「%s：<code>%s</code>」";

    /**
     * 本插件为评论额外放行的 KSES 条目
     *
     * 这些**不是 WordPress 原生的支持**，调试信息面板会把来源标注出来。
     * 刻意不放进来的属性：`class` / `style` / `srcset` / `sizes` / 任何 `on*`
     * —— 它们要么能影响页面布局，要么能承载脚本。
     *
     * @var array<string, array<string, bool>>
     */
    private static $extra_tags = array(
        "img" => array(
            "src"            => true,
            "alt"            => true,
            "width"          => true,
            "height"         => true,
            "loading"        => true,
            "referrerpolicy" => true,
        ),
    );

    public function __construct() {
        add_filter("wp_kses_allowed_html", array($this, "allow_comment_tags"), 10, 2);

        // 优先级 99：必须排在 wpautop(30) 之后，否则 wpautop 会往容器里塞裸 <p>
        add_filter("comment_text", array($this, "wrap_comment_body"), 99);
    }

    /**
     * 简版模式是否生效
     *
     * 需要「简版开关」与「前台评论编辑器」同时开启 —— 否则本插件对评论内容
     * 的改造（放行图片、包裹容器）在站点上根本没有配套的编辑器，属于无端改动。
     *
     * @return bool
     */
    public static function enabled() {
        $enabled = Config::get_option(self::OPTION, "editor_basics") === "on"
            && Config::get_option("support_front", "editor_basics") === "on";

        /**
         * 过滤器：单站点强制开启 / 关闭简版评论模式
         *
         * @param bool $enabled
         */
        return (bool) apply_filters("editormd_simple_comment_enabled", $enabled);
    }

    /**
     * 当前解析是否处于「简版评论上下文」
     *
     * 供 WPMarkdownParser / KaTeX / TaskList 判断是否需要走降级分支。
     *
     * @return bool
     */
    public static function is_simple_context() {
        return self::enabled();
    }

    /**
     * 本插件为评论额外放行的标签（供调试信息面板标注来源）
     *
     * @return array<string, array<string, bool>>
     */
    public static function added_tags() {
        return self::$extra_tags;
    }

    /**
     * 链接降级模板
     *
     * @return string printf 模板，两个 %s 依次为「链接文字」「URL」
     */
    public static function link_template() {
        /**
         * 过滤器：自定义链接降级后的输出形态
         *
         * @param string $template
         */
        return (string) apply_filters("editormd_simple_comment_link_template", self::LINK_TEMPLATE);
    }

    /**
     * 为评论上下文追加 KSES 白名单
     *
     * 只在 `pre_comment_content` 上下文生效；`post`（文章）等其它上下文一律原样返回，
     * 所以不会影响文章编辑器与其它插件的 KSES 行为。
     *
     * @param array[]|string $tags    当前允许的标签表
     * @param string         $context KSES 上下文（即 current_filter()）
     *
     * @return array[]|string
     */
    public function allow_comment_tags($tags, $context) {
        if (self::CONTEXT !== $context || ! is_array($tags) || ! self::enabled()) {
            return $tags;
        }

        foreach (self::$extra_tags as $tag => $attributes) {
            $existing = (isset($tags[$tag]) && is_array($tags[$tag])) ? $tags[$tag] : array();

            // 左侧优先：本插件声明的属性集为准，同时保留其它插件已放行的属性
            $tags[$tag] = $attributes + $existing;
        }

        return $tags;
    }

    /**
     * 给评论正文套一层插件自有容器
     *
     * 注意：这里是**渲染期**过滤器，不再经过 KSES（KSES 只作用于保存期），
     * 所以容器的 class 不会被剥。
     *
     * @param string $content 已经过 wpautop 的评论 HTML
     *
     * @return string
     */
    public function wrap_comment_body($content) {
        if (! self::enabled() || ! is_string($content) || "" === trim($content)) {
            return $content;
        }

        // 避免重复包裹（comment_text 可能被调用多次 / 被其它过滤器嵌套）
        if (false !== strpos($content, self::BODY_CLASS)) {
            return $content;
        }

        return '<div class="' . self::BODY_CLASS . ' ' . self::NO_EMOJIFY_CLASS . '">'
            . $content
            . '</div>';
    }

    /* ------------------------------------------------------------ 渲染后处理 */

    /**
     * 锚点标签的正则片段：允许属性值里出现 `>`（Michelf 的 encodeAttribute 不转义它）
     */
    const TAGS_RE = '(?:[^>"\']|"[^"]*"|\'[^\']*\')*';

    /**
     * 链接降级：把所有可点击的 `<a>` 换成只读文本
     *
     * 规则（顺序即优先级）：
     *   0. 页内锚点（href 以 `#` 开头）—— 保留原样。脚注与目录依赖它，且无钓鱼风险；
     *   1. `mailto:` —— 解包成纯文本；
     *   2. 锚内出现 `<img` —— 解包，只留内容（**保证图片永远不可点击**）；
     *   3. 锚文本与 href 相同（裸 `<url>` autolink）—— 只输出 `<code>url</code>`，
     *      避免出现「地址：地址」的重复；
     *   4. 其余 —— 按 LINK_TEMPLATE 输出 `「文字：<code>url</code>」`。
     *
     * 为什么在「解析结果」上做，而不是覆写 doAnchors()/doAutoLinks()：
     *   Michelf 的 `_doAnchors_inline_callback()` 等回调返回的是 `hashPart()` 之后的
     *   占位符（`\x1A…\x1A`），那一刻 `<a>` 标签并不存在于文本里；而引用式链接
     *   与 autolink 又走父类的另外两条回调。统一在 transform() 的产物上处理，
     *   一处覆盖全部链接来源，也避免复制父类实现。
     *
     * @param string $html 已完成 Markdown 转换的 HTML
     *
     * @return string
     */
    public static function degrade_links($html) {
        if (! is_string($html) || false === stripos($html, "<a")) {
            return $html;
        }

        $template = self::link_template();

        $result = preg_replace_callback(
            '/<a\s(' . self::TAGS_RE . ')>(.*?)<\/a>/is',
            function ($matches) use ($template) {
                $attr_string = $matches[1];
                $inner       = $matches[2];

                if (! preg_match('/\bhref\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attr_string, $href_match)) {
                    return $matches[0];
                }

                $href_encoded = "";
                if (isset($href_match[2]) && "" !== $href_match[2]) {
                    $href_encoded = $href_match[2];
                } elseif (isset($href_match[3]) && "" !== $href_match[3]) {
                    $href_encoded = $href_match[3];
                } elseif (isset($href_match[4])) {
                    $href_encoded = $href_match[4];
                }

                $href = html_entity_decode($href_encoded, ENT_QUOTES, "UTF-8");

                // 0. 页内锚点保留
                if ("" === $href || "#" === substr($href, 0, 1)) {
                    return $matches[0];
                }

                // 1. 邮件地址解包
                if (0 === stripos($href, "mailto:")) {
                    return $inner;
                }

                // 2. 含图片的锚解包
                if (false !== stripos($inner, "<img")) {
                    return $inner;
                }

                $text = html_entity_decode(trim(strip_tags($inner)), ENT_QUOTES, "UTF-8");

                // 3. 裸 URL 短路
                if ("" !== $text && $text === $href) {
                    return "<code>" . $href_encoded . "</code>";
                }

                // 4. 标准降级
                return sprintf($template, $inner, $href_encoded);
            },
            $html
        );

        // 正则出错（如回溯上限）时返回 null，此时保留原文，绝不能让正文消失
        return null === $result ? $html : $result;
    }

    /**
     * 给评论里的图片补上安全与性能属性
     *
     * 评论图片来自访客粘贴的任意地址，因此：
     *   - `loading="lazy"`：避免一屏之外的图片全部立刻发起请求；
     *   - `referrerpolicy="no-referrer"`：不给第三方域名送去本站地址与访问路径。
     *
     * 这两个属性已由 add_filter("wp_kses_allowed_html") 放行，否则会被 KSES 剥掉。
     *
     * @param string $html
     *
     * @return string
     */
    public static function decorate_images($html) {
        if (! is_string($html) || false === stripos($html, "<img")) {
            return $html;
        }

        $result = preg_replace_callback(
            '/<img\b(' . self::TAGS_RE . ')(\s*\/?>)/i',
            function ($matches) {
                $attrs = rtrim($matches[1]);
                $close = "" !== trim($matches[2]) ? trim($matches[2]) : ">";
                $extra = "";

                if (! preg_match('/\bloading\s*=/i', $attrs)) {
                    $extra .= ' loading="lazy"';
                }
                if (! preg_match('/\breferrerpolicy\s*=/i', $attrs)) {
                    $extra .= ' referrerpolicy="no-referrer"';
                }

                return "<img" . $attrs . $extra . (">" === $close ? ">" : " " . $close);
            },
            $html
        );

        return null === $result ? $html : $result;
    }
}
