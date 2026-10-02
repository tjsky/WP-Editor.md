<?php

namespace EditormdUtils;
use Exception;

/**
 * 日志类，用于进行日志的读取和写入
 * 接受静态调用，无需实例化，减少各模块代码量
 * 日志等级：
 * - verbose : 所有调试信息，包括模块生命周期、方法调用、网络请求发送与接收
 * - info    : 基础生命周期、RESTful接口请求、脚本加载、性能报告
 * - warn    : 获取数据与预期不一致、不推荐使用或被弃用的配置与操作
 * - error   : 可能会引起系统工作不正常，通常通过try...catch捕获
 * - fatal   : 已经引起系统出现故障，无法捕获的错误
 */
class Logger {
    /**
     * @var array<string> 日志等级枚举
     */
    private static $log_level_enum = ['verbose', 'info', 'warn', 'error', 'fatal'];

    /**
     * @var string 当前日志等级
     * 从verbose到fatal，大于等于当前等级的日志将会被记录落盘
     */
    private static $current_log_level = 'info';

    /**
     * @method static 配置日志等级
     * @param string $log_level 日志等级
     */
    public static function set_log_level($log_level) {
        if (is_string($log_level) && in_array($log_level, self::$log_level_enum, true)) {
            self::$current_log_level = $log_level;
            return true;
        }

        self::write(
            'warn',
            sprintf(
                'Logger level invalid, expect one of %s, received: %s',
                json_encode(self::$log_level_enum),
                is_scalar($log_level) ? (string) $log_level : gettype($log_level)
            )
        );

        return false;
    }

    public static function get_log_level() {
        return self::$current_log_level;
    }

    /**
     * @method static __callStatic 重载PHP静态方法调用的魔术方法
     * 通过中间件的形式将$method和$args传递给需要批量生成方法的功能
     * 
     * @param string $method 方法名
     * @param array<mixed> $args 参数列表
     * 
     * @throw Exception 方法真的不存在，报错提示参考PHP方法不存在的提示
     * 
     * @return mixed 实际调用函数的结果
     */
    public static function __callStatic($method, $args) {
        $result = self::save_log_router($method, $args);
        if (null !== $result) {
            return $result;
        }

        throw new Exception(
            sprintf("Call to undefined method %s::%s", "Logger", $method)
        );
    }

    /**
     * @method static 写入日志-路由
     */
    public static function save_log_router($method, $args) {
        if (! is_string($method) || ! in_array($method, self::$log_level_enum, true)) {
            return null;
        }

        $content = empty($args) ? "" : $args[0];

        return self::write($method, $content, self::get_context());
    }

    /**
     */
    private static function write($log_level, $log_content, $log_context = array()) {
        if (! in_array($log_level, self::$log_level_enum, true)) {
            return false;
        }

        $current = array_search(self::$current_log_level, self::$log_level_enum, true);
        $target  = array_search($log_level, self::$log_level_enum, true);
        if (false === $current || false === $target || $target < $current) {
            return false;
        }

        if (! is_string($log_content)) {
            $log_content = wp_json_encode($log_content);
        }

        $line = sprintf(
            '[WP Editor.md][%s] %s',
            strtoupper($log_level),
            $log_content
        );

        if (! empty($log_context)) {
            $line .= ' | context: ' . wp_json_encode($log_context);
        }

        return (bool) error_log($line);
    }

    /**
     * @method private static 获取当前上下文，以供写入日志
     */
    private static function get_context() {
        $context = array();

        if (function_exists("wp_doing_ajax") && wp_doing_ajax()) {
            $context["ajax"] = true;
        }

        if (function_exists("is_user_logged_in") && is_user_logged_in()) {
            $context["user"] = get_current_user_id();
        }

        if (isset($_SERVER["REQUEST_METHOD"])) {
            $context["method"] = sanitize_text_field(wp_unslash($_SERVER["REQUEST_METHOD"]));
        }

        if (isset($_SERVER["REQUEST_URI"])) {
            $context["uri"] = sanitize_text_field(wp_unslash($_SERVER["REQUEST_URI"]));
        }

        if (isset($_SERVER["HTTP_USER_AGENT"])) {
            $context["ua"] = sanitize_text_field(wp_unslash($_SERVER["HTTP_USER_AGENT"]));
        }

        return $context;
    }
}

/**
 * 日志错误类，用于抛出日志功能相关错误
 */
class LoggerException extends Exception {
}
