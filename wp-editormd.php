<?php
/**
 * Plugin Name:       WP Editor.md
 * Plugin URI:        https://github.com/tjsky/WP-Editor.md
 * Description:       Perhaps this is the best and most perfect Markdown editor in WordPress
 * Version:           10.3.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Tested up to:      7.1
 * Author:            LuRenJiasWorld (maintained fork by tjsky)
 * Author URI:        https://github.com/tjsky/WP-Editor.md
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       editormd
 * Domain Path:       /languages
 *
 * 10.3.0 安全加固与兼容性改造：
 *   - 修复 KaTeX 渲染链路的存储型 XSS（对应 CVE-2025-31035 同类问题）；
 *   - 修复图片粘贴接口缺失权限与 CSRF 校验、任意文件写入、图床代理 SSRF 等问题；
 *   - 修复设置页选项数据被相互覆盖、后台每个请求同步外联等缺陷；
 *   - 兼容 WordPress 7.0 与 PHP 8.4。
 */

namespace EditormdRoot;

use Editormd\Main;
use EditormdUtils\Activator;
use EditormdUtils\Logger;
use EditormdUtils\Deactivator;

define( 'WP_EDITORMD_VER', '10.3.0' );                      // 版本说明
define( 'WP_EDITORMD_URL', plugins_url( '', __FILE__ ) );   // 插件资源路径
define( 'WP_EDITORMD_PATH', dirname( __FILE__ ) );          // 插件路径文件夹
define( 'WP_EDITORMD_NAME', plugin_basename( __FILE__ ) );  // 插件名称

// 自动载入文件
require_once WP_EDITORMD_PATH . '/vendor/autoload.php';

/**
 * 调试-日志模式
 *
 * 安全修复：原实现把这段逻辑放在 require vendor/autoload.php **之前**，
 * 于是在自动加载器注册前就调用了 Logger，任何携带 wp-editormd-dev-logmode
 * Cookie 的访客都会触发 "Class EditormdUtils\Logger not found" 致命错误（白屏）；
 * 而且判断用的 Cookie 名（-dev-logmode）与读取的键名（-logmode-dev）并不一致，
 * 读取到未定义键时还会向 set_log_level() 传入 null。
 * 现在改为：autoload 之后执行、限定管理员、取值净化、异常兜底。
 *
 * 兼容性修复（致命，实测发现）：本段逻辑**不得写在插件主文件顶层**。
 * 插件主文件由 wp-settings.php 在「加载活动插件」阶段 include（第 582 行），
 * 而提供 current_user_can() / wp_get_current_user() 的 pluggable.php 要到
 * 第 612 行才加载。在顶层调用 current_user_can() 会直接抛
 *   Fatal error: Call to undefined function wp_get_current_user()
 * 且发生在插件加载期 —— 前台与后台**同时白屏**，属最高级别的可用性事故。
 * 因此改挂 plugins_loaded：该钩子（第 630 行触发）晚于 pluggable.php。
 */
add_action( 'plugins_loaded', function () {
    if ( ! isset( $_COOKIE['wp-editormd-dev-logmode'] ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $editormd_log_level = sanitize_text_field( wp_unslash( $_COOKIE['wp-editormd-dev-logmode'] ) );

    try {
        Logger::set_log_level( $editormd_log_level );
    } catch ( \Throwable $e ) {
        // 日志等级非法时不应影响页面渲染
        unset( $e );
    }
} );

/**
 * 插件激活期间运行的代码
 *
 * @param bool $network_wide 多站点环境下是否为「网络启用」
 */
function activate_editormd( $network_wide = false ) {
    Activator::activate( $network_wide );
}

/**
 * 在插件停用期间运行的代码
 * includes/class-plugin-name-deactivator.php
 */
function deactivate_editormd() {
    Deactivator::deactivate();
}

register_activation_hook( __FILE__, '\EditormdRoot\activate_editormd' );
register_deactivation_hook( __FILE__, '\EditormdRoot\deactivate_editormd' );

/**
 * 执行插件函数
 */
function run_editormd() {
    if ( version_compare( PHP_VERSION, '7.4.0' ) < 0 ) {
        unset( $_GET['activated'] );
        add_action( 'admin_notices', function () {
            $message = __( 'Hey, we\'ve noticed that you\'re running an outdated version of PHP which is no longer supported. Make sure your site is fast and secure, by upgrading PHP to the latest version.', 'editormd' );
            printf( '<div class="error"><p>%1$s</p></div>', esc_html( $message ) );
        } );
    } else {
        /**
         * 版本更新后需要执行 Activator 中的 Upgrader。
         *
         * 性能修复：原实现只要用户已登录，就在每次请求都重跑一遍
         * Activator::activate()（内含十余次 get_option 与 new Upgrader()），
         * 而 Upgrader 中还带有 wp_redirect()，开销与副作用都很大。
         * 现在改为仅在版本号发生变化时才执行。
         */
        add_action( 'init', function () {
            if ( ! is_user_logged_in() ) {
                return;
            }

            $installed = get_option( 'editor_version' );
            if (
                ! is_array( $installed )
                || ! isset( $installed['wp_editormd_ver'] )
                || $installed['wp_editormd_ver'] !== WP_EDITORMD_VER
            ) {
                Activator::activate();
            }
        } );

        new Main();
    }
}

/**
 * 开始执行插件
 */
run_editormd();
