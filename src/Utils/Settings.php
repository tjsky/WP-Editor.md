<?php

namespace EditormdUtils;

use \SettingsApi\SettingsApi as SettingsGo;
use EditormdUtils\Config;

class Settings {

    /**
     * @var string 插件名称
     */
    private $plugin_name;

    /**
     * @var string 插件版本号
     */
    private $version;

    /**
     * @var string 翻译文本域
     */
    protected $text_domain;

    private $settings_api;

    /**
     * @var string|null 静态资源包版本号（惰性加载）
     */
    private $static_file_ver = null;

    /**
     * @var array 各分组的字段类型表，用于统一净化写入值
     */
    private static $field_types = array(
        "editor_basics"       => array(
            "task_list"           => "onoff",
            "imagepaste"          => "onoff",
            "image_link"          => "onoff",
            "open_in_new_tab"     => "onoff",
            "live_preview"        => "onoff",
            "sync_scrolling"      => "onoff",
            "html_decode"         => "onoff",
            "support_front"       => "onoff",
            "support_reply"       => "onoff",
            "support_other_text"  => "text",
            "simple_comment_editor" => "onoff",
        ),
        "editor_style"        => array(
            "theme_style"       => "key",
            "code_style"        => "key",
            "front_style_sync"  => "onoff",
            "editor_addres"     => "url",
            "editor_integrity"  => "integrity",
        ),
        "syntax_highlighting" => array(
            "highlight_mode_auto"     => "onoff",
            "line_numbers"            => "onoff",
            "show_language"           => "onoff",
            "copy_clipboard"          => "onoff",
            "highlight_library_style" => "key",
            "customize_my_style"      => "text",
        ),
        "editor_emoji"        => array(
            "support_emoji" => "onoff",
        ),
        "editor_toc"          => array(
            "support_toc" => "onoff",
        ),
        "editor_latex"        => array(
            "support_latex" => "key",
        ),
        "editor_mermaid"      => array(
            "support_mermaid" => "onoff",
            "mermaid_config"  => "json",
        ),
        "editor_mindmap"      => array(
            "support_mindmap"   => "onoff",
            "customize_mindmap" => "url",
        ),
        "editor_advanced"     => array(
            "jquery_compatible" => "onoff",
            "hide_ads"          => "onoff",
        ),
        "editor_version"      => array(
            "wp_editormd_ver" => "version",
        ),
    );

    function __construct($plugin_name, $version, $text_domain) {
        $this->plugin_name = $plugin_name;
        $this->text_domain = $text_domain;
        $this->version     = $version;

        // 设置菜单生成器
        $this->settings_api = new SettingsGo;

        add_action("admin_init", array($this, "admin_init"));
        add_action("admin_menu", array($this, "admin_menu"));

        // 安全加固：统一净化写入值
        // 第三方设置库 SettingsApi::sanitize_options() 只会对「显式声明了
        // sanitize_callback 的字段」调用回调，未声明的一律原样入库；而本插件
        // 一个字段都没声明，等于净化完全失效。这里不依赖该机制，改为挂
        // pre_update_option_{section} 过滤器，对所有写入路径（设置页保存、
        // Config::update_option()、升级器）统一兜底。
        foreach (array_keys(self::$field_types) as $section) {
            add_filter("pre_update_option_" . $section, array($this, "sanitize_section"), 10, 3);
        }

        // 兼容性修复：原实现在构造函数（插件加载期，早于 admin_enqueue_scripts）
        // 直接 wp_enqueue_style/script，WordPress 6.3 起会触发 _doing_it_wrong 提示。
        add_action("admin_enqueue_scripts", array($this, "enqueue_settings_assets"));
    }

    /**
     * 加载设置页专用静态资源
     */
    public function enqueue_settings_assets() {
        if (! $this->is_settings_page()) {
            return;
        }

        $this->code_mirror_script();

        $base = Config::get_option("editor_addres", "editor_style");
        wp_enqueue_style("jQuery.Modal", $base . "/assets/jQuery.Modal/jquery.modal.min.css", array(), WP_EDITORMD_VER, "all");
        wp_enqueue_script("jQuery.Modal", $base . "/assets/jQuery.Modal/jquery.modal.min.js", array("jquery"), WP_EDITORMD_VER, true);
    }

    /**
     * 当前是否为本插件设置页
     *
     * @return bool
     */
    private function is_settings_page() {
        $page = isset($_GET["page"]) ? sanitize_key(wp_unslash($_GET["page"])) : "";

        return "wp-editormd-settings" === $page;
    }

