<?php

namespace EditormdUtils;

class Deactivator {
    public static function deactivate() {
        $user_id = get_current_user_id();

        // 健壮性修复：插件停用可能发生在 WP-CLI、定时任务等未登录场景，
        // 此时 get_current_user_id() 返回 0，原实现会带着 uid=0 去操作
        // dismissed_wp_pointers 元数据。
        if ($user_id > 0) {
            delete_user_option($user_id, "dismissed_wp_pointers", true);
        }
    }
}
