<?php
// 版本发行注记页面
// 包含页面重渲染功能
function display_page($text_domain, $config) {
    $targetVersion = isset($_GET["version"]) ? sanitize_text_field(wp_unslash($_GET["version"])) : "";
    $language      = isset($_COOKIE["wp-editormd-lang"])
        ? sanitize_text_field(wp_unslash($_COOKIE["wp-editormd-lang"]))
        : "en-US";

    if (! preg_match("/^[a-z]{2}-[A-Z]{2}$/", $language)) {
        $language = "en-US";
    }

    if (! preg_match("/^[0-9]{1,3}\.[0-9]{1,2}\.[0-9]{1,2}$/", $targetVersion)) {
        return null;
    }

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