    /**
     * 统一净化某一分组的写入值（pre_update_option_{$section} 回调）
     *
     * @param mixed  $value     新值
     * @param mixed  $old_value 旧值
     * @param string $option    分组名
     *
     * @return array
     */
    public function sanitize_section($value, $old_value, $option) {
        if (! is_array($value)) {
            return is_array($old_value) ? $old_value : array();
        }

        $allowed = isset(self::$field_types[$option]) ? self::$field_types[$option] : array();
        $old     = is_array($old_value) ? $old_value : array();
        $clean   = array();

        foreach ($value as $key => $item) {
            // 丢弃未在白名单中声明的键，避免选项注入
            if (! array_key_exists($key, $allowed)) {
                continue;
            }

            $type = $allowed[$key];
            $old_item = isset($old[$key]) ? $old[$key] : "";

            switch ($type) {
                case "onoff":
                    $clean[$key] = ("on" === $item) ? "on" : "off";
                    break;
                case "key":
                    // 枚举值：theme_style / code_style / highlight_library_style / support_latex
                    $clean[$key] = sanitize_key((string) $item);
                    break;
                case "url":
                    // 需兼容协议相对地址（//cdn.example.com/...），esc_url_raw 可正确处理
                    $clean[$key] = esc_url_raw(trim((string) $item));
                    break;
                case "integrity":
                    // 只保留「路径=sha384-xxx」形式的行，其余一律丢掉
                    $clean[$key] = AssetsIntegrity::sanitize_lines((string) $item);
                    break;
                case "json":
                    $decoded = json_decode((string) $item, true);
                    // JSON 非法时保留旧值，避免把坏配置写进去导致前端 mermaid.initialize() 报错
                    $clean[$key] = (JSON_ERROR_NONE === json_last_error()) ? (string) $item : (string) $old_item;
                    break;
                case "version":
                    $clean[$key] = preg_match("/^[0-9]{1,3}\.[0-9]{1,2}\.[0-9]{1,2}$/", (string) $item)
                        ? (string) $item
                        : (string) $old_item;
                    break;
                case "text":
                default:
                    $clean[$key] = sanitize_text_field((string) $item);
                    break;
            }
        }

        // 合并未提交的其它已知字段（例如设置页只提交当前分组的隐藏域）
        foreach ($old as $key => $item) {
            if (! array_key_exists($key, $clean) && array_key_exists($key, $allowed)) {
                $clean[$key] = $item;
            }
        }

        return $clean;
    }

    /**
     * 插件静态资源默认地址（插件自带本地资源）
     *
     * 调整说明：原实现默认从 jsdelivr CDN 加载编辑器的 JS/CSS，后台因此会向第三方
     * CDN 发起请求，存在供应链与隐私风险。现改为默认使用插件自带的本地资源。
     *
     * @return string
     */
    public static function default_static_address() {
        return WP_EDITORMD_URL;
    }

    /**
     * 思维导图库默认地址
     *
     * @return string
     */
    public static function default_mindmap_address() {
        return WP_EDITORMD_URL . "/assets/MindMap/mindMap.min.js";
    }

    function admin_init() {
        // 资源地址校正与一次性迁移
        $style_option = get_option("editor_style");
        if (! is_array($style_option)) {
            // 兼容性修复：原实现直接 $option["editor_addres"] 取值，
            // 选项缺失时会触发 PHP 8 的 Undefined array key 与 preg_match(null) 弃用告警
            $style_option = array();
        }

        $addres = isset($style_option["editor_addres"]) ? (string) $style_option["editor_addres"] : "";

        // 历史配置里若指向 jsdelivr CDN，一次性迁移为本地资源
        if ("" !== $addres && preg_match("#cdn\.jsdelivr\.net#i", $addres)) {
            $style_option["editor_addres"] = self::default_static_address();
            update_option("editor_style", $style_option);
        }

        // 空值填入默认（本地）地址 - 编辑器静态地址
        if (Config::get_option("editor_addres", "editor_style") === "") {
            $style_option["editor_addres"] = self::default_static_address();
            update_option("editor_style", $style_option);
        }

        // 空值填入默认（本地）地址 - 思维导图
        //
        // 数据损坏修复：原实现复用了上面取自 editor_style 的 $option 变量，
        // 随后 update_option("editor_mindmap", $option) —— 这会把 editor_style 的
        // 整个数组写进 editor_mindmap，导致 support_mindmap 等键丢失、思维导图功能失效。
        // 现改用独立的 $mindmap_option。
        $mindmap_option = get_option("editor_mindmap");
        if (! is_array($mindmap_option)) {
            $mindmap_option = array();
        }

        $mindmap_address = isset($mindmap_option["customize_mindmap"]) ? (string) $mindmap_option["customize_mindmap"] : "";
        if ("" !== $mindmap_address && preg_match("#cdn\.jsdelivr\.net#i", $mindmap_address)) {
            $mindmap_option["customize_mindmap"] = self::default_mindmap_address();
            update_option("editor_mindmap", $mindmap_option);
        }

        if (Config::get_option("customize_mindmap", "editor_mindmap") === "") {
            $mindmap_option["customize_mindmap"] = self::default_mindmap_address();
            update_option("editor_mindmap", $mindmap_option);
        }

        //set the settings
        $this->settings_api->set_sections($this->get_settings_sections());
        $this->settings_api->set_fields($this->get_settings_fields());

        //initialize settings
        $this->settings_api->admin_init();
    }

