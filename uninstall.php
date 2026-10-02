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

function wp_editormd_uninstall_site_options( $options_name ) {
    foreach ( $options_name as $option_name ) {
        delete_option( $option_name );
    }

    delete_transient( 'editormd_static_file_ver' );
    delete_transient( 'editormd_plugin_activated' );
}

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

//开启自带可视化编辑器
