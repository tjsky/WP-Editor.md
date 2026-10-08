<?php

namespace EditormdApp;

use EditormdUtils\Config;

class PrismJSAuto {

    private static $enqueued = false;

    public function __construct() {
        add_action("wp_enqueue_scripts", array($this, "prism_styles_scripts"));
    }

    private static function prism_languages_path() {
        return esc_url_raw(Config::get_option("editor_addres", "editor_style") . "/assets/Prism.js/components/");
    }

    public function prism_styles_scripts() {
        self::enqueue_assets();
    }

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

        $prism_version = "1.19.0";

        foreach ($prism_styles as $name => $prism_style) {
            wp_enqueue_style($name, $prism_style, array(), $prism_version, "all");
        }

        wp_enqueue_script("prism-core-js", $prism_base_url . "/components/prism-core.min.js", array(), $prism_version, true);

        foreach ($prism_scripts as $name => $prism_script) {
            wp_enqueue_script($name, $prism_script, array("prism-core-js"), $prism_version, true);
        }

        wp_add_inline_script("prism-plugin-autoloader", self::prism_autoloader_init_script(), "after");
    }

    private static function prism_autoloader_init_script() {
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