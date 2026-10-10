<?php

namespace EditormdUtils;

/**
 * 静态资源完整性校验（Subresource Integrity）
 *
 * 「编辑器静态资源地址」允许填本站以外的地址（CDN）。这样一来，页面里的可执行代码
 * 就由外部主机提供：设置填错，或对方主机被入侵、被替换，都会让访客执行到别人的 JS。
 *
 * 本类把管理员配置的哈希表（editor_style[editor_integrity]）套到插件输出的
 * <script> / <link> 标签上，交给浏览器做校验：哈希对不上就直接拒绝执行。
 *
 * 约定：
 * - 只处理「跨站」资源。本站（同协议+域名+端口）的资源不加属性，免得自伤。
 * - 键可以是资源根下的相对路径（assets/xxx），也可以是完整 URL（用于思维导图这类独立地址）。
 * - 没配置哈希的跨站资源不会被拦截，而是记进调试信息，配合设置页的持续提示由管理员处理。
 * - 页面里由脚本运行时动态加载的资源（CodeMirror 附加模块、marked 等）走 loadScript /
 *   loadCSS，由前端脚本侧用同一张表加属性；Prism 的语法组件无法逐个校验，直接固定走本机。
 */
class AssetsIntegrity {

    /** 选项名（存放在 editor_style 这一组里） */
    const OPTION = "editor_integrity";

    /** 哈希算法。sha384 是 SRI 的推荐值，浏览器兼容性也最好。 */
    const HASH_ALGO = "sha384";

    /**
     * 本次请求里成功加上 integrity 的资源
     *
     * @var array
     */
    public static $applied = array();

    /**
     * 本次请求里跨站、但没有对应哈希的资源（用于调试信息）
     *
     * @var array
     */
    public static $missing = array();

    /**
     * 解析后的哈希表缓存
     *
     * @var array|null
     */
    private static $map = null;

    /**
     * 本站主机名缓存
     *
     * @var array|null
     */
    private static $site_hosts = null;

    public function __construct() {
        add_filter("script_loader_tag", array($this, "filter_script_tag"), 10, 3);
        add_filter("style_loader_tag", array($this, "filter_style_tag"), 10, 4);
    }

    /**
     * 给 WordPress 入队的脚本标签加 integrity
     *
     * @param string $tag    完整标签
     * @param string $handle 句柄
     * @param string $src    资源地址
     *
     * @return string
     */
    public function filter_script_tag($tag, $handle, $src) {
        $hash = self::hash_for_url($src);

        if ("" === $hash) {
            return $tag;
        }

        self::record($handle, $src, $hash);

        return self::with_integrity($tag, $hash);
    }

    /**
     * 给 WordPress 入队的样式标签加 integrity
     *
     * @param string $tag    完整标签
     * @param string $handle 句柄
     * @param string $href   资源地址
     * @param string $media  media 属性
     *
     * @return string
     */
    public function filter_style_tag($tag, $handle, $href, $media) {
        $hash = self::hash_for_url($href);

        if ("" === $hash) {
            return $tag;
        }

        self::record($handle, $href, $hash);

        return self::with_integrity($tag, $hash);
    }

    /**
     * 供直接输出的 <script> / <link> 使用：返回要附加的属性串
     *
     * @param string $url 资源地址
     *
     * @return string 空字符串表示不需要（本站资源或没有哈希）
     */
    public static function tag_attrs($url) {
        $hash = self::hash_for_url($url);

        if ("" === $hash) {
            return "";
        }

        self::record("", $url, $hash);

        return ' integrity="' . esc_attr($hash) . '" crossorigin="anonymous"';
    }

