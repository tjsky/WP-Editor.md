<?php

namespace EditormdUtils;

use EditormdUtils\Config;

/**
 * 升级器类，用于版本升级时对配置进行修改
 */
class Upgrader {
    function __construct() {
        $this->run_upgrader();
    }
    
    /**
     * 升级逻辑
     * 
     * @return null
     */
    protected function run_upgrader() {
        $current_version = $this->get_current_version();

        switch($current_version) {
            case "":
                $this->any_to_10_1_0();
            case "10.1.0":
                $this->upgrade_10_1_0_to_10_1_1();
            case "10.1.1":
                $this->upgrade_10_1_1_to_10_1_2();
            case "10.1.2":
                $this->upgrade_10_1_2_to_10_2_0();
            case "10.2.0":
                $this->upgrade_10_2_0_to_10_2_1();
            case "10.2.1":
                $this->upgrade_10_2_1_to_10_3_0();
            case "10.3.0":
                $this->upgrade_10_3_0_to_10_4_0();
            case "10.4.0":
                $this->upgrade_10_4_0_to_10_4_1();
            case "10.4.1":
                $this->upgrade_10_4_1_to_10_4_2();
            case "10.4.2":
                $this->upgrade_10_4_2_to_10_4_3();
            case "10.4.3":
                $this->upgrade_10_4_3_to_10_4_4();
            default:
                break;
        }

        $next_version = $this->get_current_version();

        if ($current_version !== $next_version && "" !== $next_version) {
            $this->update_changelog_page($next_version);
        }
    }

    private function upgrade_10_4_3_to_10_4_4() {
        $this->update_to_version("10.4.4");
    }

    private function upgrade_10_4_2_to_10_4_3() {
        $this->update_to_version("10.4.3");
    }

    private function upgrade_10_4_1_to_10_4_2() {
        $this->update_to_version("10.4.2");
    }

    private function upgrade_10_4_0_to_10_4_1() {
        $this->update_to_version("10.4.1");
    }

    private function upgrade_10_3_0_to_10_4_0() {
        $this->update_to_version("10.4.0");
    }

    private function upgrade_10_2_1_to_10_3_0() {
        $this->update_to_version("10.3.0");
    }

    private function upgrade_10_2_0_to_10_2_1() {
        $this->update_to_version("10.2.1");
    }

    private function upgrade_10_1_2_to_10_2_0() {
        Config::update_option("imagepaste_sm_token", "editor_basics", "");
        $this->update_to_version("10.2.0");
    }

    private function upgrade_10_1_1_to_10_1_2() {
        $this->update_to_version("10.1.2");
    }

    private function upgrade_10_1_0_to_10_1_1() {
        $this->update_to_version("10.1.1");
    }

    /**
     * 任意版本升级到10.1.0
     * 
     * @return null
     */
    private function any_to_10_1_0() {
        // 暂时去除MathJax，替换为Katex
        $latexConfig = Config::get_option("support_latex", "editor_latex");
        if ($latexConfig == "mathjax") {
            Config::update_option("support_latex", "editor_latex", "katex");
        }
        
        // 新增在新窗口打开链接选项，默认为关闭
        Config::update_option("open_in_new_tab", "editor_basics", "off");

        // 新建版本号字段
        if (false === get_option("editor_version")) {
            add_option("editor_version", array());
        }

        // 升级到指定版本号
        $this->update_to_version("10.1.0");
    }
    
    /**
     * 从配置中获取当前版本号，如果没有则为空字符串
     * 
     * @return string
     */
    private function get_current_version() {
        $version = Config::get_option("wp_editormd_ver", "editor_version");

        return is_scalar($version) ? (string) $version : "";
    }

    /**
     * 更新配置到指定版本号，同时跳转到发行注记页面
     * 
     * @param string $version 目标版本号
     * 
     * @return boolean
     */
    private function update_to_version($version) {
        return Config::update_option("wp_editormd_ver", "editor_version", $version);
    }

    private function update_changelog_page($version) {
        if (is_admin() && ! wp_doing_ajax() && current_user_can("manage_options")) {
            $flag = "editormd_upgraded_to_" . $version;
            if (get_transient($flag)) {
                return;
            }

            set_transient($flag, 1, DAY_IN_SECONDS);

            wp_redirect(
                admin_url("options-general.php?page=wp-editormd-settings&action=release&version=" . rawurlencode($version)),
                302
            );
            exit();
        }
    }
}
