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
 *
 * 10.3.0 修复说明：
 *   1. 原实现的私有方法 save_log() 是**空实现**（直接返回 null），
 *      导致 save_log_router() 返回 null，__callStatic() 继续往下走到
 *      `isset(self::$$method)` 分支（未定义的变量静态属性）后直接抛异常。
 *      也就是说 Logger::info()/warn()/error() 等全部调用都会抛异常。
 *      现已补齐 save_log() 实现。
 *   2. 移除了 `self::$$method` + call_user_func_array() 的变量静态属性调用写法。
 *   3. set_log_level() 遇到非法值不再抛异常，改为记录告警并保持原级别，
 *      避免一个可被外部输入触发的异常影响页面渲染。
 *
 * 落盘策略说明：
 *   日志统一写入 PHP 错误日志（error_log()），不再自行在 web 可访问目录落盘。
 *   原因是插件无法保证运行环境为 Apache——在 Nginx 下 .htaccess 会被忽略，
 *   自行落盘的日志文件可能被直接下载，反而引入新的信息泄漏面。
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
     *
     * 兼容性/健壮性修复：非法等级不再抛出未捕获异常，仅记录一条告警。
     *
     * @param string $log_level 日志等级
     *
     * @return bool 是否设置成功
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

    /**
     * @method static 获取当前日志等级
     *
     * @return string
     */
    public static function get_log_level() {
        return self::$current_log_level;
    }

    /**
     * @method static __callStatic 重载PHP静态方法调用的魔术方法
     * 通过中间件的形式将 $method 与 $args 传递给需要批量生成方法的功能
     *
     * @param string $method 方法名
     * @param array<mixed> $args 参数列表
     *
     * @throws Exception 方法真的不存在，报错提示参考PHP方法不存在的提示
     *
     * @return mixed 实际调用函数的结果
     */
    public static function __callStatic($method, $args) {
        $result = self::save_log_router($method, $args);
        if (null !== $result) {
            return $result;
        }

        // 修复：原实现会走到 `isset(self::$$method)`（变量静态属性调用）后抛异常，
        // 这既是无效代码也是潜在的危险调用模式。现直接给出明确报错。
        throw new Exception(
            sprintf("Call to undefined method %s::%s", "Logger", $method)
        );
    }

    /**
     * @method static 写入日志-路由
     *
     * 接受从 __callStatic 传递来的调用请求，如果属于合法日志等级则转发给 self::write
     *
     * @param string $method
     * @param array  $args
     *
     * @return bool|null 是否落盘成功；非日志等级方法返回 null
     */
    public static function save_log_router($method, $args) {
        if (! is_string($method) || ! in_array($method, self::$log_level_enum, true)) {
            return null;
        }

        $content = empty($args) ? "" : $args[0];

        return self::write($method, $content, self::get_context());
    }

    /**
     * @method private static 写入日志
     *
     * 修复：原 save_log() 为空实现，导致所有日志调用最终抛异常。
     *
     * @param string $log_level   日志等级
     * @param mixed  $log_content 日志内容（非字符串会被 JSON 序列化）
     * @param array  $log_context 日志上下文
     *
     * @return bool 是否写入成功
     */
    private static function write($log_level, $log_content, $log_context = array()) {
        if (! in_array($log_level, self::$log_level_enum, true)) {
            return false;
        }

        // 低于当前等级的日志直接丢弃
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
     *
     * @return array 相关上下文
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
