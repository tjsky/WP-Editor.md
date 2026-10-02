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

    private $static_file_ver = null;

    private static $field_types = array(
        "editor_basics"       => array(
            "task_list"           => "onoff",
            "imagepaste"          => "onoff",
            "imagepaste_sm"       => "onoff",
            "imagepaste_sm_token" => "text",
            "image_link"          => "onoff",
            "open_in_new_tab"     => "onoff",
            "live_preview"        => "onoff",
            "sync_scrolling"      => "onoff",
            "html_decode"         => "onoff",
            "support_front"       => "onoff",
            "support_reply"       => "onoff",
            "support_other_text"  => "text",
        ),
        "editor_style"        => array(
            "theme_style"   => "key",
            "code_style"    => "key",
            "editor_addres" => "url",
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

        foreach (array_keys(self::$field_types) as $section) {
            add_filter("pre_update_option_" . $section, array($this, "sanitize_section"), 10, 3);
        }

        add_action("admin_enqueue_scripts", array($this, "enqueue_settings_assets"));
    }

    public function enqueue_settings_assets() {
        if (! $this->is_settings_page()) {
            return;
        }

        $this->code_mirror_script();

        $base = Config::get_option("editor_addres", "editor_style");
        wp_enqueue_style("jQuery.Modal", $base . "/assets/jQuery.Modal/jquery.modal.min.css", array(), WP_EDITORMD_VER, "all");
        wp_enqueue_script("jQuery.Modal", $base . "/assets/jQuery.Modal/jquery.modal.min.js", array("jquery"), WP_EDITORMD_VER, true);
    }

    private function is_settings_page() {
        $page = isset($_GET["page"]) ? sanitize_key(wp_unslash($_GET["page"])) : "";

        return "wp-editormd-settings" === $page;
    }

    public function sanitize_section($value, $old_value, $option) {
        if (! is_array($value)) {
            return is_array($old_value) ? $old_value : array();
        }

        $allowed = isset(self::$field_types[$option]) ? self::$field_types[$option] : array();
        $old     = is_array($old_value) ? $old_value : array();
        $clean   = array();

        foreach ($value as $key => $item) {
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
                    $clean[$key] = sanitize_key((string) $item);
                    break;
                case "url":
                    $clean[$key] = esc_url_raw(trim((string) $item));
                    break;
                case "json":
                    $decoded = json_decode((string) $item, true);
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

        foreach ($old as $key => $item) {
            if (! array_key_exists($key, $clean) && array_key_exists($key, $allowed)) {
                $clean[$key] = $item;
            }
        }

        return $clean;
    }

    public static function default_static_address() {
        return WP_EDITORMD_URL;
    }

    public static function default_mindmap_address() {
        return WP_EDITORMD_URL . "/assets/MindMap/mindMap.min.js";
    }

    function admin_init() {
        //检查编辑器静态资源，如果是默认配置选项提前条件下，不符合最新版资源强制升级
        $style_option = get_option("editor_style");
        if (! is_array($style_option)) {
        // is_ssl 判断网站是否启用ssl不准确
            $style_option = array();
        }

        $addres = isset($style_option["editor_addres"]) ? (string) $style_option["editor_addres"] : "";

        if ("" !== $addres && preg_match("#cdn\.jsdelivr\.net#i", $addres)) {
            $style_option["editor_addres"] = self::default_static_address();
            update_option("editor_style", $style_option);
        }

        if (Config::get_option("editor_addres", "editor_style") === "") {
            $style_option["editor_addres"] = self::default_static_address();
            update_option("editor_style", $style_option);
        }

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

    private function get_static_file_ver() {
        if (null !== $this->static_file_ver) {
            return $this->static_file_ver;
        }

        $cached = get_transient("editormd_static_file_ver");
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

        set_transient("editormd_static_file_ver", $version, 12 * HOUR_IN_SECONDS);

        $this->static_file_ver = $version;

        return $version;
    }

    public function upgrade_editormd_file() {
        if ($this->get_static_file_ver() !== WP_EDITORMD_VER) {
            add_action("admin_notices", function () {
                $message = __("The resources used by the plugin check are outdated. Please upgrade the latest resources.", "editormd");
                printf('<div class="error"><p>%1$s</p></div>', esc_html($message));
            });

            return '<span class="error">' . esc_html__('Status: Please Update!', 'editormd') . '</span> '
                . '<a href="https://github.com/tjsky/WP-Editor.md/releases/latest" rel="noopener">' . esc_html__('Downaload', 'editormd') . '</a>';
        }

        return '<span class="updated">' . esc_html__('Status: Latest', 'editormd') . '</span>';
    }

    function get_settings_sections() {
        if ("0.0.0" === $this->get_static_file_ver()) {
            add_action("admin_notices", function () {
                $message = __("The resource package is corrupt, please download again!", "editormd");
                printf('<div class="error"><p>%1$s</p></div>', esc_html($message));
            });
        }

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
                    'name'    => 'imagepaste_sm',
                    'label'   => __('ImagePaste Upload Source', $this->text_domain),
                    'desc'    => __('Change image paste upload source to https://smms.app', $this->text_domain),
                    'type'    => 'checkbox',
                    'default' => 'off'
                ),
                array(
                    'name'    => 'imagepaste_sm_token',
                    'label'   => __('sm.ms Auth Token', $this->text_domain),
                    'desc'    => __('Optional, makes your uploaded image binded with your sm.ms account. Get token <a href="https://smms.app/home/apitoken" target="_blank">Here</a>.', $this->text_domain),
                    'type'    => 'text',
                    'default' => ''
                ),
                array(
                    'name'    => 'sm_library_management',
                    'label'   => __('sm.ms Image Management', $this->text_domain),
                    'desc'    => '<a href="javascript;" id="sm-ms-management">' . __('Go', $this->text_domain) . '</a>',
                    'type'    => 'html'
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
                    'type'    => 'checkbox',
                    'default' => 'off'
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
                    'name'    => 'editor_addres',
                    'label'   => __('Editor.md Static Resource Addres', $this->text_domain),
                    'desc'    => __('Please make sure the resources are up to date.<br/>' , $this->text_domain) . __('Please upload the resource (the unzipped folder name is "assets") to your server or cdn. If your resource address is: "http(s)://example.com/myfile/assets", you should fill in: "http(s)://example.com/myfile ". <br/>',$this->text_domain) . $this->upgrade_editormd_file(),
                    'type'    => 'text',
                    'default' => self::default_static_address()
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

    private function script_style() {
        $editor_style_base_address = Config::get_option("editor_addres", "editor_style");
        include __DIR__ . "/Settings/settings.css.php";
        include __DIR__ . "/Settings/settings.js.php";
    }
}
