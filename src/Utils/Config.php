<?php

namespace EditormdUtils;

/**
 * Config类用于优化对wp_option表的增删改查
 * 接受静态调用，无需实例化，减少各模块代码量
 */
class Config {
    private static $allowed_sections = array(
        "editor_basics",
        "editor_style",
        "syntax_highlighting",
        "editor_emoji",
        "editor_toc",
        "editor_latex",
        "editor_mermaid",
        "editor_mindmap",
        "editor_advanced",
        "editor_version",
    );

    public static function is_allowed_section($section) {
        return is_string($section) && in_array($section, self::$allowed_sections, true);
    }

    /**
     * 获取字段值
     *
     * @param string $option  字段名称
     * @param string $section 字段名称分组
     * @param string $default 没搜索到返回空
     *
     * @return mixed
     */
    public static function get_option($option, $section, $default="") {
        if (! self::is_allowed_section($section)) {
            return $default;
        }

        $options = get_option($section);

        if (is_array($options) && isset($options[$option])) {
            return $options[$option];
        }

        return $default;
    }

    /**
     * 更新配置字段值
     *
     * @param string $option   字段名称
     * @param string $section  字段名称分组
     * @param string $value    新值
     *
     * @return mixed
     */
    public static function update_option($option, $section, $value) {
        if (! self::is_allowed_section($section)) {
            return false;
        }

        $options = get_option($section);

        if (! is_array($options)) {
            $options = array();
        }

        $options[$option] = $value;
        update_option($section, $options);

        return self::get_option($option, $section, "NO_DATA") != "NO_DATA";
    }
}