    /**
     * 把哈希写进标签。已在标签里的 integrity 不再覆盖。
     *
     * @param string $tag  标签
     * @param string $hash 哈希
     *
     * @return string
     */
    private static function with_integrity($tag, $hash) {
        if (! is_string($tag) || "" === $tag) {
            return $tag;
        }

        if (false !== stripos($tag, " integrity=")) {
            return $tag;
        }

        $attrs = ' integrity="' . esc_attr($hash) . '" crossorigin="anonymous"';

        foreach (array("src", "href") as $attr) {
            if (preg_match('/\s' . $attr . '=(["\'])(.*?)\1/i', $tag, $matches)) {
                $pos = strpos($tag, $matches[0]);

                if (false !== $pos) {
                    $pos += strlen($matches[0]);

                    return substr($tag, 0, $pos) . $attrs . substr($tag, $pos);
                }
            }
        }

        return $tag;
    }

    /**
     * 记录本次请求的生效 / 缺失情况，供调试信息展示
     *
     * @param string $handle 句柄
     * @param string $url    资源地址
     * @param string $hash   哈希
     *
     * @return void
     */
    private static function record($handle, $url, $hash) {
        $url = (string) $url;

        if ("" !== $url && isset(self::$missing[$url])) {
            unset(self::$missing[$url]);
        }

        $key = ("" !== (string) $handle) ? (string) $handle : $url;

        self::$applied[$key] = array(
            "url"  => $url,
            "hash" => $hash,
        );
    }

    /**
     * 解析配置的哈希表
     *
     * @return array
     */
    public static function map() {
        if (null !== self::$map) {
            return self::$map;
        }

        self::$map = self::parse_lines(Config::get_option(self::OPTION, "editor_style"));

        return self::$map;
    }

    /**
     * 是否有可用的哈希配置
     *
     * @return bool
     */
    public static function has_entries() {
        return ! empty(self::map());
    }

    /**
     * 传给前端脚本的哈希表
     *
     * 只有「资源在别的主机」且「配了哈希」时才需要：页面里由脚本按需加载的资源
     * （CodeMirror 附属模块、marked 等）无法用 PHP 过滤器加属性，改由前端脚本补。
     * 其余情况一律返回空数组，站点不用为这个功能多背几 KB 的页面数据。
     *
     * @return array
     */
    public static function runtime_config() {
        if (! self::root_is_external() || ! self::has_entries()) {
            return array();
        }

        return array(
            "root" => self::root(),
            "map"  => self::map(),
        );
    }

    /**
     * 解析「键=哈希」文本，只保留合法的行
     *
     * @param string $raw 原始文本
     *
     * @return array
     */
    public static function parse_lines($raw) {
        $out = array();

        if (! is_string($raw) || "" === trim($raw)) {
            return $out;
        }

        foreach ((array) preg_split("/\r\n|\r|\n/", $raw) as $line) {
            $line = trim($line);

            if ("" === $line || "#" === substr($line, 0, 1)) {
                continue;
            }

            $pos = strpos($line, "=");

            if (false === $pos) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));

            if (! self::is_valid_key($key) || ! self::is_valid_hash($val)) {
                continue;
            }

