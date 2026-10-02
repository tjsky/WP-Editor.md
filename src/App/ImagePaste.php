<?php
/**
 * 图片上传接口
 */

namespace EditormdApp;

use EditormdUtils\Config;
use EditormdUtils\Ajax;

/**
 * json数据结构：
 * - url    : 返回的图片地址，如果存在error可以为空
 * 
 * - error  : 返回的错误信息，可能值如下：
 *  1. file_extension_error: 文件扩展名错误，无论图片本身是什么格式，传递上来都应为image/png，致命错误，不返回图片地址
 *  2. file_too_large: 文件过大，常见于第三方图床，不返回图片地址
 *  3. file_repeated: 文件重复，常见于第三方图床，返回正确的图片地址
 *  4. unknown_error: 未知错误，详见detail
 *  5. permission_denied: 当前用户无上传权限（本次安全加固新增）
 * 
 * - detail : 其他信息，可能为以下几种格式：
 *  1. 在第三方图床请求正常的情况下，返回第三方图床返回的raw数据
 *  2. 在出现未知错误时，返回错误内容
 *
 * 安全加固说明（10.3.0）：
 *  1. 原实现完全没有权限校验与 CSRF 校验，任意可登录用户（含订阅者）均可向 uploads 写入任意文件；
 *  2. 原实现直接把 base64 解码结果写入 uploads，不校验内容是否为真实图片；
 *  3. 原实现没有任何体积上限；
 *  4. 原实现只 catch \Exception，PHP 8 下 imagecreatefrompng() 失败的 TypeError 会绕过捕获，
 *     导致 500 并残留已落盘文件。
 */
class ImagePaste {
    /*
     * 单次粘贴允许的最大载荷长度（base64 字符串长度上限，约对应 6MB 二进制）
     */
    const MAX_PAYLOAD_BYTES = 8388608;

    /*
     * 允许的图片 MIME 白名单（以文件内容探测结果为准，不信任客户端声明）
     */
    private static $allowed_mimes = array(
        "image/png",
        "image/jpeg",
        "image/gif",
        "image/webp",
    );

    /*
     * WordPress预置常量
     */
    private $uploadUrl;
    private $uploadDir;
    private $tempDir;

    /*
     * 与图片相关的变量
     */
    private $extension;             // 保存的文件扩展名，一般为png
    private $name;                  // 文件名，是所传递base64的md5值
    private $content;               // 文件二进制数据（base64 原文）

    /**
     * @var array 响应结构
     * 原实现直接写 $this->result 但未声明该属性，PHP 8.2 起「创建动态属性」已弃用
     */
    private $result = array();

    public function __construct() {
        add_action("wp_ajax_wp_editormd_imagepaste", array($this, "editormd_imagepaste_action_callback"));
    }

