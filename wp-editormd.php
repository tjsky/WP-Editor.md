<?php
/**
 * Plugin Name:       WP Editor.md
 * Plugin URI:        https://github.com/tjsky/WP-Editor.md
 * Description:       Perhaps this is the best and most perfect Markdown editor in WordPress
 * Version:           10.4.2
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Tested up to:      7.1
 * Author:            LuRenJiasWorld (maintained fork by tjsky)
 * Author URI:        https://github.com/tjsky/WP-Editor.md
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       editormd
 * Domain Path:       /languages
 */

namespace EditormdRoot;

use Editormd\Main;
use EditormdUtils\Activator;
use EditormdUtils\Logger;
use EditormdUtils\Deactivator;

define( 'WP_EDITORMD_VER', '10.4.2' );                      // 版本说明
define( 'WP_EDITORMD_URL', plugins_url( '', __FILE__ ) );   // 插件资源路径
define( 'WP_EDITORMD_PATH', dirname( __FILE__ ) );          // 插件路径文件夹
define( 'WP_EDITORMD_NAME', plugin_basename( __FILE__ ) );  // 插件名称

// 自动载入文件
require_once WP_EDITORMD_PATH . '/vendor/autoload.php';

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
        unset( $e );
    }
} );

/**
 * 插件激活期间运行的代码
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
        add_action( 'init', function () {
            $installed = get_option( 'editor_version' );

            if (
                is_array( $installed )
                && isset( $installed['wp_editormd_ver'] )
                && $installed['wp_editormd_ver'] === WP_EDITORMD_VER
            ) {
                return;
            }

            if ( get_transient( 'wp_editormd_migrating' ) ) {
                return;
            }

            set_transient( 'wp_editormd_migrating', 1, MINUTE_IN_SECONDS );

            try {
                Activator::activate();
            } catch ( \Throwable $e ) {
                error_log( 'WP Editor.md: 升级/迁移失败 - ' . $e->getMessage() );
                delete_transient( 'wp_editormd_migrating' );
                return;
            }

            delete_transient( 'wp_editormd_migrating' );
        } );

        new Main();
    }
}

/**
 * 开始执行插件
 */
run_editormd();
