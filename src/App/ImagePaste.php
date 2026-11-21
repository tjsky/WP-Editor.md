<?php
/**
 * 图片上传接口
 */

namespace EditormdApp;

use EditormdUtils\Ajax;
use EditormdUtils\Config;

/**
 * json数据结构：
 * - url    : 返回的图片地址，如果存在error可以为空
 * 
 * - error  : 返回的错误信息，可能值如下：
 *  1. file_extension_error: 文件扩展名错误，无论图片本身是什么格式，传递上来都应为image/png，致命错误，不返回图片地址
 * 
 */
class ImagePaste {
    const MAX_PAYLOAD_BYTES = 8388608;

    const MAX_BINARY_BYTES = 6291456;

    const MAX_PIXELS = 10000000;

    const MAX_SIDE = 8192;

    const MEMORY_HEADROOM_BYTES = 16777216;

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

    /*
     * 与图片相关的变量
     */
    private $extension;             // 保存的文件扩展名，一般为png
    private $name;                  // 文件名，是所传递base64的md5值
    private $content;               // 文件二进制数据（base64 原文）

    private $result = array();

    private $last_error = "";

    private $uploaded_exif = null;

    public function __construct() {
        add_action("wp_ajax_wp_editormd_imagepaste", array($this, "editormd_imagepaste_action_callback"));
        add_action("wp_ajax_wp_editormd_imageupload", array($this, "editormd_imageupload_action_callback"));
    }

    public function editormd_imageupload_action_callback() {
        try {
            if (! current_user_can("upload_files")) {
                Ajax::editormd_return_json("", "permission_denied", "");
            }

            check_ajax_referer("wp_editormd_imagepaste", "_wpnonce");

            if ($this->editormd_rate_limit_exceeded()) {
                Ajax::editormd_return_json("", "rate_limited", "");
            }

            if (empty($_FILES["file"]) || ! is_array($_FILES["file"])) {
                Ajax::editormd_return_json("", "unknown_error", "Missing file");
            }

            $file = $_FILES["file"];

            if (! empty($file["error"])) {
                Ajax::editormd_return_json("", "unknown_error", "Upload error code " . (int) $file["error"]);
            }

            if (empty($file["tmp_name"]) || ! is_uploaded_file($file["tmp_name"])) {
                Ajax::editormd_return_json("", "unknown_error", "Invalid upload");
            }

            $size = isset($file["size"]) ? (int) $file["size"] : 0;
            if ($size <= 0 || $size > self::MAX_BINARY_BYTES) {
                Ajax::editormd_return_json("", "file_too_large", "");
            }

            $info = @getimagesize($file["tmp_name"]);
            if (false === $info || empty($info["mime"]) || ! in_array($info["mime"], self::$allowed_mimes, true)) {
                Ajax::editormd_return_json("", "file_extension_error");
            }

            if (! $this->image_dimensions_within_budget((int) $info[0], (int) $info[1])) {
                Ajax::editormd_return_json("", "image_too_large", "");
            }

            require_once ABSPATH . "wp-admin/includes/file.php";
            require_once ABSPATH . "wp-admin/includes/media.php";
            require_once ABSPATH . "wp-admin/includes/image.php";

            $overrides = array(
                "test_form" => false,
                "test_type" => true,
                "mimes"     => array(
                    "png"  => "image/png",
                    "jpg"  => "image/jpeg",
                    "jpeg" => "image/jpeg",
                    "gif"  => "image/gif",
                    "webp" => "image/webp",
                ),
            );

            if ("off" === Config::get_option("image_to_library", "editor_image")) {
                $_FILES["file"] = $this->maybe_compress_upload($file);
                $moved = wp_handle_upload($_FILES["file"], $overrides);
                if (! empty($moved["error"])) {
                    Ajax::editormd_return_json("", "unknown_error", $moved["error"]);
                }

                Ajax::editormd_return_json($moved["url"]);
            }

            $_FILES["file"] = $this->maybe_compress_upload($file);

            $attachment_id = media_handle_upload("file", 0, array(), $overrides);
            if (is_wp_error($attachment_id)) {
                Ajax::editormd_return_json("", "unknown_error", $attachment_id->get_error_message());
            }

            $this->apply_exif_to_attachment($attachment_id, $this->uploaded_exif);

            Ajax::editormd_return_json(wp_get_attachment_url($attachment_id), "", "", array("id" => (int) $attachment_id));
        } catch (\Throwable $e) {
            Ajax::editormd_return_json("", "unknown_error", "Exception occurred when uploading:" . $e->getMessage());
        }
    }