            $out[self::normalize_key($key)] = $val;
        }

        return $out;
    }

    /**
     * 把解析后的哈希表拼回文本（保存时用）
     *
     * @param string $raw 原始文本
     *
     * @return string
     */
    public static function sanitize_lines($raw) {
        $lines = array();

        foreach (self::parse_lines($raw) as $key => $hash) {
            $lines[] = $key . "=" . $hash;
        }

        return implode("\n", $lines);
    }

    /**
     * 键是否合法：相对路径，或 http(s) 完整地址
     *
     * @param string $key 键
     *
     * @return bool
     */
    public static function is_valid_key($key) {
        if (! is_string($key) || "" === $key) {
            return false;
        }

        if (preg_match('#^https?://[^\s"\'<>\\\\]+$#i', $key)) {
            return true;
        }

        return (bool) preg_match('#^/?[A-Za-z0-9][A-Za-z0-9._~%+@/-]*$#', $key);
    }

    /**
     * 值是否合法：sha256/sha384/sha512 + base64
     *
     * @param string $hash 值
     *
     * @return bool
     */
    public static function is_valid_hash($hash) {
        return is_string($hash) && (bool) preg_match('#^sha(?:256|384|512)-[A-Za-z0-9+/]+=*$#', $hash);
    }

    /**
     * 统一键的写法：相对路径去掉开头的斜杠
     *
     * @param string $key 键
     *
     * @return string
     */
    private static function normalize_key($key) {
        $key = trim((string) $key);

        if (preg_match('#^https?://#i', $key)) {
            return $key;
        }

        return ltrim($key, "/");
    }

    /**
     * 取某个资源地址对应的哈希
     *
     * @param string $url 资源地址
     *
     * @return string 空字符串表示「本站资源」或「没有配置」
     */
    public static function hash_for_url($url) {
        if (! is_string($url) || "" === trim($url)) {
            return "";
        }

        $clean = preg_replace('/[?#].*$/', "", trim($url));

        if ("" === $clean || ! self::is_external($clean)) {
            return "";
        }

        $map  = self::map();
        $keys = array($clean, self::relative_key($clean));

        foreach ($keys as $key) {
            if ("" !== $key && isset($map[$key])) {
                return $map[$key];
            }
        }

        // 只在「确实归我们管」时才记为缺失：位于资源根之下
        if ("" !== self::relative_key($clean)) {
            self::$missing[$clean] = true;
        }

        return "";
    }

    /**
     * 资源地址相对资源根的路径
     *
     * @param string $url 资源地址
     *
     * @return string 空字符串表示不在资源根之下
     */
    public static function relative_key($url) {
        $root = self::root();

        if ("" === $root) {
            return "";
        }

        $url_clean  = preg_replace('#^https?://#i', "", (string) $url);
        $root_clean = preg_replace('#^https?://#i', "", $root);

        if (0 === strpos($url_clean, $root_clean . "/")) {
            return ltrim(substr($url_clean, strlen($root_clean)), "/");
        }

        return "";
    }

    /**
     * 资源根地址
     *
     * @return string
     */
    public static function root() {
        $root = (string) Config::get_option("editor_addres", "editor_style");

        if ("" === trim($root)) {
            $root = WP_EDITORMD_URL;
        }

        return untrailingslashit(trim($root));
    }

    /**
     * 资源根是否指向别的主机
     *
     * @return bool
     */
    public static function root_is_external() {
        return self::is_external(self::root());
    }

    /**
     * 地址是否由本站以外的主机提供
     *
     * 这里只比主机名，不比协议与端口：
     * 加 integrity 就必须同时加 crossorigin，而跨源请求要对方回 CORS 头。
     * 同一主机上因为协议或端口不同而被判成「跨源」，反而可能把本来好好的资源拦下来，
     * 所以只要主机是本站，就当作本地资源、不做校验（本来也没有第三方能篡改它）。
     *
     * @param string $url 地址
     *
     * @return bool
     */
    public static function is_external($url) {
        return ! self::host_is_site($url);
    }

    /**
     * 取地址里的主机名（小写）
     *
     * @param string $url 地址
     *
     * @return string 相对地址或无法解析时返回空字符串
     */
    public static function host($url) {
        if (! is_string($url) || "" === trim($url)) {
            return "";
        }

        $url = trim($url);

        // 协议相对地址（//example.com/x.js）
        if (0 === strpos($url, "//")) {
            $url = "http:" . $url;
        }

        // 相对地址：视为本站
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            return "";
        }

        $parts = wp_parse_url($url);

        if (! is_array($parts) || empty($parts["host"])) {
            return "";
        }

        return strtolower($parts["host"]);
    }

    /**
     * 地址是否属于本站（home_url / site_url 任一主机名）
     *
     * @param string $url 地址
     *
     * @return bool
     */
    public static function host_is_site($url) {
        $host = self::host($url);

        if ("" === $host) {
            return true;
        }

        return in_array($host, self::site_hosts(), true);
    }

    /**
     * 本站可能出现的所有主机名
     *
     * @return array
     */
    private static function site_hosts() {
        if (null !== self::$site_hosts) {
            return self::$site_hosts;
        }

        $hosts = array();

        foreach (array(home_url(), site_url()) as $home) {
            $host = self::host($home);

            if ("" !== $host) {
                $hosts[] = $host;
            }
        }

        self::$site_hosts = array_values(array_unique($hosts));

        return self::$site_hosts;
    }

    /**
     * 可在「本机」找到的文件清单（相对于资源根）
     *
     * 只列出插件真的会加载的文件：由 PHP 入队的，以及由前端脚本按需加载的。
     * 变动的部分（CodeMirror 主题、Prism 主题与插件）跟随当前设置。
     *
     * @return array
     */
    public static function candidates() {
        $list = array(
            "assets/Editormd/editormd.min.css",
            "assets/Editormd/editormd.min.js",
            "assets/Editormd/languages/en.js",
            "assets/Editormd/languages/zh-tw.js",
            "assets/Editormd/lib/modes.min.js",
            "assets/Editormd/lib/addons.min.js",
            "assets/Editormd/lib/prism.min.js",
            "assets/Config/editormd-nonce.js",
            "assets/Config/editormd.min.js",
            "assets/Turndown/turndown.js",
            "assets/Marked/marked.min.js",
            "assets/Mermaid/mermaid-compat.js",
            "assets/Mermaid/mermaid.min.js",
            "assets/MindMap/mindMap.min.js",
            "assets/CodeMirror/lib/codemirror.css",
            "assets/CodeMirror/lib/codemirror.js",
            "assets/CodeMirror/addon/dialog/dialog.css",
            "assets/CodeMirror/addon/fold/foldgutter.css",
            "assets/CodeMirror/addon/search/matchesonscrollbar.css",
            "assets/Prism.js/components/prism-core.min.js",
            "assets/Prism.js/plugins/autoloader/prism-autoloader.min.js",
            "assets/Prism.js/plugins/toolbar/prism-toolbar.min.js",
            "assets/Prism.js/plugins/toolbar/prism-toolbar.css",
            "assets/ClipBoard/clipboard.min.js",
            "assets/jQuery.Modal/jquery.modal.min.css",
            "assets/jQuery.Modal/jquery.modal.min.js",
            "assets/DomToImage/dist/dom-to-image.min.js",
            "assets/FileSaver/FileSaver.min.js",
        );

        // 前台样式（与 Main 里实例化 FrontStyle 的条件保持一致）
        if ("off" !== Config::get_option("open_in_new_tab", "editor_basics")) {
            $list[] = "assets/FrontStyle/FrontStyle.min.css";
            $list[] = "assets/FrontStyle/FrontStyle.min.js";
        }

        // CodeMirror 主题（跟随「编辑器编辑主题」）
        $code_style = (string) Config::get_option("code_style", "editor_style");

        if ("" !== $code_style && "default" !== $code_style) {
            $list[] = "assets/CodeMirror/theme/" . $code_style . ".css";
        }

        // KaTeX / Mermaid / Emoji / 思维导图（按开关决定）
        if ("katex" === Config::get_option("support_latex", "editor_latex")) {
            $list[] = "assets/KaTeX/katex.min.css";
            $list[] = "assets/KaTeX/katex.min.js";
        }

        if ("on" === Config::get_option("support_mermaid", "editor_mermaid")) {
            $list[] = "assets/Mermaid/mermaid.min.js";
        }

        if ("on" === Config::get_option("support_emoji", "editor_emoji")) {
            $list[] = "assets/Emojify.js/css/basic/emojify.min.css";
            $list[] = "assets/Emojify.js/js/emojify.min.js";
        }

        // Prism：主题 + 已启用的插件
        $prism_theme = (string) Config::get_option("highlight_library_style", "syntax_highlighting");

        if ("" === $prism_theme || "default" === $prism_theme) {
            $list[] = "assets/Prism.js/themes/prism.css";
        } elseif ("customize" !== $prism_theme) {
            $list[] = "assets/Prism.js/themes/prism-" . $prism_theme . ".css";
        }

        if ("on" === Config::get_option("line_numbers", "syntax_highlighting")) {
            // 编辑器预览侧还会自己去拉非压缩版（Editor.md 内置的加载器写死了文件名）
            $list[] = "assets/Prism.js/plugins/line-numbers/prism-line-numbers.js";
            $list[] = "assets/Prism.js/plugins/line-numbers/prism-line-numbers.min.js";
            $list[] = "assets/Prism.js/plugins/line-numbers/prism-line-numbers.css";
        }

        if ("on" === Config::get_option("show_language", "syntax_highlighting")) {
            $list[] = "assets/Prism.js/plugins/show-language/prism-show-language.min.js";
        }

        if ("on" === Config::get_option("copy_clipboard", "syntax_highlighting")) {
            $list[] = "assets/Prism.js/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js";
        }

        $list = array_values(array_unique($list));

        sort($list);

        return $list;
    }

    /**
     * 用本机文件生成哈希表文本（供设置页一键填入）
     *
     * @return array 两项：lines（文本）、skipped（本机缺失的文件）
     */
    public static function generate_from_local() {
        $lines   = array();
        $skipped = array();

        foreach (self::candidates() as $relative) {
            $file = WP_EDITORMD_PATH . "/" . $relative;

            if (! is_file($file) || ! is_readable($file)) {
                $skipped[] = $relative;
                continue;
            }

            $hash = @hash_file(self::HASH_ALGO, $file, true);

            if (false === $hash || "" === $hash) {
                $skipped[] = $relative;
                continue;
            }

            $lines[] = $relative . "=" . self::HASH_ALGO . "-" . base64_encode($hash);
        }

        return array(
            "lines"   => $lines,
            "skipped" => $skipped,
        );
    }

    /**
     * 设置页上的状态描述
     *
     * @param string $text_domain 翻译域
     *
     * @return string
     */
    public static function status_html($text_domain) {
        $count = count(self::map());
        $html  = "";

        if (! self::root_is_external()) {
            $html .= '<span class="updated">'
                . esc_html__("Current resource address is on this site, so integrity is not required.", $text_domain)
                . '</span> ';
        } elseif ($count > 0) {
            $html .= '<span class="updated">'
                . sprintf(
                    esc_html__("%d integrity entries configured. Resources on another host are verified by the browser, and any mismatch is refused.", $text_domain),
                    $count
                )
                . '</span> ';
        } else {
            $html .= '<span class="error">'
                . esc_html__("The resource address is on another host. Fill in the integrity values above.", $text_domain)
                . '</span> ';
        }

        $html .= esc_html__("One entry per line, format: path=sha384-xxxx. The path is relative to the resource address above; a full URL is also accepted.", $text_domain);

        return $html;
    }

    /**
     * 「别再无视它」的警告：非本站资源地址 + 没配置哈希
     *
     * @param string $text_domain 翻译域
     *
     * @return string
     */
    public static function warning_html($text_domain) {
        if (! self::root_is_external() || self::has_entries()) {
            return "";
        }

        $doc = "https://www.tjsky.net/tutorial/1536";

        $message = wp_kses(
            __("A wrong CDN setting, or a compromised CDN, may inject malicious code into the JavaScript and CSS it serves. Please set the <code>integrity</code> attribute — how to set it up:", $text_domain),
            array("code" => array())
        );

        return '<div class="editormd-integrity-warning">' . $message . ' <a href="' . esc_url($doc)
            . '" target="_blank" rel="noopener noreferrer">'
            . esc_html__("Documentation", $text_domain) . '</a></div>';
    }
}
