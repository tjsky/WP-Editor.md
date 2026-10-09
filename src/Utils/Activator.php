<?php

namespace EditormdUtils;

use EditormdUtils\Upgrader as Upgrader;

class Activator {

    public static function activate($network_wide = false) {
        if ($network_wide && is_multisite()) {
            $site_ids = get_sites(array("fields" => "ids", "number" => 0));

            foreach ($site_ids as $site_id) {
                switch_to_blog($site_id);
                self::activate_site();
                restore_current_blog();
            }

            return;
        }

        self::activate_site();
    }

    private static function activate_site() {
        $defaults = array(
            "editor_basics"       => self::$defaultOptionsBasics,
            "editor_style"        => self::$defaultOptionsStyle,
            "syntax_highlighting" => self::$defaultOptionsSyntax,
            "editor_emoji"        => self::$defaultOptionsEmoji,
            "editor_toc"          => self::$defaultOptionsToc,
            "editor_latex"        => self::$defaultOptionsKatex,
            "editor_mermaid"      => self::$defaultOptionsMermaid,
            "editor_mindmap"      => self::$defaultOptionsMindMap,
            "editor_advanced"     => self::$defaultOptionsAdvanced,
        );

        foreach ($defaults as $option_name => $default_value) {
            if (false === get_option($option_name)) {
                add_option($option_name, $default_value, "", "yes");
            }
        }

        if (false === get_option("editor_version")) {
            add_option("editor_version", array("wp_editormd_ver" => WP_EDITORMD_VER), "", "yes");
        }

        // 版本升级器
        new Upgrader();
    }

    public static $defaultOptionsBasics = array(
        "task_list"           =>  "on",
        "imagepaste"          =>  "on",
        "image_link"          =>  "on",
        "open_in_new_tab"     =>  "on",
        "live_preview"        =>  "on",
        "sync_scrolling"      =>  "on",
        "html_decode"         =>  "on",
        "support_front"       =>  "off",
        "support_reply"       =>  "off",
        "support_other_text"  =>  "",
        "simple_comment_editor" => "off"
    );

    public static $defaultOptionsStyle = array(
        "theme_style"       => "default",
        "code_style"        => "default",
        "front_style_sync"  => "off",
        "editor_addres"     => ""
    );

    public static $defaultOptionsSyntax = array(
        "highlight_mode_auto"            => "on",
        "line_numbers"                   => "on",
        "show_language"                  => "on",
        "copy_clipboard"                 => "on",
        "highlight_library_style"        => "default",
        "customize_my_style"             => "nothing"
    );

    public static $defaultOptionsEmoji = array(
        "support_emoji" => "on"
    );

    public static $defaultOptionsToc = array(
        "support_toc" => "off"
    );

    public static $defaultOptionsKatex = array(
        "support_latex" => "katex"
    );

    public static $defaultOptionsMermaid = array(
        'support_mermaid' => 'off',
        'mermaid_config'  => '{
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
}'
    );

    public static $defaultOptionsMindMap = array(
        "support_mindmap"   => "off",
        "customize_mindmap" => ""
    );

    public static $defaultOptionsAdvanced = array(
        "jquery_compatible" => "off",
        "hide_ads"          => "off",
    );
}