    private function maybe_compress_upload($file) {
        if ($this->keep_original_format()) {
            return $file;
        }

        $target = $this->target_format();
        if ("" === $target) {
            return $file;
        }

        if (empty($file["tmp_name"]) || ! is_uploaded_file($file["tmp_name"])) {
            return $file;
        }

        $info = @getimagesize($file["tmp_name"]);
        if (false === $info || empty($info["mime"])) {
            return $file;
        }

        if ("image/gif" === $info["mime"] && $this->is_animated_gif($file["tmp_name"])) {
            return $file;
        }

        $this->uploaded_exif = $this->read_exif_meta($file["tmp_name"]);

        $converted = $this->convert_image($file["tmp_name"], $info["mime"], $target);
        if (false === $converted) {
            return $file;
        }

        $original = $file["tmp_name"];
        if (false === @copy($converted, $original)) {
            wp_delete_file($converted);
            return $file;
        }
        wp_delete_file($converted);

        $name = isset($file["name"]) ? $file["name"] : "image";
        $name = preg_replace("/\.[^.]+$/", "", $name);

        $file["tmp_name"] = $original;
        $file["name"]     = $name . "." . $target;
        $file["type"]     = "jpg" === $target ? "image/jpeg" : "image/" . $target;
        $file["size"]     = (int) filesize($original);
        $file["error"]    = 0;

        return $file;
    }

    private function read_exif_meta($file) {
        if (! function_exists("wp_read_image_metadata")) {
            require_once ABSPATH . "wp-admin/includes/image.php";
        }

        if (! function_exists("wp_read_image_metadata")) {
            return null;
        }

        $meta = wp_read_image_metadata($file);

        return (is_array($meta) && ! empty($meta)) ? $meta : null;
    }

