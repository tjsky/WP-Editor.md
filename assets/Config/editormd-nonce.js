/**
 * WP Editor.md —— AJAX nonce 注入垫片
 *
 * 背景（10.3.0 安全加固）：
 *   插件的后台 AJAX 接口（图片粘贴上传、设置页 iframe 入口）原先完全没有 CSRF 校验。
 *   但前端主包 assets/Config/editormd.min.js 是 webpack 编译产物，直接改源码需要重新构建。
 *   为让安全修复「即使不重新构建也能生效」，本文件以独立、免构建、非压缩的方式单独加载，
 *   并挂一个全局 $.ajaxPrefilter，仅对白名单内的 action 自动补上 _wpnonce 参数。
 *
 * 取值来源（惰性读取，不依赖脚本加载顺序）：
 *   1. window._EditormdNonce —— PHP 侧 wp_localize_script 到本垫片自身 handle 上，最先可用；
 *   2. window._Editormd      —— 原有配置对象，作为兜底。
 *
 * 依赖：jQuery
 */
(function ($) {
  "use strict";

  if (typeof $ !== "function" || typeof $.ajaxPrefilter !== "function") {
    return;
  }

  // action => 配置对象中的 key 名。不在此表内的请求一律不注入，避免影响其它插件的 AJAX。
  var NONCE_KEYS = {
    wp_editormd_imagepaste: "imagepasteNonce",
    wp_editormd_pages: "pagesNonce",
  };

  /**
   * 惰性解析 nonce：执行时（而非加载时）读取，规避 wp_localize_script 打印顺序问题
   */
  function resolveNonce(action) {
    var key = NONCE_KEYS[action];
    if (!key) {
      return null;
    }

    var sources = [window._EditormdNonce, window._Editormd];
    for (var i = 0; i < sources.length; i++) {
      if (sources[i] && sources[i][key]) {
        return String(sources[i][key]);
      }
    }

    return null;
  }

  /**
   * 从 jQuery ajax 的 data 中取出 action 名
   */
  function resolveAction(data) {
    if (!data) {
      return null;
    }

    if (typeof data === "string") {
      var matched = /(?:^|&)action=([^&]*)/.exec(data);
      return matched ? decodeURIComponent(matched[1]) : null;
    }

    if (typeof data === "object" && data.action) {
      return String(data.action);
    }

    return null;
  }

  $.ajaxPrefilter(function (options) {
    var action = resolveAction(options.data);
    if (!action) {
      return;
    }

    var nonce = resolveNonce(action);
    if (!nonce) {
      return;
    }

    if (typeof options.data === "string") {
      if (!/(?:^|&)_wpnonce=/.test(options.data)) {
        options.data += "&_wpnonce=" + encodeURIComponent(nonce);
      }
      return;
    }

    if (typeof options.data === "object" && options.data !== null) {
      if (!options.data._wpnonce) {
        options.data._wpnonce = nonce;
      }
    }
  });
})(window.jQuery);
