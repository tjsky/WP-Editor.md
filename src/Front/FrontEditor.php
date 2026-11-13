<?php

namespace EditormdFront;

use EditormdApp\PrismJSAuto;
use EditormdUtils\Config;
use EditormdUtils\AssetsIntegrity;
use EditormdUtils\Settings;

class FrontEditor {
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
    private $text_domain;

    /**
     * @var string 静态资源地址
     */
    private $front_static_url;

    /**
     * Controller constructor 初始化类并设置其属性
     *
     * @param $plugin_name
     * @param $version
     * @param $ioption
     */
    public function __construct() {
        $this->plugin_name      = "WP Editor.md";
        $this->text_domain      = "editormd";
        $this->version          = WP_EDITORMD_VER;
        $this->front_static_url = Config::get_option("editor_addres", "editor_style");

        // 兜底：站点装好后若还没进过后台，editor_style 里还没有这个键，
        // 取出来是空串 —— 拼出来的就是「/assets/...」这种相对地址（指向站点根，
        // 必然 404）。此时应当退回默认地址（本地资源或维护者 CDN）。
        if ("" === $this->front_static_url) {
            $this->front_static_url = Settings::default_static_address();
        }

        add_action("wp_enqueue_scripts", array($this, "enqueue_front_styles"));
        add_action("wp_enqueue_scripts", array($this, "enqueue_front_scripts"));
    }

    /**
     * 判断当前请求是否真的需要加载前端编辑器资源
     *
     * 兼容性/性能修复：原实现只要本类被实例化，就会在**每一个**前台页面
     * 无条件加载整套编辑器 CSS/JS（数百 KB）。
     * 现改为按需加载：
     *   - 开启「前端评论」时：仅在单篇内容页且评论开放时加载；
     *   - 配置了「其它编辑器目标元素 ID」时：保持全站加载（目标元素可能出现在任何页面，
     *     无法在服务端可靠预判）。
     * 如需恢复旧的「全站加载」行为，可用过滤器强制返回 true：
     *   add_filter("editormd_front_editor_needed", "__return_true");
     *
     * @return bool
     */
    private function front_editor_needed() {
        $needed = false;

        if (Config::get_option("support_front", "editor_basics") === "on") {
            if (is_singular() && comments_open()) {
                $needed = true;
            }
        }

        if (Config::get_option("support_other_text", "editor_basics") !== "") {
            $needed = true;
        }

        return (bool) apply_filters("editormd_front_editor_needed", $needed);
    }

    /**
     * 注册样式文件
     */
    public function enqueue_front_styles() {
        if (! $this->front_editor_needed()) {
            return;
        }

        //Style - Editor.md
        wp_enqueue_style("Editormd_Front", $this->front_static_url . "/assets/Editormd/editormd.min.css", array(), WP_EDITORMD_VER, "all");
    }

