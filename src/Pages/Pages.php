<?php

namespace EditormdPages;
use EditormdUtils\Config;

/**
 * 页面渲染类，用于控制静态页面的展示
 */
class Pages {
    const ADMIN_PRIV = 0;
    const LOGIN_PRIV = 1;
    const GURST_PRIV = 2;

    private $pages;

    private $entries;

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

        // 注册wp_ajax接口，同时允许登录和非登录用户访问，权限由本类控制
        add_action("wp_ajax_wp_editormd_pages", array($this, "renderer"));
        add_action("wp_ajax_nopriv_wp_editormd_pages", array($this, "renderer"));
    }

    public function renderer() {
        $page  = isset($_GET["page"]) ? sanitize_key(wp_unslash($_GET["page"])) : "";
        $entry = isset($_GET["entry"]) ? sanitize_key(wp_unslash($_GET["entry"])) : "";

        if (! isset($this->pages[$page])) {
            $this->noAccess();
        }

        if (! $this->verifyRequest($page)) {
            $this->noAccess();
        }

        if (! $this->isAuthorized($this->pages[$page])) {
            $this->noAccess();
        }

        require_once(__DIR__ . "/page/$page/$page.php");

        if ("" !== $entry) {
            if (! isset($this->entries[$page][$entry]) || ! function_exists($this->entries[$page][$entry])) {
                $this->noAccess();
            }

            echo call_user_func($this->entries[$page][$entry]);
        } else {
            echo display_page($this->text_domain, Config::class);
        }

        wp_die();
    }

    private function verifyRequest($page) {
        $nonce = isset($_REQUEST["_wpnonce"]) ? sanitize_text_field(wp_unslash($_REQUEST["_wpnonce"])) : "";
        if ("" !== $nonce && wp_verify_nonce($nonce, "wp_editormd_pages")) {
            return true;
        }

        return $this->isSameOriginRequest();
    }

    private function isSameOriginRequest() {
        $home = wp_parse_url(home_url());
        if (empty($home["host"])) {
            return false;
        }

        foreach (array("HTTP_ORIGIN", "HTTP_REFERER") as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }

            $source = wp_parse_url(esc_url_raw(wp_unslash($_SERVER[$key])));
            if (empty($source["host"])) {
                continue;
            }

            if (strtolower($source["host"]) === strtolower($home["host"])) {
                return true;
            }
        }

        return false;
    }

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
        return (bool) current_user_can("manage_options");
    }

    private function canLogin() {
        return (bool) is_user_logged_in();
    }

    private function canGuest() {
        return true;
    }

    private function noAccess() {
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
