<?php
// 版本发行注记页面
// 包含页面重渲染功能
//
// 安全加固（10.3.0）：
//   1. 原实现先按 $_GET["version"] 拼接路径并 file_get_contents()，之后才做格式校验，
//      污染输入仍会先触发一次文件读取；现改为「先校验、后读取」。
//   2. version 改为按 release-note 目录实际存在的版本做白名单匹配，从根上消除路径穿越。
//   3. $_GET["version"] 与 $_COOKIE["wp-editormd-lang"] 均未做 isset 判断，
//      PHP 8 下会触发 Undefined array key 警告。
//   4. $title 直接插入 <title> 标签、资源地址未转义，补 esc_html()/esc_url()。
//   5. 移除指向 jsdelivr CDN 的横幅图片请求（消除后台向第三方 CDN 发起的额外请求）。
//
// 10.4.2：函数改用插件前缀命名 —— 原先两个页面文件都定义了全局 display_page()，
//         任何第三方代码定义同名函数都会导致 require 时 "Cannot redeclare" 致命错误。
function wp_editormd_render_upgrade_release_page($text_domain, $config) {
    $targetVersion = isset($_GET["version"]) ? sanitize_text_field(wp_unslash($_GET["version"])) : "";
    $language      = isset($_COOKIE["wp-editormd-lang"])
        ? sanitize_text_field(wp_unslash($_COOKIE["wp-editormd-lang"]))
        : "en-US";

    // 先校验语言，避免用未净化的值参与路径拼接
    if (! preg_match("/^[a-z]{2}-[A-Z]{2}$/", $language)) {
        $language = "en-US";
    }

    // 安全修复：version 必须严格匹配语义化版本格式
    if (! preg_match("/^[0-9]{1,3}\.[0-9]{1,2}\.[0-9]{1,2}$/", $targetVersion)) {
        return null;
    }

    // 安全修复：仅允许 release-note 目录下真实存在的版本，
    // 即使格式校验被绕过也无法跳出该目录
    $releaseRoot = __DIR__ . "/release-note";
    if (! in_array($targetVersion, wp_editormd_available_release_versions($releaseRoot), true)) {
        return null;
    }

    $noteFile = $releaseRoot . "/" . $targetVersion . "/" . $language . ".md";
    if (! file_exists($noteFile)) {
        return null;
    }

    $rawText = file_get_contents($noteFile);
    if (false === $rawText || "" === $rawText) {
        return null;
    }

    $text = htmlspecialchars($rawText, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");

    $editor_style_base_address = $config::get_option("editor_addres", "editor_style");
    $title    = esc_html(sprintf(__("Successfully upgrade to version", $text_domain), $targetVersion));
    $baseUrl  = esc_url($editor_style_base_address);

    return <<<EOT
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>$title</title>
    <link rel="stylesheet" href="$baseUrl/assets/Editormd/editormd.min.css" />
    <link rel="stylesheet" href="$baseUrl/assets/Config/editormd.css" />
    <script src="$baseUrl/assets/Marked/marked.min.js"></script>
    <style>
      html, body {
        margin: 0;
        width: 100%;
        height: 100%;
        font-family: "PingFang SC", "Hiragino Sans GB", "Heiti SC", "Microsoft YaHei", "WenQuanYi Micro Hei" !important;
      }
      code {
        background-color: #e5e5e5 !important;
        margin: 0 4px !important;
        border-radius: 4px !important;
      }
      #upgrade-release {
        width: 100%;
        max-width: 920px;
        box-sizing: border-box;
        padding: 30px 60px;
        margin: auto;
      }
      #upgrade-release-raw-text {
        display: none;
      }
    </style>
  </head>
  <body>
    <base target="_blank">
    <div id="upgrade-release" class="markdown-body"></div>
    <div id="upgrade-release-raw-text">
$text
    </div>
  </body>
  <script>
    function decodeHtml(html) {
      var txt = document.createElement("textarea");
      txt.innerHTML = html;
      return txt.value;
    }
    var upgradeRelease = document.getElementById("upgrade-release");
    var upgradeReleaseRawText = document.getElementById("upgrade-release-raw-text");
    upgradeRelease.innerHTML = marked(decodeHtml(upgradeReleaseRawText.innerHTML));
  </script>
</html>
EOT;
}

/**
 * 列出 release-note 目录下真实可用的版本号
 *
 * @param string $releaseRoot
 *
 * @return array
 */
function wp_editormd_available_release_versions($releaseRoot) {
    $versions = array();

    $entries = @scandir($releaseRoot);
    if (false === $entries) {
        return $versions;
    }

    foreach ($entries as $entry) {
        if ("." === $entry || ".." === $entry) {
            continue;
        }

        if (is_dir($releaseRoot . "/" . $entry) && preg_match("/^[0-9]{1,3}\.[0-9]{1,2}\.[0-9]{1,2}$/", $entry)) {
            $versions[] = $entry;
        }
    }

    return $versions;
}
