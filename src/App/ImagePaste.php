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
 * 
 * - detail : 其他信息，可能为以下几种格式：
 *  1. 在第三方图床请求正常的情况下，返回第三方图床返回的raw数据
 *  2. 在出现未知错误时，返回错误内容
 */
class ImagePaste {
    const MAX_PAYLOAD_BYTES = 8388608;

    const MAX_BINARY_BYTES = 6291456;

    const MAX_PIXELS = 10000000;

    const MAX_SIDE = 8192;

    const MEMORY_HEADROOM_BYTES = 16777216;

    const UPLOAD_TIMEOUT  = 10;
    const CONNECT_TIMEOUT = 3;

    const RATE_LIMIT_MAX    = 30;
    const RATE_LIMIT_WINDOW = 60;

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

    private $result = array();

    private $last_error = "";

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

            if (! isset($_REQUEST["dataurl"]) || ! is_string($_REQUEST["dataurl"])) {
                Ajax::editormd_return_json("", "unknown_error", "Missing or invalid dataurl");
            }

            $dataurl = wp_unslash($_REQUEST["dataurl"]);

            if (strlen($dataurl) > self::MAX_PAYLOAD_BYTES) {
                Ajax::editormd_return_json("", "file_too_large", "");
            }

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

            if ("base64" !== strtolower(trim($encoding))) {
                Ajax::editormd_return_json("", "file_extension_error");
            }

            $declared = strtolower(trim($type));
            if (! in_array($declared, self::$allowed_mimes, true) && "image/jpg" !== $declared) {
                Ajax::editormd_return_json("", "file_extension_error");
            }

            $this->extension = "png";
            $this->name      = "wp_editor_md_" . md5($dataurl);
        } catch (\Throwable $e) {
            Ajax::editormd_return_json("", "unknown_error", "Exception occurred when parsing requests:" . $e->getMessage());
        }

        if (! current_user_can("upload_files")) {
            Ajax::editormd_return_json("", "permission_denied", "");
        }

        check_ajax_referer("wp_editormd_imagepaste", "_wpnonce");

        if ($this->editormd_rate_limit_exceeded()) {
            Ajax::editormd_return_json("", "rate_limited", "");
        }

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
            Ajax::editormd_return_json("", "unknown_error", "Exception occurred when uploading:" . $e->getMessage());
        }
    }

    // 存储图片到本地
    private function editormd_imagepaste_save() {
        $tempFile = $this->editormd_save_to_temp_dir();
        if (false === $tempFile) {
            Ajax::editormd_return_json("", $this->last_error !== "" ? $this->last_error : "file_extension_error");
        }

        $this->extension = "jpg";
        $finalName       = $this->name . "." . $this->extension;
        $targetUrl       = trailingslashit($this->uploadUrl) . $finalName;

        if (file_exists(trailingslashit($this->uploadDir) . $finalName)) {
            wp_delete_file($tempFile);
            Ajax::editormd_return_json($targetUrl);
        }

        $bits = file_get_contents($tempFile);
        wp_delete_file($tempFile);

        if (false === $bits) {
            Ajax::editormd_return_json("", "unknown_error", "Failed to read converted image");
        }

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
            Ajax::editormd_return_json("", $this->last_error !== "" ? $this->last_error : "file_extension_error");
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

        $connect_timeout = function ($handle) {
            if (defined("CURLOPT_CONNECTTIMEOUT") && function_exists("curl_setopt")) {
                curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
            }
        };
        add_action("http_api_curl", $connect_timeout);

        $response = wp_remote_post("https://smms.app/api/v2/upload", array(
            "timeout"     => self::UPLOAD_TIMEOUT,
            "redirection" => 0,
            "sslverify"   => true,
            "headers"     => $headers,
            "body"        => $body,
        ));

        remove_action("http_api_curl", $connect_timeout);

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

    private function editormd_save_to_temp_dir() {
        $binary = base64_decode($this->content, true);
        if (false === $binary || "" === $binary) {
            $this->last_error = "file_extension_error";
            return false;
        }

        if (strlen($binary) > self::MAX_BINARY_BYTES) {
            $this->last_error = "file_too_large";
            return false;
        }

        if (! function_exists("getimagesize")) {
            $this->last_error = "unknown_error";
            return false;
        }

        if (! function_exists("wp_tempnam")) {
            require_once ABSPATH . "wp-admin/includes/file.php";
        }

        $tempFile = wp_tempnam($this->name . ".tmp");
        if (! $tempFile) {
            $this->last_error = "unknown_error";
            return false;
        }

        if (false === @file_put_contents($tempFile, $binary)) {
            wp_delete_file($tempFile);
            $this->last_error = "unknown_error";
            return false;
        }

        $info = $this->image_info($tempFile);
        if (false === $info) {
            wp_delete_file($tempFile);
            $this->last_error = "file_extension_error";
            return false;
        }

        $mime = $info["mime"];

        if (! $this->image_dimensions_within_budget((int) $info[0], (int) $info[1])) {
            wp_delete_file($tempFile);
            $this->last_error = "image_too_large";
            return false;
        }

        if ("image/jpeg" === $mime) {
            $newFilename = $tempFile . ".jpg";
            if (false === @rename($tempFile, $newFilename)) {
                wp_delete_file($tempFile);
                $this->last_error = "unknown_error";
                return false;
            }

            return $newFilename;
        }

        return $this->editormd_png2jpg($tempFile, true, $mime);
    }

    private function image_info($file) {
        $info = @getimagesize($file);
        if (false === $info || empty($info["mime"])) {
            return false;
        }

        if (! in_array($info["mime"], self::$allowed_mimes, true)) {
            return false;
        }

        return $info;
    }

    private function image_dimensions_within_budget($width, $height) {
        if ($width <= 0 || $height <= 0) {
            return false;
        }

        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            return false;
        }

        if (($width * $height) > self::MAX_PIXELS) {
            return false;
        }

        $limit = function_exists("wp_convert_hr_to_bytes")
            ? (int) wp_convert_hr_to_bytes(ini_get("memory_limit"))
            : 0;

        if ($limit > 0) {
            $needed = $width * $height * 4 * 2 + self::MEMORY_HEADROOM_BYTES;
            $free   = $limit - memory_get_usage(true);

            if ($free <= 0 || $needed > ($free * 0.8)) {
                return false;
            }
        }

        return true;
    }

    private function editormd_rate_limit_exceeded() {
        $user_id = get_current_user_id();
        if (! $user_id) {
            return false;
        }

        $key   = "wp_editormd_rl_" . $user_id;
        $count = (int) get_transient($key);

        if ($count >= self::RATE_LIMIT_MAX) {
            return true;
        }

        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);

        return false;
    }

    private function editormd_png2jpg($filePath, $deleteOldFile = true, $mime = "image/png") {
        $quality = 50;

        $newFilename = $filePath . ".jpg";

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