    function admin_menu() {
        add_options_page($this->plugin_name . __(" Options", $this->text_domain), $this->plugin_name, "manage_options", "wp-editormd-settings", array($this, "plugin_page"));
    }

    function code_mirror_script() {
        wp_enqueue_script("code-editor");
        wp_enqueue_style("code-editor");

        $settings = wp_enqueue_code_editor(array(
            "type" => "json",
            'codemirror' => array(
                'autoRefresh' => true
              )
        ));

        // 系统禁用CodeMirror
        if (false === $settings) {
            return;
        }

        wp_add_inline_script(
            "code-editor",
            sprintf(
                'jQuery(function() { jQuery("#editor_mermaid\\\\[mermaid_config\\\\]").length !== 0 ? wp.codeEditor.initialize("editor_mermaid\\\\[mermaid_config\\\\]", %s) : ""; });',
                wp_json_encode($settings)
            )
        );

        wp_add_inline_script(
            "wp-codemirror",
            "window.CodeMirror = wp.CodeMirror;"
        );
    }

    /**
     * 读取远程文本内容
     *
     * 安全修复：原实现用 @file_get_contents() 读取，失败后再用 curl 兜底，且 URL 完全
     * 由管理员选项 editor_addres 拼接而成 —— file_get_contents 对 file:// 等非 http 协议
     * 同样有效，因此构成盲 SSRF / 本地文件读取面；同时该方法在 admin_init 中被同步调用，
     * 每个后台请求（含 admin-ajax.php）最坏要阻塞 3 秒 × 2 次。
     *
     * 现改为：仅允许 http/https、使用 WordPress 官方 HTTP API（自带超时与证书校验）、
     * 且结果由调用方通过 transient 缓存。
     *
     * @param string $url
     *
     * @return string
     */
    function file_get_content($url) {
        if (! is_string($url) || "" === $url) {
            return "";
        }

        $parts = wp_parse_url($url);
        if (empty($parts["scheme"]) || ! in_array(strtolower($parts["scheme"]), array("http", "https"), true)) {
            return "";
        }

        $response = wp_remote_get($url, array(
            "timeout"     => 5,
            "redirection" => 3,
            "sslverify"   => true,
        ));

        if (is_wp_error($response)) {
            return "";
        }

        return (string) wp_remote_retrieve_body($response);
    }

    /**
     * 获取静态资源包版本号
     *
     * 优先读取插件自带的本机文件，避免任何网络往返；仅在配置指向外部地址时
     * 才发起一次远程请求，并用 transient 缓存 12 小时。
     *
     * @return string
     */
    private function get_static_file_ver() {
        if (null !== $this->static_file_ver) {
            return $this->static_file_ver;
        }

        // 缓存键带上插件版本：升级插件后旧缓存自然失效，不会继续拿上一个资源包
        // 的版本去和新插件比 —— 否则升级后最长 12 小时内仍会误报「资源包过时」。
        // （旧键不带版本，由 Upgrader 在 10.5.0 迁移里清一次。）
        $cache_key = "editormd_static_file_ver_" . $this->version;

        $cached = get_transient($cache_key);
        if (false !== $cached && is_string($cached) && "" !== $cached) {
            $this->static_file_ver = $cached;

            return $this->static_file_ver;
        }

        $version  = "0.0.0";
        $localFile = WP_EDITORMD_PATH . "/assets/version.json";

        if (file_exists($localFile)) {
            $editormd = json_decode((string) file_get_contents($localFile), true);
        } else {
            $address  = (string) Config::get_option("editor_addres", "editor_style");
            $editormd = json_decode($this->file_get_content(trailingslashit($address) . "assets/version.json"), true);
        }

        if (is_array($editormd) && ! empty($editormd["version"]) && is_scalar($editormd["version"])) {
            $version = (string) $editormd["version"];
        }

        set_transient($cache_key, $version, 12 * HOUR_IN_SECONDS);

        $this->static_file_ver = $version;

        return $version;
    }

