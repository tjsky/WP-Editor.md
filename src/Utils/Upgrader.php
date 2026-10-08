<?php

namespace EditormdUtils;

use EditormdUtils\Config;

/**
 * 升级器类，用于版本升级时对配置进行修改
 *
 * 10.3.0 修复说明：
 *   1. run_upgrader() 的 switch 故意使用贯穿（fall-through），这里补上显式注释，
 *      说明这是「按版本顺序依次执行迁移」的设计而非笔误。
 *   2. 新增 10.2.1 → 10.3.0 迁移，否则 editor_version 永远停在 10.2.1，
 *      会让「版本变更才执行激活流程」的判断每次都成立，形成重复执行。
 *   3. update_changelog_page() 原实现无条件在 init 上 wp_redirect + exit，
 *      若选项写入失败（版本号没变）就会每次请求都 302，前台也会被跳到 wp-admin；
 *      现改为「仅在后台、非 AJAX、且确实发生了版本变化时跳转」，并加一次性闸门。
 *
 * 10.4.0 补充：
 *   迁移链新增 10.3.0 → 10.4.0。本版无数据结构变更，但必须推进版本号，
 *   否则从旧版升级上来的站点 editor_version 会停在上一个版本，
 *   使 wp-editormd.php 中「版本不一致」的判断长期成立、激活流程被反复执行。
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

        // 注意：此处的 switch 故意不写 break，按版本顺序依次贯穿执行迁移。
        // 例如从 "" 升级时会依次跑完 10.1.0 → 10.1.1 → 10.1.2 → 10.2.0 → 10.2.1 → 10.3.0。
        switch($current_version) {
            case "":
                $this->any_to_10_1_0();
                // no break —— 继续执行后续迁移
            case "10.1.0":
                $this->upgrade_10_1_0_to_10_1_1();
                // no break
            case "10.1.1":
                $this->upgrade_10_1_1_to_10_1_2();
                // no break
            case "10.1.2":
                $this->upgrade_10_1_2_to_10_2_0();
                // no break
            case "10.2.0":
                $this->upgrade_10_2_0_to_10_2_1();
                // no break
            case "10.2.1":
                $this->upgrade_10_2_1_to_10_3_0();
                // no break
            case "10.3.0":
                $this->upgrade_10_3_0_to_10_4_0();
                // no break
            case "10.4.0":
                $this->upgrade_10_4_0_to_10_4_1();
                // no break
            case "10.4.1":
                $this->upgrade_10_4_1_to_10_4_2();
                // no break
            case "10.4.2":
                $this->upgrade_10_4_2_to_10_4_3();
                // no break
            case "10.4.4":
                $this->upgrade_10_4_4_to_10_4_5();
                // no break
            default:
                break;
        }

        $next_version = $this->get_current_version();

        // 如果存在版本更新，则在更新完成后跳转到更新页面。
        // 只有当版本确实发生变化时才跳转——否则一旦写入失败就会形成无限重定向。
        if ($current_version !== $next_version && "" !== $next_version) {
            $this->update_changelog_page($next_version);
        }
    }

    /**
     * 10.4.4 → 10.4.5
     *
     * 本版修的是「编辑器预览与保存结果不一致」：预览侧不认识插件的图片尺寸 /
     * 属性语法，也缺少服务端那套公式误渲染防护。全部改动都在前端资源与
     * 构建产物（assets/Config/editormd.min.js）里，没有数据结构变更，
     * 只需把 editor_version 推进到新版本号 —— 理由见上一段。
     */
    private function upgrade_10_4_4_to_10_4_5() {
        $this->update_to_version("10.4.5");
    }

    /**
     * 10.4.3 → 10.4.4
     *
     * 本版修的是评论侧的 Markdown 链路（转换开关默认关闭导致评论原样入库、
     * html_decode 关闭时引用与链接标题被转义破坏）以及 Prism 的双份加载。
     * 同样没有数据结构变更，只需把 editor_version 推进到新版本号。
     */
    private function upgrade_10_4_3_to_10_4_4() {
        $this->update_to_version("10.4.4");
    }

    /**
     * 10.4.2 → 10.4.3
     *
     * 本版只修前端缺陷（Prism 语言包路径与执行顺序、工具栏与 Bootstrap 的
     * .tooltip 类名冲突、后台回复框宽度、字数统计的未声明变量），
     * 没有数据结构变更，同样只需把 editor_version 推进到新版本号 —— 理由见上一段。
     */
    private function upgrade_10_4_2_to_10_4_3() {
        $this->update_to_version("10.4.3");
    }

    /**
     * 10.4.1 → 10.4.2
     *
     * 本版只做安全与依赖升级（Mermaid / KaTeX / 前端依赖、图片资源预算、
     * AJAX 授权收紧、令牌移出浏览器等），没有数据结构变更，
     * 同样只需把 editor_version 推进到新版本号 —— 理由见上一段。
     */
    private function upgrade_10_4_1_to_10_4_2() {
        $this->update_to_version("10.4.2");
    }

    /**
     * 10.4.0 → 10.4.1
     *
     * 本版只修复了插件包的元数据（插件头字段），没有数据结构变更，
     * 这里同样只需把 editor_version 推进到新版本号 —— 理由见上一段。
     */
    private function upgrade_10_4_0_to_10_4_1() {
        $this->update_to_version("10.4.1");
    }

    /**
     * 10.3.0 → 10.4.0
     *
     * 本版没有数据结构变更，只需把 editor_version 推进到新版本号。
     * 这一步不能省：wp-editormd.php 用
     *   $installed['wp_editormd_ver'] !== WP_EDITORMD_VER
     * 判断是否需要重跑激活流程，若版本号停在上一个版本，
     * 该条件会长期成立，导致每次请求都重复执行 Activator::activate()。
     */
    private function upgrade_10_3_0_to_10_4_0() {
        $this->update_to_version("10.4.0");
    }

    /**
     * 10.2.1 → 10.3.0
     */
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
     * 更新配置到指定版本号
     * 
     * @param string $version 目标版本号
     * 
     * @return boolean
     */
    private function update_to_version($version) {
        return Config::update_option("wp_editormd_ver", "editor_version", $version);
    }

    /**
     * 跳转到发行注记页面（302）
     *
     * somesite.com/wp-admin/options-general.php?page=wp-editormd-settings&action=release&version=x.x.x
     *
     * @param string $version
     */
    private function update_changelog_page($version) {
        // 修复：原实现挂在 init 上无条件跳转，前台请求也会被 302 到 wp-admin，
        // 且一旦版本号写入失败就会形成无限重定向。
        if (is_admin() && ! wp_doing_ajax() && current_user_can("manage_options")) {
            // 一次性闸门：同一次版本迁移只跳转一次
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
