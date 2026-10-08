<?php

namespace EditormdApp;

use EditormdUtils\Config;

class PrismJSAuto {

    /**
     * @var bool 是否已经入队过，避免多处调用时重复注入内联脚本
     */
    private static $enqueued = false;

    public function __construct() {
        add_action("wp_enqueue_scripts", array($this, "prism_styles_scripts"));
    }

    /**
     * 语言包目录（Prism autoloader 从这里按需取语言的定义文件）
     *
     * @return string
     */
    private static function prism_languages_path() {
        return esc_url_raw(Config::get_option("editor_addres", "editor_style") . "/assets/Prism.js/components/");
    }

    public function prism_styles_scripts() {
        self::enqueue_assets();
    }

    /**
     * 入队 Prism 相关资源
     *
     * 前台（wp_enqueue_scripts）与 wp-admin 的编辑界面共用。
     *
     * 统一 Prism（10.4.4）：wp-admin 侧此前没有任何 Prism —— 编辑器的预览靠
     * Editor.md 自带的 assets/Editormd/lib/prism.min.js（见该文件里的说明，现已成为占位）。
     * 既然插件已经提供了配置完整的 Prism（主题、行号、复制按钮、语言标签、
     * 按需加载的语言包），后台就不再引入第二套，直接复用这一套。
     */
    public static function enqueue_assets() {
        if (self::$enqueued) {
            return;
        }
        self::$enqueued = true;

        $prism_base_url = Config::get_option("editor_addres","editor_style") . "/assets/Prism.js";                  // 资源载入地址
        $prism_theme    = Config::get_option("highlight_library_style", "syntax_highlighting");                     // 语法高亮风格
        $line_numbers   = Config::get_option("line_numbers", "syntax_highlighting") == "on" ? true : false;         // 行号显示
        $show_language  = Config::get_option("show_language", "syntax_highlighting") == "on" ? true : false;        // 显示语言
        $copy_clipboard = Config::get_option("copy_clipboard", "syntax_highlighting") == "on" ? true : false;       // 粘贴
        $toolbar        = true;                                                                                     // 工具栏，必须加载，否则参考Issue#454

        $prism_plugins  = array(
            "autoloader" => array(
                "js"  => true,
                "css" => false
            ),
            "toolbar" => array(
                "js"  => $toolbar,
                "css" => $toolbar
            ),
            "line-numbers" => array(
                "css" => $line_numbers,
                "js"  => $line_numbers
            ),
            "show-language" => array(
                "js"  => $show_language,
                "css" => false
            ),
            "copy-to-clipboard" => array(
                "js"  => $copy_clipboard,
                "css" => false
            ),
        );
        $prism_styles   = array();
        $prism_scripts  = array();

        if (empty($prism_theme) || $prism_theme == "default") {
            $prism_styles["prism-theme-default"] = $prism_base_url . "/themes/prism.css";
        } else if ($prism_theme == "customize") {
            $prism_styles["prism-theme-style"] = Config::get_option("customize_my_style", "syntax_highlighting"); //自定义风格
        } else {
            $prism_styles["prism-theme-style"] = $prism_base_url . "/themes/prism-{$prism_theme}.css";
        }
        foreach ($prism_plugins as $prism_plugin => $prism_plugin_config) {
            if ($prism_plugin_config["css"] === true) {
                $prism_styles["prism-plugin-{$prism_plugin}"] = $prism_base_url . "/plugins/{$prism_plugin}/prism-{$prism_plugin}.css";
            }
            if ($prism_plugin_config["js"] === true) {
                $prism_scripts["prism-plugin-{$prism_plugin}"] = $prism_base_url . "/plugins/{$prism_plugin}/prism-{$prism_plugin}.min.js";
            }
        }

        /**
         * 代码粘贴代码增强
         * 引入clipboard
         */
        $lib_url = Config::get_option("editor_addres","editor_style") . "/assets/ClipBoard/clipboard.min.js";

        if ($copy_clipboard) {
            wp_enqueue_script("copy-clipboard", $lib_url, array(), "2.0.1", true);
        }

        // 版本号同时充当静态资源的缓存指纹，必须与 assets/Prism.js 里真正的发行版一致
        // （见 assets/Prism.js/package.json）。此前这里写死的 1.15.0 是 Editor.md 自带
        // 的那套 Prism 的版本，插件实际随包发布的是 1.19.0。
        $prism_version = "1.19.0";

        foreach ($prism_styles as $name => $prism_style) {
            wp_enqueue_style($name, $prism_style, array(), $prism_version, "all");
        }

        // Prism 内核必须先于所有插件加载（autoloader 等都会在加载时读取 Prism 全局对象），
        // 因此这里显式声明依赖，而不是依赖入队顺序。
        wp_enqueue_script("prism-core-js", $prism_base_url . "/components/prism-core.min.js", array(), $prism_version, true);

        foreach ($prism_scripts as $name => $prism_script) {
            wp_enqueue_script($name, $prism_script, array("prism-core-js"), $prism_version, true);
        }

        // 语言包路径必须等 Prism 与 autoloader 都执行完再设置。
        //
        // 修复（10.4.3）：原实现把这段配置挂在 wp_print_footer_scripts 的**默认优先级 10** 上
        // 直接输出，而 WordPress 打印页脚脚本是在优先级 20 —— 内联脚本先跑，此时 Prism
        // 还不存在，于是控制台必然报 `Uncaught ReferenceError: Prism is not defined`；
        // 并且 languages_path 没设上，autoloader 会退回默认的相对路径 `components/`，
        // 去请求 `https://站点/components/prism-*.min.js`（404）。
        // 现在改为挂在本插件自己的脚本句柄之后，由 WordPress 保证执行顺序。
        wp_add_inline_script("prism-plugin-autoloader", self::prism_autoloader_init_script(), "after");
    }

    /**
     * Prism autoloader 的初始化脚本
     *
     * @return string
     */
    private static function prism_autoloader_init_script() {
        // plaintext / plain / text / txt 这四个纯文本语言：Prism 既不在 core 里预置，
        // 也没有对应的组件文件，而 autoloader 遇到未加载的语言就会去取
        // prism-<lang>.min.js。预声明成空语法后 autoloader 直接跳过，省掉一次必然 404 的请求
        // （空语法对纯文本来说也正是正确的行为）。
        return 'window.Prism=window.Prism||{};'
            . '(function(P){'
            . 'P.languages=P.languages||{};'
            . 'P.plugins=P.plugins||{};'
            . 'P.languages.plaintext=P.languages.plaintext||{};'
            . 'P.languages.plain=P.languages.plain||{};'
            . 'P.languages.text=P.languages.text||{};'
            . 'P.languages.txt=P.languages.txt||{};'
            . 'if(P.plugins.autoloader){P.plugins.autoloader.languages_path='
            . wp_json_encode(self::prism_languages_path()) . ';}'
            . '})(window.Prism);';
    }
}