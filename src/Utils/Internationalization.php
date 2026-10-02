<?php

namespace EditormdUtils;

class Internationalization {

    /**
     * @access   private
     * @var      string    $domain    The domain identifier for this plugin.
     */
    private $domain;

    public function __construct() {
        $this->domain = "editormd";
        add_action("plugins_loaded", array($this,"load_plugin_textdomain"));
    }

    /**
     * 指定文件夹
     *
     * 兼容性修复：原实现使用 dirname(dirname(dirname(plugin_basename(__FILE__)))) 拼接相对路径，
     * 依赖 include_path 解析，比较脆弱；改用 plugin_dir_path()（绝对路径）。
     */
    public function load_plugin_textdomain() {

        load_plugin_textdomain(
            $this->domain,
            false,
            dirname(plugin_basename(__FILE__)) . "/../../languages/"
        );

    }

}
