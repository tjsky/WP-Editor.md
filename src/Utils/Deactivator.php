<?php

namespace EditormdUtils;

class Deactivator {
    public static function deactivate() {
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            delete_user_option($user_id, "dismissed_wp_pointers", true);
        }
    }
}
