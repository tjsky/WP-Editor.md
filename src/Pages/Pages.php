<?php

namespace EditormdPages;
use EditormdUtils\Config;

/**
 * 页面渲染类，用于控制静态页面的展示
 *
 * 安全加固说明（10.3.0）：
 *  1. canAdmin() 原先用 current_user_can("administrator")，把「角色名」当成「能力名」校验，
 *     语义错误且会误伤自定义角色；现改用标准能力 manage_options。
 *  2. LOGIN_PRIV 分支存在 `if ($this->canLogin()) $authorized = true;` 之后
 *     又紧跟一句无条件的 `$authorized = true;`，使该权限级别形同虚设（恒真授权），已删除。
 *  3. $_GET 未做 isset/净化，PHP 8 下会触发 Undefined array key，并把 null 传给 preg_match()。
 *  4. 安全参数正则 /^([a-z0-9\-\_]?)+$/ 属于典型的 (x?)+ 灾难性回溯写法，可被低成本打挂。
 *  5. require_once(__DIR__ . "/page/$page/$page.php") 使用变量拼接路径，改为固定白名单映射。
 *  6. call_user_func("wp_editormd_entry_" . $entry) 动态拼接函数名，改为显式白名单。
 *  7. 原实现完全没有 CSRF 校验。
 */
class Pages {
    const ADMIN_PRIV = 0;
    const LOGIN_PRIV = 1;
    const GURST_PRIV = 2;

    /**
     * 允许渲染的页面白名单：page slug => 权限级别
     *
     * @var array
     */
    private $pages;

    /**
     * 允许调用的 entry 回调白名单：page slug => array(entry => 函数名)
     *
     * @var array
     */
    private $entries;

    /**
     * 页面渲染函数白名单：page slug => 函数名
     *
     * @var array
     */
    private $renderers;

    private $text_domain;

    function __construct($text_domain) {
        $this->text_domain = $text_domain;

        $this->pages = array(
            "sm-ms-management"   =>    self::ADMIN_PRIV,
            "upgrade-release"    =>    self::ADMIN_PRIV,
        );

        $this->entries = array(
            "sm-ms-management"   => array(
                "sm_ms_proxy" => "wp_editormd_entry_sm_ms_proxy",
            ),
            "upgrade-release"    => array(),
        );

        /**
         * 页面渲染函数白名单：page slug => 函数名
         *
         * 安全/健壮性修复（10.4.2）：原先两个页面文件都定义了全局函数 display_page()，
         * 属于典型的全局命名污染 —— 只要主题、其它插件或 SDK 也定义了同名函数，
         * require 页面文件时就会 "Cannot redeclare display_page()" 直接 500。
         * 现改为插件前缀命名，并在调用前用 function_exists() 校验。
         */
        $this->renderers = array(
            "sm-ms-management"   => "wp_editormd_render_sm_ms_management_page",
            "upgrade-release"    => "wp_editormd_render_upgrade_release_page",
        );

        // 注册 wp_ajax 接口。
        // 安全修复（10.4.2）：本类的两个页面都是管理员页面，原先额外注册了
        // wp_ajax_nopriv_* 允许未登录用户进入该入口，属于多余且危险的暴露面，已移除。
        add_action("wp_ajax_wp_editormd_pages", array($this, "renderer"));
    }

    public function renderer() {
        // 安全修复：原先直接读 $_GET，无 isset、无净化
        $page  = isset($_GET["page"]) ? sanitize_key(wp_unslash($_GET["page"])) : "";
        $entry = isset($_GET["entry"]) ? sanitize_key(wp_unslash($_GET["entry"])) : "";

        // 安全修复：页面白名单校验，未命中直接拒绝（原先仅靠一条有 ReDoS 缺陷的正则过滤）
        if (! isset($this->pages[$page])) {
            $this->noAccess();
        }

        // 安全修复：CSRF 校验（原先完全没有）
        if (! $this->verifyRequest($page)) {
            $this->noAccess();
        }

        // 权限校验
        if (! $this->isAuthorized($this->pages[$page])) {
            $this->noAccess();
        }

        // 安全修复：$page 已通过白名单校验，路径拼接不再可被污染
        require_once(__DIR__ . "/page/$page/$page.php");

        if ("" !== $entry) {
            // 安全修复：entry 改为显式白名单分发，不再动态拼接函数名
            if (! isset($this->entries[$page][$entry]) || ! function_exists($this->entries[$page][$entry])) {
                $this->noAccess();
            }

            echo call_user_func($this->entries[$page][$entry]);
        } else {
            // 安全修复：渲染函数同样走白名单，避免依赖全局函数名
            if (! isset($this->renderers[$page]) || ! function_exists($this->renderers[$page])) {
                $this->noAccess();
            }

            echo call_user_func($this->renderers[$page], $this->text_domain, Config::class);
        }

        wp_die();
    }

    /**
     * 请求来源校验（CSRF）
     *
     * 安全修复（10.4.2）：原实现以「Host 相同即视为同源」作为 nonce 校验失败的兜底。
     * 该判断既不等价于同源（未比较 scheme 与 port，https://example.com 与
     * https://example.com:8443、http://example.com 都会被判为同源），
     * 又把 Origin/Referer 当成了 nonce 的替代品 —— 而这两个头在非浏览器客户端
     * 可以任意伪造。现改为只接受有效的 nonce，与 WordPress 标准做法一致。
     *
     * @param string $page
     *
     * @return bool
     */
    private function verifyRequest($page) {
        $nonce = isset($_REQUEST["_wpnonce"]) ? sanitize_text_field(wp_unslash($_REQUEST["_wpnonce"])) : "";
        if ("" === $nonce) {
            return false;
        }

        return (bool) wp_verify_nonce($nonce, "wp_editormd_pages");
    }

    /**
     * 按权限级别校验当前用户
     *
     * 安全修复：原先的 switch 中 LOGIN_PRIV 分支会无条件把 $authorized 置为 true，
     * 这里改为真正的返回值判断。
     *
     * @param int $pagePriv
     *
     * @return bool
     */
    private function isAuthorized($pagePriv) {
        switch ($pagePriv) {
            case self::ADMIN_PRIV:
                return $this->canAdmin();
            case self::LOGIN_PRIV:
                return $this->canLogin();
            case self::GURST_PRIV:
                return $this->canGuest();
        }

        return false;
    }

    private function canAdmin() {
        // 安全修复：角色名不等同于能力名
        return (bool) current_user_can("manage_options");
    }

    private function canLogin() {
        return (bool) is_user_logged_in();
    }

    private function canGuest() {
        return true;
    }

    private function noAccess() {
        // 安全修复：AJAX 场景下返回明确的 403，而不是伪装成 404
        if (function_exists("wp_doing_ajax") && wp_doing_ajax()) {
            nocache_headers();
            wp_die("Forbidden", "Forbidden", array("response" => 403));
        }

        global $wp_query;
        if ($wp_query instanceof \WP_Query) {
            $wp_query->set_404();
        }
        status_header(404);
        nocache_headers();
        die();
    }
}