    private function apply_exif_to_attachment($attachment_id, $image_meta = null) {
        if (! is_array($image_meta) || empty($image_meta)) {
            $meta = wp_get_attachment_metadata($attachment_id);
            if (! is_array($meta) || empty($meta["image_meta"]) || ! is_array($meta["image_meta"])) {
                return;
            }
            $image_meta = $meta["image_meta"];
        }

        $post = get_post($attachment_id);
        if (! $post || "" !== trim((string) $post->post_excerpt)) {
            return;
        }

        $description = "";
        if (! empty($image_meta["caption"])) {
            $description = (string) $image_meta["caption"];
        } elseif (! empty($image_meta["title"])) {
            $description = (string) $image_meta["title"];
        }

        if ("" === trim($description)) {
            return;
        }

        wp_update_post(array(
            "ID"           => $attachment_id,
            "post_excerpt" => sanitize_text_field($description),
        ));
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
            $this->editormd_imagepaste_save();
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

        $extension = strtolower(pathinfo($tempFile, PATHINFO_EXTENSION));
        if (! in_array($extension, array("jpg", "png", "gif", "webp"), true)) {
            $extension = "jpg";
        }

        $this->extension = $extension;
        $finalName       = $this->name . "." . $this->extension;
        $targetUrl       = trailingslashit($this->uploadUrl) . $finalName;

        if (file_exists(trailingslashit($this->uploadDir) . $finalName)) {
            wp_delete_file($tempFile);
            $existing = $this->maybe_register_attachment(trailingslashit($this->uploadDir) . $finalName, $targetUrl);
            Ajax::editormd_return_json($targetUrl, "", "", $existing ? array("id" => $existing) : array());
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

        $attachment_id = $this->maybe_register_attachment($uploaded["file"], $uploaded["url"]);

        Ajax::editormd_return_json($uploaded["url"], "", "", $attachment_id ? array("id" => $attachment_id) : array());
    }

    private function maybe_register_attachment($file, $url) {
        if ("off" === Config::get_option("image_to_library", "editor_image")) {
            return 0;
        }

        if (function_exists("attachment_url_to_postid")) {
            $existing = attachment_url_to_postid($url);
            if ($existing) {
                return (int) $existing;
            }
        }

        if (! $file || ! file_exists($file)) {
            return 0;
        }

        if (! function_exists("wp_generate_attachment_metadata")) {
            require_once ABSPATH . "wp-admin/includes/image.php";
        }

        $filetype = wp_check_filetype($file);

        $attachment_id = wp_insert_attachment(array(
            "post_mime_type" => $filetype["type"] ? $filetype["type"] : "image/jpeg",
            "post_title"     => sanitize_text_field(pathinfo($file, PATHINFO_FILENAME)),
            "post_content"   => "",
            "post_status"    => "inherit",
        ), $file);

        if (is_wp_error($attachment_id) || ! $attachment_id) {
            return 0;
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $file);
        if (! empty($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        return (int) $attachment_id;
    }

    private function pasted_image_quality() {
        $quality = (int) Config::get_option("image_quality", "editor_image");

        return ($quality >= 1 && $quality <= 100) ? $quality : 80;
    }

    private function keep_original_format() {
        return "off" !== Config::get_option("image_keep_origin", "editor_image");
    }

    private function target_format() {
        $format = strtolower(trim((string) Config::get_option("image_format", "editor_image")));
        if (! in_array($format, array("jpg", "png", "webp"), true)) {
            $format = "webp";
        }

        if ($this->can_write_format($format)) {
            return $format;
        }

        return $this->can_write_format("jpg") ? "jpg" : "";
    }

    private function can_write_format($format) {
        switch ($format) {
            case "png":
                return function_exists("imagepng");
            case "webp":
                return function_exists("imagewebp");
            case "jpg":
                return function_exists("imagejpeg");
        }

        return false;
    }

    private function extension_for_mime($mime) {
        $map = array(
            "image/png"  => "png",
            "image/gif"  => "gif",
            "image/webp" => "webp",
            "image/jpeg" => "jpg",
        );

        return isset($map[$mime]) ? $map[$mime] : "jpg";
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

        if ($this->keep_original_format()) {
            $newFilename = $tempFile . "." . $this->extension_for_mime($mime);
            if (false === @rename($tempFile, $newFilename)) {
                wp_delete_file($tempFile);
                $this->last_error = "unknown_error";
                return false;
            }

            return $newFilename;
        }

        if ("image/gif" === $mime && $this->is_animated_gif($tempFile)) {
            $newFilename = $tempFile . ".gif";
            if (false === @rename($tempFile, $newFilename)) {
                wp_delete_file($tempFile);
                $this->last_error = "unknown_error";
                return false;
            }

            return $newFilename;
        }

        $target = $this->target_format();
        if ("" !== $target) {
            $converted = $this->convert_image($tempFile, $mime, $target);
            if (false !== $converted) {
                return $converted;
            }
        }

        $newFilename = $tempFile . "." . $this->extension_for_mime($mime);
        if (false === @rename($tempFile, $newFilename)) {
            wp_delete_file($tempFile);
            $this->last_error = "unknown_error";
            return false;
        }

        return $newFilename;
    }

    private function is_animated_gif($file) {
        $handle = @fopen($file, "rb");
        if (! $handle) {
            return false;
        }

        $frames = 0;
        while (! feof($handle) && $frames < 2) {
            $chunk = fread($handle, 102400);
            if (false === $chunk || "" === $chunk) {
                break;
            }
            $frames += (int) preg_match_all("#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s", $chunk);
        }

        fclose($handle);

        return $frames > 1;
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

    private function convert_image($filePath, $mime, $target) {
        $quality = $this->pasted_image_quality();

        $image = $this->image_create_from($filePath, $mime);
        if (false === $image) {
            return false;
        }

        $image = $this->apply_orientation($image, $filePath);

        $width       = imagesx($image);
        $height      = imagesy($image);
        $newFilename = $filePath . "." . $target;

        if ("png" === $target) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $saved = @imagepng($image, $newFilename, 9);
            imagedestroy($image);
        } elseif ("webp" === $target) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $saved = @imagewebp($image, $newFilename, $quality);
            imagedestroy($image);
        } else {
            $background = imagecreatetruecolor($width, $height);
            if (false === $background) {
                imagedestroy($image);
                return false;
            }

            imagefill($background, 0, 0, imagecolorallocate($background, 255, 255, 255));
            imagealphablending($background, true);
            imagecopy($background, $image, 0, 0, 0, 0, $width, $height);
            imagedestroy($image);

            $saved = @imagejpeg($background, $newFilename, $quality);
            imagedestroy($background);
        }

        if (false === $saved) {
            if (file_exists($newFilename)) {
                wp_delete_file($newFilename);
            }
            return false;
        }

        if ($filePath !== $newFilename && file_exists($filePath)) {
            wp_delete_file($filePath);
        }

        return $newFilename;
    }

    private function apply_orientation($image, $file) {
        if (! function_exists("exif_read_data")) {
            return $image;
        }

        $exif = @exif_read_data($file);
        if (! is_array($exif) || empty($exif["Orientation"])) {
            return $image;
        }

        switch ((int) $exif["Orientation"]) {
            case 3:
                $angle = 180;
                break;
            case 6:
                $angle = -90;
                break;
            case 8:
                $angle = 90;
                break;
            default:
                return $image;
        }

        $filler  = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $rotated = @imagerotate($image, $angle, $filler);
        if (false === $rotated) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
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
}