    /**
     * 返回资源版本状态
     *
     * 兼容性修复：原实现是定义在 get_settings_sections() 方法体内的嵌套函数
     * function upgradeEditormdFile()，方法被调用两次就会 "Cannot redeclare function" 致命错误；
     * 同时用 define() 声明 WP_EDITORMD_STATIC_FILE_VER，重复调用也会告警。
     * 现改为类方法 + 惰性取值。
     *
     * @return string
     */
    public function upgrade_editormd_file() {
        $package = $this->get_static_file_ver();

        // 资源包缺失 / 读不到：本地 assets/version.json 不存在，且配置的外部地址也不可达
        if ("0.0.0" === $package) {
            return '<span class="error">'
                . esc_html__("Status: Resource package missing or corrupt!", "editormd")
                . '</span> '
                . esc_html__("Cannot read assets/version.json — the resource package may not have been uploaded completely.", "editormd");
        }

        if ($package !== WP_EDITORMD_VER) {
            return '<span class="error">'
                . esc_html__("Status: Please Update!", "editormd")
                . '</span> '
                . sprintf(
                    /* translators: 1: resource package version, 2: plugin version */
                    esc_html__("Resource package version %1\$s does not match plugin version %2\$s.", "editormd"),
                    esc_html($package),
                    esc_html(WP_EDITORMD_VER)
                )
                . ' <a href="https://github.com/tjsky/WP-Editor.md/releases/latest" rel="noopener">'
                . esc_html__("Downaload", "editormd")
                . '</a>';
        }

        return '<span class="updated">' . esc_html__("Status: Latest", "editormd") . '</span>';
    }

    function get_settings_sections() {
        // 资源包状态（缺失 / 版本不一致）一律只在插件设置页显示 —— 由
        // upgrade_editormd_file() 输出在 editor_addres 字段的 desc 里。
        //
        // 这里曾用 add_action("admin_notices") 弹后台通告，但 get_settings_sections()
        // 与 get_settings_fields() 都是在 admin_init 阶段被调用的，而 WordPress
        // 后台的每个页面（写文章、审核评论、仪表盘…）都会跑 admin_init ——
        // 于是「设置页里的一行状态」被放大成了全后台红框提示。

        $sections = array(
            array(
                "id"    => "editor_basics",
                "title" => __("Basic Settings", $this->text_domain)
            ),
            array(
                "id"    => "editor_style",
                "title" => __("Editor Style Settings", $this->text_domain)
            ),
            array(
                "id"    => "syntax_highlighting",
                "title" => __("Syntax Highlighting Settings", $this->text_domain)
            ),
            array(
                "id"    => "editor_emoji",
                "title" => __("Emoji Settings", $this->text_domain)
            ),
            array(
                "id"    => "editor_toc",
                "title" => __("TOC Settings", $this->text_domain)
            ),
            array(
                "id"    => "editor_latex",
                "title" => __("KaTeX Settings", $this->text_domain)
            ),
            array(
                "id"    => "editor_mermaid",
                "title" => __("Mermaid Settings", $this->text_domain)
            ),
            array(
                "id"    => "editor_mindmap",
                "title" => __("MindMap Settings", $this->text_domain)
            ),
            array(
                "id"    => "editor_advanced",
                "title" => __("Advanced Settings", $this->text_domain)
            ),
        );

        return $sections;
    }