    /**
     * 注册脚本文件
     */
    public function enqueue_front_scripts() {
        if (! $this->front_editor_needed()) {
            return;
        }

        // jQuery 统一使用 WordPress 自带版本。
        // 安全修复：原「非兼容模式」会 wp_deregister_script("jquery") 并改为加载 jQuery 1.12.4，
        // 该版本存在已知 XSS 漏洞（如 CVE-2015-9251），且会破坏依赖 core jQuery 的其它插件，故移除该分支。
        wp_enqueue_script("jquery");
        wp_enqueue_script("Editormd_Front", $this->front_static_url . "/assets/Editormd/editormd.min.js", array("jquery"), WP_EDITORMD_VER, true);

        //JavaScript - AJAX nonce 注入垫片（免构建，见 assets/Config/editormd-nonce.js）
        wp_enqueue_script("Editormd-Nonce_Front", $this->front_static_url . "/assets/Config/editormd-nonce.js", array("jquery"), WP_EDITORMD_VER, true);
        wp_localize_script("Editormd-Nonce_Front", "_EditormdNonce", array(
            "imagepasteNonce" => wp_create_nonce("wp_editormd_imagepaste"),
            "pagesNonce"      => wp_create_nonce("wp_editormd_pages"),
        ));

        //JavaScript - Config
        wp_enqueue_script("Config_Front", $this->front_static_url . "/assets/Config/editormd.min.js", array("Editormd_Front", "Editormd-Nonce_Front"), $this->version, true);

        //JavaScript - Mermaid 10 兼容垫片（见 assets/Mermaid/mermaid-compat.js 的说明）
        wp_enqueue_script("Mermaid-Compat_Front", $this->front_static_url . "/assets/Mermaid/mermaid-compat.js", array(), WP_EDITORMD_VER, true);

        //JavaScript/CSS - Prism
        //
        // 统一 Prism（10.4.4）：编辑器预览会调用 Prism.highlightAll()。
        // 「自动高亮」选项关闭时前台本来一份 Prism 都没有，此前靠 Editor.md 动态加载它
        // 自带的 lib/prism.min.js 兜底 —— 那个文件现在只是占位，所以这里显式补齐。
        // 若「自动高亮」已开启，PrismJSAuto 会先一步入队，本调用因幂等直接返回。
        PrismJSAuto::enqueue_assets();

        //JavaScript - 载入国际化语言资源文件
        $lang = get_bloginfo("language");
        switch ($lang) {
            case "zh-TW":
                wp_enqueue_script("Editormd-lang-tw_Front", $this->front_static_url . "/assets/Editormd/languages/zh-tw.js", array(), WP_EDITORMD_VER, true);
                break;
            case "zh-CN":
                break;
            case "en-US":
                wp_enqueue_script("Editormd-lang-us_Front", $this->front_static_url . "/assets/Editormd/languages/en.js", array(), WP_EDITORMD_VER, true);
                break;
            default:
                wp_enqueue_script("Editormd-lang-us_Front", $this->front_static_url . "/assets/Editormd/languages/en.js", array(), WP_EDITORMD_VER, true);
        }


        if (Config::get_option("highlight_library_style", "syntax_highlighting") == "customize") {
            $prismTheme = Config::get_option("customize_my_style"     , "syntax_highlighting");
        } else {
            $prismTheme = Config::get_option("highlight_library_style", "syntax_highlighting");
        }

        // 前台编辑器风格（10.5.0 起）：
        //   默认传 default，即 Editor.md 自带的浅色主题 —— 前台评论框应当跟随
        //   主题走，用户要适配夜间模式请自行写 CSS；只有打开
        //   editor_style → front_style_sync 开关时，才沿用后台的两项风格设置。
        //   后台编辑器（Admin\Controller）不受这个开关影响。
        $style_sync = Config::get_option("front_style_sync", "editor_style") === "on";

        // 同步开启但选项为空时同样退回 default，避免把空字符串喂给 Editor.md
        $style_theme = (string) Config::get_option("theme_style", "editor_style");
        $style_code  = (string) Config::get_option("code_style", "editor_style");

        $front_theme         = ($style_sync && "" !== $style_theme) ? $style_theme : "default";
        $front_preview_theme = $front_theme;
        $front_code_style    = ($style_sync && "" !== $style_code) ? $style_code : "default";

        wp_localize_script("Config_Front", "_Editormd", array(
            "editormdUrl"       => $this->front_static_url,                                      // 静态资源CDN地址
            "syncScrolling"     => Config::get_option("sync_scrolling", "editor_basics"),        // 编辑器同步
            "livePreview"       => Config::get_option("live_preview", "editor_basics"),          // 即是否开启实时预览
            "htmlDecode"        => Config::get_option("html_decode", "editor_basics"),           // HTML标签解析
            "imageLink"         => Config::get_option("image_link", "editor_basics"),            // 图片链接
            "toc"               => Config::get_option("support_toc", "editor_toc"),              // TOC
            "theme"             => $front_theme,                                                 // 编辑器总体主题
            "previewTheme"      => $front_preview_theme,                                         // 编辑器预览主题
            "editorTheme"       => $front_code_style,                                            // 编辑器编辑主题
            "emoji"             => Config::get_option("support_emoji", "editor_emoji"),          // emoji表情
            "tex"               => Config::get_option("support_latex", "editor_latex"),          // 科学公式
            "taskList"          => Config::get_option("task_list", "editor_basics"),             // task lists
            "imagePaste"        => Config::get_option("imagepaste", "editor_basics"),            // 图像粘贴
            "prismTheme"        => $prismTheme,                                                  // 语法高亮风格
            "prismLineNumbers"  => Config::get_option("line_numbers", "syntax_highlighting"),    // 行号显示
            "mindMap"           => Config::get_option("support_mindmap", "editor_mindmap"),      // 思维导图
            "mindMapURL"        => Config::get_option("customize_mindmap", "editor_mindmap"),    // 思维导图地址
            "mermaid"           => Config::get_option("support_mermaid", "editor_mermaid"),      // Mermaid
            //"mermaidConfig"     => Config::get_option("mermaid_config","editor_mermaid"),      // Mermaid配置
            "placeholderEditor" => __("Enjoy Markdown! Coding now...", $this->text_domain),
            "imgUploading"      => __("Image Uploading...", $this->text_domain),
            "imgUploadeFailed"  => __("Failed To Upload The Image!", $this->text_domain),
            "supportComment"    => Config::get_option("support_front", "editor_basics"),         // 前端评论
            "supportOther"      => Config::get_option("support_other_text", "editor_basics"),    // 前端编辑器ID
            // 访客评论简版模式（10.5.0）：
            //   开启后评论编辑器改为单栏 + 精简工具栏，且只提供粗体、斜体、删除线、
            //   引用、行内代码、图片与链接（链接与图片不可点击）。
            //   注意：服务端还有一层更硬的约束（KSES 白名单 + comment_text 钩子门控），
            //   见 src/App/SimpleCommentMode.php —— 前端只是「体验层」。
            "simpleComment"     => Config::get_option("simple_comment_editor", "editor_basics"),
            "commentPlaceholder" => __("Supports **bold**, *italic*, ~~strikethrough~~, > quote, `code`, images and links (links and images are not clickable)", $this->text_domain),
            // 安全加固：AJAX 接口的 CSRF nonce（由 assets/Config/editormd-nonce.js 垫片消费）
            "imagepasteNonce"   => wp_create_nonce("wp_editormd_imagepaste"),
            "pagesNonce"        => wp_create_nonce("wp_editormd_pages"),
            // 跨站资源地址时，把哈希表交给前端脚本，给按需加载的资源补 integrity
            "integrity"         => AssetsIntegrity::runtime_config(),
        ));
    }

}
