<?php

if (
    ! defined( 'WP_UNINSTALL_PLUGIN' )
    ||
    ! WP_UNINSTALL_PLUGIN
    ||
    dirname( WP_UNINSTALL_PLUGIN ) != dirname( plugin_basename( __FILE__ ) )
) {
    status_header( 404 );
    wp_die();
}

/**
 * 清理单个站点的插件选项
 *
 * @param array $options_name
 */
function wp_editormd_uninstall_site_options( $options_name ) {
    foreach ( $options_name as $option_name ) {
        delete_option( $option_name );
    }

    // 清除插件产生的缓存
    delete_transient( 'editormd_static_file_ver' );
    delete_transient( 'editormd_plugin_activated' );
}

/**
 * 需要清理的选项名
 *
 * 兼容性修复：原实现在文件顶层写 `static $options_name = array(...)`，
 * 但 PHP 只允许在函数内部声明 static 变量。文件级的 static 声明会在编译期
 * 直接报致命错误，导致插件根本无法被正常卸载。
 */
$options_name = array(
    'editor_basics',
    'editor_style',
    'syntax_highlighting',
    'editor_emoji',
    'editor_toc',
    'editor_latex',
    'editor_mermaid',
    'editor_mindmap',
    'editor_advanced',
    'editor_version'
);

if ( is_multisite() ) {
    // 多站点修复：原实现只清理当前站点，网络激活后会在各子站留下大量残留选项
    $wp_editormd_site_ids = get_sites(
        array(
            'fields' => 'ids',
            'number' => 0,
        )
    );

    foreach ( $wp_editormd_site_ids as $wp_editormd_site_id ) {
        switch_to_blog( $wp_editormd_site_id );
        wp_editormd_uninstall_site_options( $options_name );
        restore_current_blog();
    }

    delete_site_option( 'editor_version' );
} else {
    wp_editormd_uninstall_site_options( $options_name );
}

// 说明：原实现末尾的 add_filter( 'user_can_richedit', '__return_true' ) 在卸载流程中
// 不会有任何效果（注册完过滤器请求随即结束），故移除。
