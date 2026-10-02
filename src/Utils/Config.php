<?php

namespace EditormdUtils;

/**
 * Config类用于优化对wp_option表的增删改查
 * 接受静态调用，无需实例化，减少各模块代码量
 *
 * 10.3.0 修复说明：
 *   1. get_option() 对非数组返回值直接按偏移访问（PHP 8 下会告警）；
 *   2. update_option() 中 `$options[$option] = $value;` 在选项不存在时，
 *      get_option() 返回 false，PHP 8.1 起「把 false 自动转换为数组」已弃用；
 *   3. 新增分组白名单，避免 Config::update_option() 被用于写入任意 WordPress 选项
 *      （即选项注入）。
 */
class Config {
    /**
     * 允许读写的数据分组白名单
     *
     * @var array<int, string>
     */
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

    /**
     * 判断分组是否在白名单内
     *
     * @param string $section
     *
     * @return bool
     */
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

        // 兼容性修复：选项不存在时 get_option() 返回 false，
        // 直接写入下标会触发 PHP 8.1 的「false 自动转数组」弃用警告
        if (! is_array($options)) {
            $options = array();
        }

        $options[$option] = $value;
        update_option($section, $options);

        return self::get_option($option, $section, "NO_DATA") != "NO_DATA";
    }
}