    // 入口方法，根据配置进行下一步路由
    public function editormd_imagepaste_action_callback() {
        // 1. 采集用户上传的数据信息
        try {
            $this->result = array("error" => "");

            $upload = wp_upload_dir();
            if (! empty($upload["error"])) {
                Ajax::editormd_return_json("", "unknown_error", $upload["error"]);
            }

            $this->uploadUrl = $upload["url"];
            $this->uploadDir = $upload["path"];
            $this->tempDir   = trailingslashit(get_temp_dir());

            // 安全修复：解析请求前先做数据类型判断，避免 PHP 8 下的 Undefined array key / 类型错误
            if (! isset($_REQUEST["dataurl"]) || ! is_string($_REQUEST["dataurl"])) {
                Ajax::editormd_return_json("", "unknown_error", "Missing or invalid dataurl");
            }

            $dataurl = wp_unslash($_REQUEST["dataurl"]);

            // 安全修复：新增体积上限（原实现无任何限制，可被打爆磁盘与内存）
            if (strlen($dataurl) > self::MAX_PAYLOAD_BYTES) {
                Ajax::editormd_return_json("", "file_too_large", "");
            }

            // 严格按 RFC 2397 data URL 结构解析，避免 explode() 少段时产生 Undefined offset
            $parts = explode(";", $dataurl, 2);
            if (count($parts) < 2) {
                Ajax::editormd_return_json("", "unknown_error", "Malformed dataurl");
            }
            list($data, $image) = $parts;

            $data_parts = explode(":", $data, 2);
            if (count($data_parts) < 2) {
                Ajax::editormd_return_json("", "unknown_error", "Malformed dataurl header");
            }
            list($field, $type) = $data_parts;

            $image_parts = explode(",", $image, 2);
            if (count($image_parts) < 2) {
                Ajax::editormd_return_json("", "unknown_error", "Malformed dataurl body");
            }
            list($encoding, $this->content) = $image_parts;

            // 安全修复：仅接受 base64 编码，避免其它编码绕过后续校验
            if ("base64" !== strtolower(trim($encoding))) {
                Ajax::editormd_return_json("", "file_extension_error");
            }

            // 客户端声明的 MIME 只做粗筛，真实类型由 detect_image_mime() 以文件内容为准判定
            $declared = strtolower(trim($type));
            if (! in_array($declared, self::$allowed_mimes, true) && "image/jpg" !== $declared) {
                Ajax::editormd_return_json("", "file_extension_error");
            }

            $this->extension = "png";
            $this->name      = "wp_editor_md_" . md5($dataurl);
        } catch (\Throwable $e) {
            Ajax::editormd_return_json("", "unknown_error", "Exception occurred when parsing requests:" . $e->getMessage());
        }

        // 2. 权限与 CSRF 校验（安全修复：原实现完全没有这两步）
        //    nonce 由 assets/Config/editormd-nonce.js 垫片通过 _wpnonce 参数注入，
        //    该垫片以 wp_localize_script 下发的 _Editormd.imagepasteNonce 为取值来源。
        if (! current_user_can("upload_files")) {
            Ajax::editormd_return_json("", "permission_denied", "");
        }

        check_ajax_referer("wp_editormd_imagepaste", "_wpnonce");

        // 3. 选择上传目的地
        try {
            switch (Config::get_option("imagepaste_sm", "editor_basics")) {
                case "on":
                    $this->editormd_imagepaste_smms();
                    break;
                default:
                    $this->editormd_imagepaste_save();
                    break;
            }
        } catch (\Throwable $e) {
            // 安全修复：原先只 catch \Exception，PHP 8 下的 TypeError/Error 会直接 500 并残留文件
            Ajax::editormd_return_json("", "unknown_error", "Exception occurred when uploading:" . $e->getMessage());
        }
    }

    // 存储图片到本地
    private function editormd_imagepaste_save() {
        $tempFile = $this->editormd_save_to_temp_dir();
        if (false === $tempFile) {
            Ajax::editormd_return_json("", "file_extension_error");
        }

        $this->extension = "jpg";
        $finalName       = $this->name . "." . $this->extension;
        $targetUrl       = trailingslashit($this->uploadUrl) . $finalName;

        // 同一张图片重复粘贴时复用已有文件，保持原有幂等语义
        if (file_exists(trailingslashit($this->uploadDir) . $finalName)) {
            wp_delete_file($tempFile);
            Ajax::editormd_return_json($targetUrl);
        }

        $bits = file_get_contents($tempFile);
        wp_delete_file($tempFile);

        if (false === $bits) {
            Ajax::editormd_return_json("", "unknown_error", "Failed to read converted image");
        }

        // 安全修复：改用 WordPress 官方 API 落盘（自动处理目录创建、权限与重名），
        // 替代原先直接 file_put_contents() 写入 uploads 的做法
        $uploaded = wp_upload_bits($finalName, null, $bits);
        if (! empty($uploaded["error"])) {
            Ajax::editormd_return_json("", "unknown_error", $uploaded["error"]);
        }

        Ajax::editormd_return_json($uploaded["url"]);
    }