    /**
     * Returns all the settings fields
     *
     * @return array settings fields
     */
    function get_settings_fields() {
        $mermaidConfig = '{
    "theme": "dark",
    "logLevel": 5,
    "arrowMarkerAbsolute": false,
    "startOnLoad": true,
    "flowchart": {
        "htmlLabels": true,
        "curve": "linear"
    },
    "sequence": {
        "diagramMarginX": 50,
        "diagramMarginY": 10,
        "actorMargin": 50,
        "width": 150,
        "height": 65,
        "boxMargin": 10,
        "boxTextMargin": 5,
        "noteMargin": 10,
        "messageMargin": 35,
        "mirrorActors": true,
        "bottomMarginAdj": 1,
        "useMaxWidth": true
    },
    "gantt": {
        "titleTopMargin": 25,
        "barHeight": 20,
        "barGap": 4,
        "topPadding": 50,
        "leftPadding": 75,
        "gridLineStartPadding": 35,
        "fontSize": 11,
        "fontFamily": "\"Open-Sans\", \"sans-serif\"",
        "numberSectionStyles": 4,
        "axisFormat": "%Y-%m-%d"
    },
    "class": {},
    "git": {}
}';
        $settings_fields = array(
            'editor_basics'       => array(
                array(
                    'name'  => 'support_posts_pages',
                    'label' => __('Use Markdown For Posts And Pages', $this->text_domain),
                    'desc'  => '<a href="' . admin_url("options-writing.php") . '" target="_blank">' . __('Go', $this->text_domain) . '</a>',
                    'type'  => 'html'
                ),
                array(
                    'name'  => 'support_comment',
                    'label' => __('Use Markdown For Comments', $this->text_domain),
                    'desc'  => '<a href="' . admin_url("options-discussion.php#wpcom_publish_comments_with_markdown") . '" target="_blank">' . __('Go', $this->text_domain) . '</a>',
                    'type'  => 'html'
                ),
                array(
                    'name'    => 'task_list',
                    'label'   => __('Support Task Lists', $this->text_domain),
                    'desc'    => __('Github Flavored Markdown task lists', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'imagepaste',
                    'label'   => __('Support Image Paste', $this->text_domain),
                    'desc'    => __('Image Paste allows you to copy and paste images from your desktop to the editor, maybe won\'t work on some browsers due to incompatibility. Reference: <a href="https://github.com/LuRenJiasWorld/WP-Editor.md/pull/386" target="_blank">PR #386</a>', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'image_link',
                    'label'   => __('Image Hyperlink', $this->text_domain),
                    'desc'    => __('Support upload image hyperlink', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'open_in_new_tab',
                    'label'   => __('Open link in new tab', $this->text_domain),
                    'desc'    => __('Only works for new posts after enabled, or you can manually update every posts', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'on'
                ),
                array(
                    'name'    => 'live_preview',
                    'label'   => __('Live preview', $this->text_domain),
                    'desc'    => __('', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'sync_scrolling',
                    'label'   => __('Sync scrolling', $this->text_domain),
                    'desc'    => __('', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'html_decode',
                    'label'   => __('Support Html Decode', $this->text_domain),
                    'desc'    => __('Support rich text analysis', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'support_front',
                    'label'   => __('Support Front Comment', $this->text_domain),
                    'desc'    => __('Currently confirmed to be fully compatible with the WordPress default themes. If it fails to load, please report it as soon as possible.', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'simple_comment_editor',
                    'label'   => __('Use Simple Editor For Front Comments', $this->text_domain),
                    'desc'    => __('Offers only bold, italic, strikethrough, blockquote, inline code, images and links in the visitor comment box — links and images are not clickable. The editor starts in single-column mode with a trimmed toolbar.', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'  => 'comment_syntax_notice',
                    'label' => __('Comment Syntax Limitation', $this->text_domain),
                    'desc'  => __('Comment syntax is limited by the WordPress KSES whitelist for untrusted content. Whether or not the simple editor is enabled, images, tables, headings, lists and horizontal rules may not display correctly in comments — some are stripped outright. The currently effective whitelist is listed under “Advanced Settings → Debugger”.', $this->text_domain),
                    'type'  => 'html'
                ),
                array(
                    'name'    => 'support_reply',
                    'label'   => __('Support Reply Comment For edit-comments.php', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'support_other_text',
                    'label'   => __('Support Other (Document ID)', $this->text_domain),
                    'desc'    => __('', $this->text_domain),
                    'type'    => 'text',
                    'default' => ''
                )
            ),
            'editor_style'        => array(
                array(
                    'name'    => 'theme_style',
                    'label'   => __('Toolbar & Preview Style', $this->text_domain),
                    'desc'    => __('Will not affect the Markdown editor window', $this->text_domain),
                    'type'    => 'select',
                    'options' => array(
                        'default' => __('default', $this->text_domain),
                        'dark'    => __('dark', $this->text_domain)
                    ),
                    'default' => 'default'
                ),
                array(
                    'name'    => 'code_style',
                    'label'   => __('Markdown Editor Style', $this->text_domain),
                    'desc'    => __('Change the markdown editor style', $this->text_domain),
                    'type'    => 'select',
                    'options' => array(
                        'default'                 => 'default',
                        '3024-day'                => '3024-day',
                        '3024-night'              => '3024-night',
                        'abcdef'                  => 'abcdef',
                        'ambiance'                => 'ambiance',
                        'ambiance-mobile'         => 'ambiance-mobile',
                        'base16-dark'             => 'base16-dark',
                        'base16-light'            => 'base16-light',
                        'bespin'                  => 'bespin',
                        'blackboard'              => 'blackboard',
                        'cobalt'                  => 'cobalt',
                        'colorforth'              => 'colorforth',
                        'dracula'                 => 'dracula',
                        'duotone-dark'            => 'duotone-dark',
                        'duotone-light'           => 'duotone-light',
                        'eclipse'                 => 'eclipse',
                        'elegant'                 => 'elegant',
                        'erlang-dark'             => 'erlang-dark',
                        'gruvbox-dark'            => 'gruvbox-dark',
                        'hopscotch'               => 'hopscotch',
                        'icecoder'                => 'icecoder',
                        'idea'                    => 'idea',
                        'isotope'                 => 'isotope',
                        'lesser-dark'             => 'lesser-dark',
                        'liquibyte'               => 'liquibyte',
                        'lucario'                 => 'lucario',
                        'material'                => 'material',
                        'mbo'                     => 'mbo',
                        'mdn-like'                => 'mdn-like',
                        'midnight'                => 'midnight',
                        'monokai'                 => 'monokai',
                        'neat'                    => 'neat',
                        'neo'                     => 'neo',
                        'night'                   => 'night',
                        'oceanic-next'            => 'oceanic-next',
                        'panda-syntax'            => 'panda-syntax',
                        'paraiso-dark'            => 'paraiso-dark',
                        'paraiso-light'           => 'paraiso-light',
                        'pastel-on-dark'          => 'pastel-on-dark',
                        'railscasts'              => 'railscasts',
                        'rubyblue'                => 'rubyblue',
                        'seti'                    => 'seti',
                        'shadowfox'               => 'shadowfox',
                        'solarized'               => 'solarized',
                        'ssms'                    => 'ssms',
                        'the-matrix'              => 'the-matrix',
                        'tomorrow-night-bright'   => 'tomorrow-night-bright',
                        'tomorrow-night-eighties' => 'tomorrow-night-eighties',
                        'ttcn'                    => 'ttcn',
                        'twilight'                => 'twilight',
                        'vibrant-ink'             => 'vibrant-ink',
                        'xq-dark'                 => 'xq-dark',
                        'xq-light'                => 'xq-light',
                        'yeti'                    => 'yeti',
                        'zenburn'                 => 'zenburn'
                    ),
                    'default' => 'default'
                ),
                array(
                    'name'    => 'style_preview',
                    'label'   => __('Editor Style Preview', $this->text_domain),
                    'desc'    => '
                                    <div id="style-preview-container">
                                        <img 
                                            src="https://github.com/LuRenJiasWorld/WP-Editor.md-Image-Resource/raw/master/editor-preview/blank.png" 
                                            id="style-preview-frame" 
                                        />
                                        <img 
                                            src="https://github.com/LuRenJiasWorld/WP-Editor.md-Image-Resource/raw/master/editor-markdown/blank.png"
                                            id="style-preview-editor" 
                                        />
                                    </div>
                                 ',
                    'type'    => 'html'
                ),
                array(
                    'name'    => 'front_style_sync',
                    'label'   => __('Apply The Styles Above To The Front-end Editor', $this->text_domain),
                    'desc'    => __('Off (default): the front-end comment editor keeps the default light style and follows your theme — write your own CSS if you need e.g. a dark mode. On: the two styles above apply to the front-end editor as well. The admin editor is never affected by this switch.', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'editor_addres',
                    'label'   => __('Editor.md Static Resource Addres', $this->text_domain),
                    'desc'    => __('Please make sure the resources are up to date.<br/>' , $this->text_domain) . __('Please upload the resource (the unzipped folder name is "assets") to your server or cdn. If your resource address is: "http(s)://example.com/myfile/assets", you should fill in: "http(s)://example.com/myfile ". <br/>',$this->text_domain) . $this->upgrade_editormd_file() . AssetsIntegrity::warning_html($this->text_domain),
                    'type'    => 'text',
                    // 默认使用插件自带的本地资源（原默认值为 jsdelivr CDN 地址）
                    'default' => self::default_static_address()
                ),
                array(
                    'name'    => 'editor_integrity',
                    'label'   => __('Static Resource Integrity (integrity)', $this->text_domain),
                    'desc'    => AssetsIntegrity::status_html($this->text_domain) . $this->integrity_tools_html(),
                    'type'    => 'textarea',
                    'default' => ''
                ),
            ),
            'syntax_highlighting' => array(
                array(
                    'name'    => 'highlight_mode_auto',
                    'label'   => __('Auto load mode', $this->text_domain),
                    'desc'    => __('', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'line_numbers',
                    'label'   => __('Line Numbers', $this->text_domain),
                    'desc'    => __('', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'show_language',
                    'label'   => __('Show Language', $this->text_domain),
                    'desc'    => __('', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'copy_clipboard',
                    'label'   => __('Copy to Clipboard', $this->text_domain),
                    'desc'    => __('', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'highlight_library_style',
                    'label'   => __('PrismJS Syntax Highlight Style', $this->text_domain),
                    'desc'    => __('Syntax highlight theme style', $this->text_domain),
                    'type'    => 'select',
                    'options' => array(
                        'default'        => 'Default',
                        'dark'           => 'Dark',
                        'funky'          => 'Funky',
                        'okaidia'        => 'Okaidia',
                        'twilight'       => 'Twilight',
                        'coy'            => 'Coy',
                        'solarizedlight' => 'Solarized Light',
                        'tomorrow'       => 'Tomorrow Night',
                        'customize'       => __('Customize Style', $this->text_domain),
                    ),
                    'default' => 'default'
                ),
                array(
                    'name'    => 'highlight_preview',
                    'label'   => __('Syntax Highlight Preview', $this->text_domain),
                    'desc'    => '
                                    <div id="highlight-preview-container">
                                        <img 
                                            src="https://github.com/LuRenJiasWorld/WP-Editor.md-Image-Resource/raw/master/editor-highlight/blank.png" 
                                            id="highlight-preview-frame" 
                                        />
                                    </div>
                                 ',
                    'type'    => 'html'
                ),
                array(
                    'name'    => 'customize_my_style',
                    'label'   => __('Customize Style Library', $this->text_domain),
                    'desc'    => __('Get More <a href="https://github.com/LuRenJiasWorld/Prism.js-style" target="_blank" rel="nofollow">Theme Style</a>', $this->text_domain),
                    'type'    => 'text',
                    'default' => 'notiong'
                )
            ),
            'editor_emoji'        => array(
                array(
                    'name'    => 'support_emoji',
                    'label'   => __('Support Emoji', $this->text_domain),
                    'desc'    => __('', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                )
            ),
            'editor_toc'          => array(
                array(
                    'name'    => 'support_toc',
                    'label'   => __('Support ToC', $this->text_domain),
                    'desc'    => __('Table of Contents', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'  => 'toc_tips',
                    'label' => __('You need install the plugin', $this->text_domain),
                    'desc'  => '<a class="toc_tips" href="' . admin_url("plugin-install.php?tab=plugin-information&plugin=table-of-contents-plus&TB_iframe=true ") . '" rel="nofollow" target="_blank">' . __('If you need to enable this option,you need install the plugin', $this->text_domain) . '</a>',
                    'type'  => 'html'
                )
            ),
            'editor_latex'        => array(
                array(
                    'name'    => 'support_latex',
                    'label'   => __('Support LaTeX', $this->text_domain),
                    'desc'    => __('LaTeX Support Library', $this->text_domain),
                    'type'    => 'select',
                    'options' => array(
                        'katex'   => 'KaTeX',
                        'disable' => __('Disable', $this->text_domain)
                   ),
                    'default' => 'disable'
               ),
            ),
            'editor_mermaid'      => array(
                array(
                    'name'    => 'support_mermaid',
                    'label'   => __('Support Mermaid', $this->text_domain),
                    'desc'    => __('Support FlowChart,SequenceDiagram and GantDiagrams', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'mermaid_config',
                    'label'   => __('Mermaid Config', $this->text_domain),
                    'desc'    => __('More info: <a rel="nofollow" target="_blank" href="https://mermaidjs.github.io/mermaidAPI.html">MermaidAPI Doc</a> and <a href="https://github.com/knsv/mermaid/blob/master/src/mermaidAPI.js" target="_blank" rel="nofollow">MermaidAPI.js</a>', $this->text_domain),
                    'type'    => 'textarea',
                    'default' => $mermaidConfig
               )
           ),
            'editor_mindmap'      => array(
                array(
                    'name'    => 'support_mindmap',
                    'label'   => __('Support MindMap', $this->text_domain),
                    'desc'    => __('', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'customize_mindmap',
                    'label'   => __('Customize MindMap Library', $this->text_domain),
                    'type'    => 'text',
                    'default' => self::default_mindmap_address()
                ),
           ),
            'editor_advanced'     => array(
                array(
                    'name'    => 'jquery_compatible',
                    'label'   => __('Compatibility Mode', $this->text_domain),
                    'desc'    => __('Enable WordPress\'s own jQuery library and load first, will fix many compatibility issues.', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'  => 'debugger',
                    'label' => __('Debugger', $this->text_domain),
                    'desc'  => '<a id="debugger" href="#">' . __('Info', $this->text_domain) . '</a>',
                    'type'  => 'html'
                ),
                array(
                    'name'  => 'hide_ads',
                    'label'   => __('Hide Ads', $this->text_domain),
                    'desc'    => __('', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
               ),
            ),
        );

        return $settings_fields;
    }

    function plugin_page() {
        echo '<div class="wrap">';

        $this->settings_api->show_navigation();

        echo '<div class="form-and-donate">';

        $this->settings_api->show_forms();

        echo Debugger::editormd_debug($this->text_domain);

        if(Config::get_option("hide_ads","editor_advanced") == "off") {
            $donateImgUrl = "//static.lurenjia.in/WP%20Editor.md";
            
            echo '<div id="donate">';
            echo '<h3>' . __('Donate', $this->text_domain) . '</h3>';
            echo '<p>' . __('It is hard to continue development and support for this plugin without contributions from users like you. If you enjoy using WP-Editor.md and find it useful, please consider making a donation. Your donation will help encourage and support the plugin’s continued development and better user support.Thank You!', $this->text_domain) . '</p>';
            echo '<p style="display: table;"><strong style="display: table-cell;vertical-align: middle;">Alipay(支付宝)：</strong><a rel="nofollow" target="_blank" href="'. $donateImgUrl .'/支付宝.png"><img width="160" height="160" src="'. $donateImgUrl .'/支付宝.png"/></a></p>';
            echo '<p style="display: table;"><strong style="display: table-cell;vertical-align: middle;">WeChat(微信)：</strong><a rel="nofollow" target="_blank" href="'. $donateImgUrl .'/微信赞赏.png"><img width="160" height="160" src="'. $donateImgUrl .'/微信赞赏.png"/></a></p>';
            echo '<p style="display: table;"><strong style="display: table-cell;vertical-align: middle;">PayPal(贝宝)：</strong><a rel="nofollow" target="_blank" href="https://www.paypal.me/lurenjia">https://www.paypal.me/lurenjia</a></p>';
            // 二次开发版（本 fork）作者的说明。本条不含收款方式，只是把用户引向博客，
            // 因此使用行内链接 —— 类名用于覆盖上面 #donate a{display:block} 的规则。
            echo '<p class="editormd-fork-note">' . __('I am the author of this modified / forked version. Compared with the original author\'s contribution, all I did was some minor fixes and small tweaks with little technical merit, so I did not add my own donation info. If you enjoy this modified WP-Editor.md, it would be great if you could visit my blog <a href="https://www.tjsky.net" target="_blank" rel="noopener">秋风于渭水</a> when you have time. Thank you!', $this->text_domain) . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';

        $this->script_style();
    }

    /**
     * Get all the pages
     *
     * @return array page names with key value pairs
     */
    function get_pages() {
        $pages         = get_pages();
        $pages_options = array();
        if ($pages) {
            foreach ($pages as $page) {
                $pages_options[$page->ID] = $page->post_title;
            }
        }

        return $pages_options;
    }

    /**
     * 「用本机文件生成」按钮 + 隐藏的哈希清单
     *
     * 清单直接渲染进页面，点按钮时由前端脚本灌进设置项。比再加一个 AJAX 接口简单，
     * 也少一处需要鉴权的入口。
     *
     * @return string
     */
    private function integrity_tools_html() {
        $generated = AssetsIntegrity::generate_from_local();

        if (empty($generated["lines"])) {
            return "";
        }

        $html = '<div class="editormd-integrity-tools">'
            . '<button type="button" class="button" id="editormd-integrity-fill">'
            . esc_html__("Fill in with the local files", $this->text_domain)
            . '</button>'
            . '<span id="editormd-integrity-fill-note"></span>'
            . '</div>';

        if (! empty($generated["skipped"])) {
            $html .= '<div class="editormd-integrity-note">'
                . esc_html__("Not found locally, so skipped:", $this->text_domain)
                . ' ' . esc_html(implode(", ", $generated["skipped"]))
                . '</div>';
        }

        $html .= '<textarea id="editormd-integrity-source" class="editormd-integrity-source" readonly="readonly" aria-hidden="true" tabindex="-1">'
            . esc_textarea(implode("\n", $generated["lines"]))
            . '</textarea>';

        return $html;
    }

    private function script_style() {
        $editor_style_base_address = Config::get_option("editor_addres", "editor_style");
        // 兼容性修复：原先使用相对路径 include("Settings/...")，依赖 include_path/CWD 解析，比较脆弱
        include __DIR__ . "/Settings/settings.css.php";
        include __DIR__ . "/Settings/settings.js.php";
    }
}