    // 上传图片到sm.ms
    private function editormd_imagepaste_smms() {
        $tempFile = $this->editormd_save_to_temp_dir();
        if (false === $tempFile) {
            Ajax::editormd_return_json("", "file_extension_error");
        }

        // 获取用户配置中的图床校验码
        $authToken = Config::get_option("imagepaste_sm_token", "editor_basics");
        $headers   = array();
        if ($authToken !== "") {
            $headers["Authorization"] = $authToken;
        }

        $multipart = $this->build_multipart_body($tempFile, basename($tempFile), array("format" => "json"));
        wp_delete_file($tempFile);

        if (false === $multipart) {
            Ajax::editormd_return_json("", "unknown_error", "Failed to read image payload");
        }

        list($body, $contentType) = $multipart;
        $headers["Content-Type"]  = $contentType;

        // 安全修复：改用 WordPress 官方 HTTP API（wp_remote_post），
        // 自带证书校验与超时控制。原实现使用 curl 并显式关闭了
        // CURLOPT_SSL_VERIFYPEER / CURLOPT_SSL_VERIFYHOST，存在中间人风险。
        $response = wp_remote_post("https://smms.app/api/v2/upload", array(
            "timeout"     => 120,
            "redirection" => 0,
            "sslverify"   => true,
            "headers"     => $headers,
            "body"        => $body,
        ));

        if (is_wp_error($response)) {
            Ajax::editormd_return_json("", "unknown_error", $response->get_error_message());
        }

        $reqCode = (int) wp_remote_retrieve_response_code($response);
        $result  = wp_remote_retrieve_body($response);

        switch ($reqCode) {
            case 200:
                $data = json_decode($result, true);
                if (! is_array($data)) {
                    Ajax::editormd_return_json("", "unknown_error", "Invalid response from sm.ms");
                }
                // 对图片重复的情况进行特殊处理
                if (isset($data["code"]) && $data["code"] === "image_repeated") {
                    $imageUrl = isset($data["images"]) ? $data["images"] : "";
                } else {
                    $imageUrl = isset($data["data"]["url"]) ? $data["data"]["url"] : "";
                }

                if ("" === $imageUrl) {
                    Ajax::editormd_return_json("", "unknown_error", $result);
                }

                Ajax::editormd_return_json($imageUrl, "", $result);
                break;
            case 413:
                Ajax::editormd_return_json("", "file_too_large", "");
                break;
            default:
                Ajax::editormd_return_json("", "unknown_error", $reqCode . $result);
                break;
        }
    }

    /**
     * 将当前读取的数据校验后保存到临时目录，并统一转换为 JPG
     *
     * 安全修复：原实现直接写入 uploads 且完全信任客户端声明的 MIME，
     * 这里改为「先落临时目录 → 以文件内容探测真实类型 → 通过白名单后再转换」。
     *
     * @return string|false 成功返回临时 JPG 文件路径，失败返回 false
     */
    private function editormd_save_to_temp_dir() {
        // strict 模式：非法 base64 直接返回 false，避免解码出半截内容
        $binary = base64_decode($this->content, true);
        if (false === $binary || "" === $binary) {
            return false;
        }

        if (! function_exists("getimagesize")) {
            return false;
        }

        $tempFile = $this->tempDir . $this->name . ".tmp";
        if (false === @file_put_contents($tempFile, $binary)) {
            return false;
        }

        $mime = $this->detect_image_mime($tempFile);
        if (false === $mime) {
            wp_delete_file($tempFile);
            return false;
        }

        // 源文件本身就是 JPEG 时无需转换，直接沿用
        if ("image/jpeg" === $mime) {
            $newFilename = preg_replace("/\.tmp$/", ".jpg", $tempFile);
            if (false === @rename($tempFile, $newFilename)) {
                wp_delete_file($tempFile);
                return false;
            }

            return $newFilename;
        }

        $newFilename = $this->editormd_png2jpg($tempFile, true, $mime);

        return $newFilename;
    }

    /**
     * 以文件内容探测真实图片类型
     *
     * @param string $file
     *
     * @return string|false MIME 或 false
     */
    private function detect_image_mime($file) {
        $info = @getimagesize($file);
        if (false === $info || empty($info["mime"])) {
            return false;
        }

        if (! in_array($info["mime"], self::$allowed_mimes, true)) {
            return false;
        }

        return $info["mime"];
    }

    /**
     * 将图片转换为 JPG 以减小体积
     *
     * 安全修复：原实现未判断 imagecreatefrompng() 的返回值，遇到非 PNG 输入时
     * imagesx(false) 会抛 TypeError（PHP 8 下属 \Error，原 catch (\Exception) 捕获不到），
     * 导致 500 且残留已落盘文件。此处补齐返回值判断与失败清理，并按真实类型选择解码器。
     *
     * @param string $filePath
     * @param bool   $deleteOldFile
     * @param string $mime
     *
     * @return string|false 成功返回新文件路径，失败返回 false
     */
    private function editormd_png2jpg($filePath, $deleteOldFile = true, $mime = "image/png") {
        $quality     = 50;
        $newFilename = preg_replace("/\.(png|tmp)$/i", ".jpg", $filePath);

        $image = $this->image_create_from($filePath, $mime);
        if (false === $image) {
            if ($deleteOldFile) {
                wp_delete_file($filePath);
            }
            return false;
        }

        $width  = imagesx($image);
        $height = imagesy($image);

        $bg = imagecreatetruecolor($width, $height);
        if (false === $bg) {
            imagedestroy($image);
            if ($deleteOldFile) {
                wp_delete_file($filePath);
            }
            return false;
        }

        imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
        imagealphablending($bg, true);
        imagecopy($bg, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        $saved = imagejpeg($bg, $newFilename, $quality);
        imagedestroy($bg);

        if (false === $saved) {
            if ($deleteOldFile) {
                wp_delete_file($filePath);
            }
            return false;
        }

        if ($deleteOldFile && file_exists($filePath) && $filePath !== $newFilename) {
            wp_delete_file($filePath);
        }

        return $newFilename;
    }

    /**
     * 按真实 MIME 选择图像解码器
     *
     * @return resource|\GdImage|false
     */
    private function image_create_from($file, $mime) {
        switch ($mime) {
            case "image/png":
                return @imagecreatefrompng($file);
            case "image/jpeg":
                return @imagecreatefromjpeg($file);
            case "image/gif":
                return @imagecreatefromgif($file);
            case "image/webp":
                if (function_exists("imagecreatefromwebp")) {
                    return @imagecreatefromwebp($file);
                }
                return false;
        }

        return false;
    }

    /**
     * 手工构造 multipart/form-data 请求体
     *
     * 原实现依赖 curl + CURLFile（此方式无法在不关闭证书校验的前提下改写为 wp_remote_post），
     * 这里改为手工构造请求体，从而彻底移除 curl 依赖与关闭 TLS 校验的做法。
     *
     * @param string $filePath
     * @param string $fileName
     * @param array  $fields
     *
     * @return array|false array($body, $contentType) 或 false
     */
    private function build_multipart_body($filePath, $fileName, $fields) {
        $contents = file_get_contents($filePath);
        if (false === $contents) {
            return false;
        }

        $boundary = "----WPEditormdBoundary" . md5(microtime(true) . wp_rand());
        $eol      = "\r\n";
        $body     = "";

        foreach ($fields as $key => $value) {
            $body .= "--" . $boundary . $eol
                . 'Content-Disposition: form-data; name="' . $key . '"' . $eol . $eol
                . $value . $eol;
        }

        $body .= "--" . $boundary . $eol
            . 'Content-Disposition: form-data; name="smfile"; filename="' . sanitize_file_name($fileName) . '"' . $eol
            . 'Content-Type: image/jpeg' . $eol . $eol
            . $contents . $eol
            . "--" . $boundary . "--" . $eol;

        return array($body, "multipart/form-data; boundary=" . $boundary);
    }
}
