/* global jQuery, TurndownService, require, editormd, wp, ajaxurl */

/**
 * Editor.md配置
 */
require("./editormd.css");

/**
 * 预览侧补齐插件的「图片尺寸 / 属性」语法，让编辑器预览与保存后的结果一致。
 *
 * 背景
 *   10.4.0 给图片加了可选尺寸语法（实现在 src/App/WPMarkdownParser.php）：
 *     ![alt](img.jpg =600)                  仅宽度
 *     ![alt](img.jpg =300x200)              宽 + 高
 *     ![alt](img.jpg =x400)                 仅高度
 *     ![alt](img.jpg "title" =500x333){#id .class}
 *   但它只存在于服务端：保存时才生效。编辑器预览走的是 Editor.md 自带的 marked，
 *   完全不认识这套写法，`![a](u =600)` 会被判成「不是图片」原样吐出裸 Markdown。
 *   结果是同一段内容「预览里是乱码、发布后却正常」—— 预览失去参考价值。
 *
 * 做法（不改 Editor.md 源码，只做两处包装）
 *   1. 包住 editormd.$marked：在交给 marked 解析之前，把尺寸与属性块搬进 URL 的
 *      fragment，变成 marked 认识的普通图片：
 *        ![alt](u =600){#id}  →  ![alt](u#wp-editormd-img=<base64>)
 *      改写会跳过围栏代码块、缩进代码块与行内代码，不会动到正文里的示例代码。
 *   2. 覆写 editormd.markedRenderer 产出的 renderer.image：把 fragment 解回来，
 *      按 PHP 侧相同的规则输出 width / height / style / id / class。
 *
 * 为什么用 fragment 而不是把尺寸塞进 title
 *   万一这段文本落到了别的渲染器手里（第三方接管 marked），fragment 只会被忽略，
 *   图片照常加载，只是没有尺寸 —— 优雅降级；塞 title 则会变成一个莫名其妙的 tooltip。
 */
(function (editormd) {
  if (!editormd) {
    return;
  }

  // URL fragment 里的键名。保持足够独特，避免与真实站点的图片地址撞车。
  var SPEC_KEY = "wp-editormd-img";

  // 图片语法：与 PHP 侧 doImages() 一一对应（groups 见下方注释）
  var IMG_RE = /!\[([^\]]*)\]\(\s*(?:<([^>\n]*)>|([^\s)]+))(?:\s+("([^"]*)"|'([^']*)'))?\s*(?:=\s*(?:(\d{1,5})(?:\s*[xX]\s*(\d{1,5})?)?|[xX]\s*(\d{1,5})))?\s*\)([ ]?\{([^{}\n]*)\})?/g;

  var INLINE_CODE_RE = /(`+)([\s\S]*?)\1/g;
  var FENCE_RE = /^\s{0,3}(`{3,}|~{3,})/;
  var INDENT_CODE_RE = /^(?: {4}|\t)\S/;

  // 段落内联 HTML 的分段：代码标签（内容不参与公式）与普通标签
  var SEG_RE = /<(code|pre)\b[^>]*>|<\/(code|pre)\s*>|<[^>]*>/gi;

  // 不参与公式的 $ 会被临时换成这个私用区字符，渲染完再换回来
  var TEX_PLACEHOLDER = "\uE001";
  var TEX_PLACEHOLDER_RE = /\uE001/g;

  /* ------------------------------------------------------------------ 工具 */

  function escAttr(value) {
    return String(value === null || value === undefined ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  // UTF-8 安全的 base64（btoa 只吃 latin1）
  function b64encode(str) {
    var bytes = encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function (m, hex) {
      return String.fromCharCode(parseInt(hex, 16));
    });
    return window.btoa(bytes);
  }

  function b64decode(str) {
    var raw = window.atob(str);
    var out = "";
    for (var i = 0; i < raw.length; i++) {
      out += "%" + ("00" + raw.charCodeAt(i).toString(16)).slice(-2);
    }
    return decodeURIComponent(out);
  }

  /**
   * 判断 {...} 里是不是「属性块」而不是普通的花括号内容。
   * 只认 #id / .class / key=value 三种开头，避免误吃正文里的 {示例}。
   */
  function looksLikeAttrs(raw) {
    return /^\s*(?:[#.][^\s.]+|[A-Za-z_][\w:.-]*\s*=)/.test(raw);
  }

  /**
   * 把 `{#id .class key="v"}` 转成 HTML 属性串（与 PHP 的 doExtraAttributes 对齐）。
   */
  function parseAttrBlock(raw) {
    if (!raw) {
      return "";
    }

    var id = "";
    var classes = [];
    var pairs = [];
    var re = /#[^\s.#=]+|\.[^\s.#=]+|[^\s=]+=(?:"[^"]*"|'[^']*'|\S+)/g;
    var m;

    while ((m = re.exec(raw)) !== null) {
      var token = m[0];
      if (token.charAt(0) === "#") {
        id = token.slice(1);
      } else if (token.charAt(0) === ".") {
        classes.push(token.slice(1));
      } else {
        var eq = token.indexOf("=");
        pairs.push([
          token.slice(0, eq),
          token.slice(eq + 1).replace(/^["']|["']$/g, ""),
        ]);
      }
    }

    var out = "";
    if (id) {
      out += " id=\"" + escAttr(id) + "\"";
    }
    if (classes.length) {
      out += " class=\"" + escAttr(classes.join(" ")) + "\"";
    }
    for (var i = 0; i < pairs.length; i++) {
      out += " " + pairs[i][0] + "=\"" + escAttr(pairs[i][1]) + "\"";
    }
    return out;
  }

  /* ------------------------------------------------------ 公式：压掉误渲染 */
  /*
   * Editor.md 的 paragraph() 里对行内公式只看一个正则 /(\$([^\$]*)\$)+/，
   * 不做任何「这到底像不像公式」的判断，于是
   *   价格从 $100 涨到 $200 元      → 把「100 涨到 」当成公式
   *   function update( $a, $b )     → 同上，且下划线还被当上下标
   *   `$var` 与 `$foo = 1;`         → 跨 <code> 配对，把代码片段吞进公式
   * 而服务端（KaTeX::katex_looks_like_formula + 跳过 code/pre）是**会**把这些压掉的，
   * 于是同一段内容「预览是公式、发布是纯文本」。
   *
   * 这里在交给 Editor.md 之前，把「不该当公式的 $」临时换成私用区字符，
   * 让它的公式正则匹配不到；渲染完再把字符换回 $。判定规则与服务端逐条对齐。
   */

  function stripTags(html) {
    return String(html).replace(/<[^>]*>/g, "");
  }

  /**
   * 与服务端 KaTeX::katex_looks_like_formula() 同规则：
   * 定界符紧贴内容，且内容里至少有字母 / 数字 / 反斜杠。
   */
  function looksLikeFormula(content) {
    if (!content) {
      return false;
    }
    if (/^\s/.test(content) || /\s$/.test(content)) {
      return false;
    }
    return /[A-Za-z0-9\\]/.test(content);
  }

  function guardPseudoTex(html) {
    if (typeof html !== "string" || html.indexOf("$") === -1) {
      return { text: html, count: 0 };
    }

    // 整段就是块级公式时原样放行，交由 Editor.md 的 isTeXLine 分支处理
    if (/^\s*\$\$[\s\S]*\$\$\s*$/.test(stripTags(html))) {
      return { text: html, count: 0 };
    }

    // 逐字符标记「是否处于 code / pre 或标签内部」
    var chars = html.split("");
    var blocked = new Array(chars.length);
    var depth = 0;
    var last = 0;
    var m;
    var i;

    SEG_RE.lastIndex = 0;
    while ((m = SEG_RE.exec(html)) !== null) {
      for (i = last; i < m.index; i++) {
        blocked[i] = depth > 0;
      }
      if (m[1]) {
        if (/^(code|pre)$/i.test(m[1])) {
          depth++;
        }
      } else if (m[2]) {
        if (/^(code|pre)$/i.test(m[2])) {
          depth = Math.max(0, depth - 1);
        }
      }
      // 标签自身一律不参与配对
      for (i = m.index; i < SEG_RE.lastIndex; i++) {
        blocked[i] = true;
      }
      last = SEG_RE.lastIndex;
    }
    for (i = last; i < chars.length; i++) {
      blocked[i] = depth > 0;
    }

    var count = 0;
    var candidates = [];

    for (i = 0; i < chars.length; i++) {
      if (chars[i] !== "$") {
        continue;
      }
      if (blocked[i]) {
        // 代码块 / 行内代码里的 $ 必须原样显示（与服务端跳过 code/pre 一致）
        chars[i] = TEX_PLACEHOLDER;
        count++;
        continue;
      }
      candidates.push(i);
    }

    // 两两配对，压掉不像公式的那些
    for (var p = 0; p + 1 < candidates.length; p += 2) {
      var open = candidates[p];
      var close = candidates[p + 1];
      if (looksLikeFormula(html.slice(open + 1, close))) {
        continue;
      }
      chars[open] = TEX_PLACEHOLDER;
      chars[close] = TEX_PLACEHOLDER;
      count++;
    }

    return { text: chars.join(""), count: count };
  }

  /* ------------------------------------------------------- 改写：Markdown 侧 */

  function rewriteLine(line) {
    // 先把行内代码抽出来占位，避免动到 `![a](u =600)` 这类示例
    var stash = [];
    var guarded = line.replace(INLINE_CODE_RE, function (whole) {
      stash.push(whole);
      return "\uE000IC" + (stash.length - 1) + "\uE000";
    });

    var rewritten = guarded.replace(
      IMG_RE,
      function (whole, alt, urlAngle, urlBare, titleQuoted, titleDq, titleSq, w1, h1, h2, attrBlock, attrInner) {
        var url = urlAngle ? urlAngle : urlBare;
        var title = titleDq || titleSq || "";
        var width = parseInt(w1, 10) || 0;
        var height = parseInt(h1, 10) || parseInt(h2, 10) || 0;
        var attrs = (typeof attrInner === "string" && looksLikeAttrs(attrInner)) ? attrInner : "";

        // 既没有尺寸也没有属性块 —— 普通图片，原样放行，绝不改写
        if (!width && !height && !attrs) {
          return whole;
        }

        // URL 自带 fragment 时先摘下来，避免与我们追加的 key 混在一起
        var frag = "";
        var hash = url.indexOf("#");
        if (hash >= 0) {
          frag = url.slice(hash + 1);
          url = url.slice(0, hash);
        }

        var meta = b64encode(JSON.stringify({
          w: width,
          h: height,
          a: attrs,
          t: title,
          f: frag,
        }));

        var nextUrl = url + "#" + SPEC_KEY + "=" + meta;
        return "![" + alt + "](" + (urlAngle ? "<" + nextUrl + ">" : nextUrl) + ")";
      },
    );

    return rewritten.replace(/\uE000IC(\d+)\uE000/g, function (whole, idx) {
      return stash[parseInt(idx, 10)];
    });
  }

  function rewriteSource(src) {
    if (typeof src !== "string" || src.indexOf("![") === -1) {
      return src;
    }

    var lines = src.split("\n");
    var inFence = false;
    var fenceChar = "";

    for (var i = 0; i < lines.length; i++) {
      var fm = FENCE_RE.exec(lines[i]);
      if (fm) {
        if (!inFence) {
          inFence = true;
          fenceChar = fm[1].charAt(0);
        } else if (fm[1].charAt(0) === fenceChar) {
          inFence = false;
          fenceChar = "";
        }
        continue;
      }
      if (inFence || INDENT_CODE_RE.test(lines[i])) {
        continue;
      }
      lines[i] = rewriteLine(lines[i]);
    }

    return lines.join("\n");
  }

  /* ---------------------------------------------------------- 还原：渲染侧 */

  function decodeSpec(href) {
    if (typeof href !== "string") {
      return null;
    }

    var key = SPEC_KEY + "=";
    var at = href.indexOf("#" + key);
    var sep = 1;
    if (at < 0) {
      at = href.indexOf("&" + key);
    }
    if (at < 0) {
      return null;
    }

    var start = at + sep + key.length;
    var end = href.indexOf("&", start);
    if (end < 0) {
      end = href.length;
    }

    var meta;
    try {
      meta = JSON.parse(b64decode(href.slice(start, end)));
    } catch (e) {
      return null;
    }
    if (!meta || typeof meta !== "object") {
      return null;
    }

    var url = href.slice(0, at) + href.slice(end);
    if (meta.f) {
      url += "#" + meta.f;
    }
    return { meta: meta, url: url };
  }

  function buildImgTag(href, text) {
    var decoded = decodeSpec(href);
    if (!decoded) {
      return null;
    }

    var meta = decoded.meta;
    var attrs = typeof meta.a === "string" ? meta.a : "";
    var width = parseInt(meta.w, 10) || 0;
    var height = parseInt(meta.h, 10) || 0;

    // 属性块里显式写了 width / height 时以它为准（与 PHP 侧一致）
    if (/\swidth\s*=/i.test(attrs)) {
      width = 0;
    }
    if (/\sheight\s*=/i.test(attrs)) {
      height = 0;
    }

    var style = "";
    if (width > 0 && height > 0) {
      style = "width:" + width + "px;max-width:100%;height:auto;aspect-ratio:" + width + "/" + height;
    } else if (width > 0) {
      style = "width:" + width + "px;max-width:100%;height:auto";
    } else if (height > 0) {
      style = "height:" + height + "px;width:auto;max-width:100%";
    }

    var out = "<img src=\"" + escAttr(decoded.url) + "\" alt=\"" + escAttr(text) + "\"";
    if (meta.t) {
      out += " title=\"" + escAttr(meta.t) + "\"";
    }
    out += parseAttrBlock(attrs);
    if (width > 0) {
      out += " width=\"" + width + "\"";
    }
    if (height > 0) {
      out += " height=\"" + height + "\"";
    }
    if (style) {
      out += " style=\"" + escAttr(style) + "\"";
    }
    return out + " />";
  }

  /* ------------------------------------------------------------- 包装安装 */

  function wrapMarked(original) {
    if (typeof original !== "function" || original.__wpEditormdWrapped) {
      return original;
    }

    var wrapped = function (src, options) {
      return original.call(this, rewriteSource(src), options);
    };
    wrapped.__wpEditormdWrapped = true;

    /**
     * 包装后必须把 marked 自身的静态成员透出去 —— Editor.md 会读
     * editormd.$marked.Renderer / .Lexer / .setOptions / .defaults，
     * 只返回一个裸函数会让 markedRenderer() 里的 new marked.Renderer() 直接抛
     * 「Renderer is not a constructor」。
     */
    try {
      Object.setPrototypeOf(wrapped, original);
    } catch (e) {
      // 不支持 setPrototypeOf 的老环境：退回逐个复制自有属性
      for (var key in original) {
        if (Object.prototype.hasOwnProperty.call(original, key)) {
          wrapped[key] = original[key];
        }
      }
    }

    return wrapped;
  }

  /**
   * editormd.$marked 是 Editor.md 在依赖（marked.min.js）加载完成后才赋值的，
   * Config 脚本此时可能还没等到。用属性拦截器保证「赋值那一刻」就被包上，
   * 否则会漏掉首次渲染。
   */
  function installMarkedHook() {
    var current = editormd.$marked;

    try {
      Object.defineProperty(editormd, "$marked", {
        configurable: true,
        enumerable: true,
        get: function () {
          return current;
        },
        set: function (value) {
          current = wrapMarked(value);
        },
      });
      // 若已经赋值过，重新赋一次触发 setter 包装
      if (typeof current === "function") {
        editormd.$marked = current;
      }
    } catch (e) {
      // 环境不支持 defineProperty（极老浏览器）时退化为「当下包一次」
      editormd.$marked = wrapMarked(current);
    }
  }

  function installRendererHook() {
    var originalFactory = editormd.markedRenderer;
    if (typeof originalFactory !== "function" || originalFactory.__wpEditormdWrapped) {
      return;
    }

    var factory = function (markdownToC, options) {
      var renderer = originalFactory.call(this, markdownToC, options);

      if (renderer && typeof renderer.image === "function") {
        var baseImage = renderer.image;
        renderer.image = function (href, title, text) {
          var built = buildImgTag(href, text);
          return built !== null ? built : baseImage.apply(this, arguments);
        };
      }

      /**
       * 压掉行内公式的误渲染：Editor.md 的 paragraph() 只做正则配对、
       * 不判断「像不像公式」，会把价格、函数签名、行内代码一并当成公式。
       * 这里先把不该当公式的 $ 换成占位字符，渲染完再换回来。
       */
      if (renderer && typeof renderer.paragraph === "function") {
        var baseParagraph = renderer.paragraph;
        renderer.paragraph = function (text) {
          var guarded = guardPseudoTex(text);
          var html = baseParagraph.call(this, guarded.text);
          if (!guarded.count) {
            return html;
          }
          return html.replace(TEX_PLACEHOLDER_RE, function () {
            return "$";
          });
        };
      }

      return renderer;
    };
    factory.__wpEditormdWrapped = true;
    editormd.markedRenderer = factory;
  }

  installMarkedHook();
  installRendererHook();
})(window.editormd);

(function ($, doc, win, editor) {
  $(doc).ready(function () {
    var textareaID = null;
    if (doc.getElementById("wp-content-editor-container")) {
      textareaID = "wp-content-editor-container";
    } else if (doc.getElementById("wp-replycontent-editor-container") && editor.supportReply === "on" && location.href.indexOf("/wp-admin/") !== -1) {
      textareaID = "wp-replycontent-editor-container";
    } else if (doc.getElementById("comment") && editor.supportComment === "on" && location.href.indexOf("/wp-admin/") === -1) {
      textareaID = "comment";
      $("#comment").after("<div id=\"comment\"></div>").remove();
    } else if (editor.supportOther !== "") {
      // 自定义ID编辑器
      textareaID = editor.supportOther;
      $("#" + editor.supportOther).after("<div id=\"" + editor.supportOther + "\"></div>").remove();
    } else {
      return false;
    }

    //完整菜单
    var fullToolBar = [
      "undo", "redo", "|",
      "bold", "del", "italic", "quote", "ucwords", "uppercase", "lowercase", "|",
      "h1", "h2", "h3", "h4", "h5", "h6", "|",
      "list-ul", "list-ol", "hr", "|",
      "link", "reference-link", "image", "code", "code-block", "table", "datetime", editor.emoji !== "off" ? "emoji" : "" + "html-entities", "more", "pagebreak", "|",
      "goto-line", "watch", "preview", "fullscreen", "clear", "search", "|",
      "help", "info",
    ];
    var simpleToolBar = [
      "bold", "del", "italic", "quote", "ucwords", "uppercase", "lowercase", "|",
      "link", "reference-link", "image", "code", "code-block", "table", "datetime", editor.emoji !== "off" ? "emoji" : "" + "html-entities", "|",
      "watch", "preview", "fullscreen", "clear", "info",
    ];
    var miniToolBar = [
      "ucwords", "uppercase", "lowercase", "|",
      "link", "reference-link", "image", "table", "datetime", editor.emoji !== "off" ? "emoji" : "" + "html-entities", "|",
      "watch", "preview", "fullscreen", "info",
    ];

    var toolBar;
    switch (textareaID) {
      case "wp-content-editor-container":
        toolBar = fullToolBar;
        break;
      case "comment":
        toolBar = simpleToolBar;
        break;
      case "wp-replycontent-editor-container":
        toolBar = miniToolBar;
        break;
      default:
        toolBar = fullToolBar;
        break;
    }

    const editorTextArea = doc.getElementById(textareaID);

    const htmlTagEscapedItem = [
      // 可能会引起样式错乱或XSS漏洞的标签
      "script", "style",
      // 每次输入新字符都会导致重复加载的标签
      "audio", "video",
      // 表单相关内容，可能会影响编辑器其他功能（如无法提交）
      "form", "input", "textarea", "button", "select", "option", "optgroup", "fieldset", "output",
    ];

    var wpEditormd = editormd({
      id: textareaID,
      path: editor.editormdUrl + "/assets/Editormd/lib/",
      width: "100%", //编辑器宽度
      height: textareaID === "wp-content-editor-container" ? 640 : 320,  //编辑器高度
      syncScrolling: editor.livePreview !== "off" && editor.syncScrolling !== "off", //即是否开启同步滚动预览
      watch: textareaID === "wp-replycontent-editor-container" ? false : editor.livePreview !== "off",
      htmlDecode: editor.htmlDecode !== "off", //HTML标签解析
      htmlTagEscapedItem: htmlTagEscapedItem,
      toolbarAutoFixed: false, //工具栏是否自动固定
      toolbar: true,
      autoFocus: textareaID !== "comment", //判断场景是否跳转到编辑器区域
      tocm: false, //同TOC 不过不合适
      tocContainer: editor.toc === "off" ? false : "", //TOC
      tocDropdown: false, //下拉TOC
      theme: editor.theme, //编辑器总体主题
      previewTheme: editor.previewTheme, //编辑器主题
      editorTheme: editor.editorTheme, //编辑器主题
      emoji: editor.emoji !== "off", //Emoji表情
      tex: editor.tex === "katex", //LaTeX公式
      mind: editor.mindMap !== "off", //思维导图
      mermaid: editor.mermaid !== "off", //Mermaid
      atLink: false, //Github @Link
      taskList: editor.taskList !== "off", //task lists
      imageFormats: ["jpg", "jpeg", "gif", "png", "bmp", "webp"],
      placeholder: editor.placeholderEditor, //编辑器placeholder
      prismTheme: editor.prismTheme, //Prism主題风格
      prismLineNumbers: editor.prismLineNumbers !== "off",
      saveHTMLToTextarea: true,
      toolbarIcons: function () {
        return toolBar;
      },
      //强制全屏
      onfullscreen: function () {
        editorTextArea.style.position = "fixed";
        editorTextArea.style.zIndex = "99999";
        editorTextArea.style.width = "100%";
        editorTextArea.style.height = "100%";
      },
      //退出全屏返回原来的样式
      onfullscreenExit: function () {
        editorTextArea.style.position = "relative";
        editorTextArea.style.zIndex = "auto";
        editorTextArea.style.width = "100%";
        editorTextArea.style.removeProperty("height");
        // 触发resize事件，让编辑器重新获得尺寸信息，重新处理编辑器内部布局
        window.dispatchEvent(new Event("resize", {}));
      },
      onload: function () {
        //加载完成执行
        if (textareaID === "comment") {
          //修改评论表单name
          $("textarea.editormd-markdown-textarea").attr("name", "comment");
        }

        if (textareaID === "wp-replycontent-editor-container") {
          // 后台回复框：编辑器宽度跟着预览面板的显隐走。
          // 预览关闭（默认状态）时编辑器占满整行，打开时各占一半 ——
          // 否则关闭预览时编辑器仍只占左半边，右半边是一大片空白。
          var syncReplyEditorWidth = function () {
            var $wrap = $("#wp-replycontent-editor-container");
            if (!$wrap.length) {
              return;
            }

            var $preview = $wrap.find(".editormd-preview");
            var previewVisible = $preview.is(":visible");

            // 行内样式（非 !important）用于覆盖 Editor.md 内联写入的宽度，
            // 因此 editormd.css 里不能再给 .CodeMirror 的 width 加 !important
            $wrap.find(".CodeMirror").css("width", previewVisible ? "50%" : "100%");
          };

          syncReplyEditorWidth();
          window.addEventListener("resize", syncReplyEditorWidth);

          // 宽度由 Editor.md 自己在内联样式里改（而且不是点击后立刻生效，
          // 只靠固定延时对不上时机，它也可能在我们之后再把宽度写回去），
          // 因此监听容器整棵子树的样式变化，谁改都能立刻校正回来。
          var replyWrapNode = $("#wp-replycontent-editor-container").get(0);
          if (replyWrapNode && typeof MutationObserver !== "undefined") {
            new MutationObserver(syncReplyEditorWidth).observe(replyWrapNode, {
              attributes: true,
              attributeFilter: ["style", "class"],
              subtree: true,
            });
          }

          // 保底：工具栏按钮点击后再补几次校正
          $(document).on("click", "#wp-replycontent-editor-container .editormd-toolbar-container a", function () {
            setTimeout(syncReplyEditorWidth, 50);
            setTimeout(syncReplyEditorWidth, 300);
          });

          $(".reply").click(function () {
            setTimeout(function () {
              $(".edit-comments-php .CodeMirror.cm-s-default.CodeMirror-wrap").css("margin-top", $(".editormd-toolbar").height());
              syncReplyEditorWidth();
            }, 100);
          });
        }

        if (getWidth() === 1600) {
          // 1600分辨率删除编辑器编辑空白外边距
          var codeMirror = $(".editormd .CodeMirror.CodeMirror-wrap");
          var codeMirrorMarginTop = codeMirror.css("margin-top");
          codeMirror.css("margin-top", parseInt(codeMirrorMarginTop) - 32 + "px");
        }

        // 隐藏默认编辑器
        $("#ed_toolbar").hide();
      },
    });
    //将wpEditormd实例绑定至window，以便外部扩展调用
    window.wpEditormd = wpEditormd;
    // WP Media module支持
    if (typeof wp !== "undefined" && typeof wp.media !== "undefined") {
      var original_wp_media_editor_insert = wp.media.editor.insert;
      wp.media.editor.insert = function (html) {
        // 显示Loading画面，避免用户以为添加失败
        showLoader();

        // 需要判断标签格式，如果是可以被转换的标签则对其进行转换
        // <img> <a>标签是可以被转换的
        // 否则不进行转换，直接插入到文本中
        var convertableRegex = new RegExp("(<img .*?>)|(<a ?.*?>.*<\/a>)");
        var markdown;
        if (convertableRegex.test(html)) {
          var turndownService = new TurndownService();
          markdown = turndownService.turndown(html);
        } else {
          markdown = html;
        }

        original_wp_media_editor_insert(markdown);
        wpEditormd.insertValue(markdown);

        hideLoader(1500);
      };
    }
    // 实时更新字数
    var updateWordCounter = setInterval(function() {
      // wp.utils.WordCounter()在前台评论部分不存在，因此需要判断一下，避免出现错误。
      // 修复（10.4.3）：原来的判断写成 `wp && wp.utils`，而前台根本没有 wp 这个全局变量，
      // 直接读未声明的标识符会抛 ReferenceError —— 每秒一次、每次一报，
      // 控制台里就会刷出大量「wp is not defined」。必须先做 typeof 判断。
      if (typeof wp !== "undefined" && wp.utils) {
        var $count = $("#wp-word-count").find(".word-count");
        var html = wpEditormd.getHTML();

        var wordCounter = new wp.utils.WordCounter();
        var words = wordCounter.count(html);

        $count.text(words);
      } else {
        clearInterval(updateWordCounter);
      }
    }, 1000);
    // 图像粘贴
    if (editor.imagePaste === "on") {
      $("#" + textareaID).on("paste", function (event) {
        event = event.originalEvent;
        var cbd = window.clipboardData || event.clipboardData; //兼容ie||chrome
        var ua = window.navigator.userAgent;
        if (!(event.clipboardData && event.clipboardData.items)) {
          return;
        }
        // 获取当前光标位置
        var nowCursor = {
          line: wpEditormd.getCursor().line,
          ch: wpEditormd.getCursor().ch,
        };
        if (cbd.items && cbd.items.length === 2 && cbd.items[0].kind === "string" && cbd.items[1].kind === "file" &&
          cbd.types && cbd.types.length === 2 && cbd.types[0] === "text/plain" && cbd.types[1] === "Files" &&
          ua.match(/Macintosh/i) && Number(ua.match(/Chrome\/(\d{2})/i)[1]) < 49) {
          return;
        }
        var itemLength = cbd.items.length;
        if (itemLength === 0) {
          return;
        }
        if (itemLength === 1 && cbd.items[0].kind === "string") {
          return;
        }

        // 此处itemLength等于2的情况是为了兼容MacOS，其剪贴板内包含两个元素，一个是文件名，一个是文件二进制数据
        if ((itemLength === 1 && cbd.items[0].kind === "file") || itemLength === 2 && cbd.items[1].kind === "file") {
          var item;
          var fileName = "";
          if (itemLength === 1) {
            item = cbd.items[0];
          } else {
            cbd.items[0].getAsString(function(s) {
              fileName = s;
            });
            item = cbd.items[1];
          }


          var blob = item.getAsFile();
          if (blob.size === 0) {
            return;
          }

          showLoader();

          //传参
          readBlobAsDataURL(blob, function (dataurl) {
            var uploadingText = "![" + editor.imgUploading + "]";
            var uploadFailText = "![" + editor.imgUploadeFailed + "]";
            var data = {
              action: "wp_editormd_imagepaste",
              dataurl: dataurl,
            };
            wpEditormd.insertValue(uploadingText);

            // 去除部分设备下粘贴时出现的文件名（如MacOS）
            wpEditormd.setValue(wpEditormd.getValue().replace(fileName, ""));
            nowCursor.ch = nowCursor.ch - fileName.length;

            $.ajax({
              url: ajaxurl,
              type: "POST",
              data: data,
              success: function (obj) {
                let pasteMarkdownText;
                if (obj.error) {
                  pasteMarkdownText = wpEditormd.getValue().replace(uploadingText, uploadFailText);
                } else {
                  if ( editor.imageLink === "on" ) {
                    pasteMarkdownText = wpEditormd.getValue().replace(uploadingText, "[" + "![](" + obj.url + ")" + "](" + obj.url + ")");
                  } else {
                    pasteMarkdownText = wpEditormd.getValue().replace(uploadingText, "![](" + obj.url + ")");
                  }
                }
                wpEditormd.setValue(pasteMarkdownText);

                // 移动光标
                nowCursor.ch = nowCursor.ch + pasteMarkdownText.length;
                wpEditormd.setCursor(nowCursor);
                wpEditormd.focus();
              },
              complete: function() {
                hideLoader();
              },
            });
          });
        }
      });
    }
  });
  // CodeMirror
  editormd.codeMirrorURL = {
    url: cdn_url(editor.editormdUrl, "codemirror"),
  };
  // Marked
  editormd.markedURL = {
    js: cdn_url(editor.editormdUrl, "marked") + "/marked.min",
  };
  // Prism高亮库
  editormd.prismURL = {
    url: cdn_url(editor.editormdUrl, "prismjs"),
  };

  // 思维导图自定义地址
  editormd.mindMapURL = splitFileName(editor.mindMapURL);

  // KaTeX科学公式配置
  if (editor.tex === "on") {
    editormd.katexURL = {
      css: cdn_url(editor.editormdUrl, "katex") + "/katex.min",
      js: cdn_url(editor.editormdUrl, "katex") + "/katex.min",
    };
  }
  // Mermaid配置
  if (editor.mermaid === "on") {
    editormd.mermaidURL = {
      js: cdn_url(editor.editormdUrl, "mermaid") + "/mermaid.min",
    };
  }
  // Emoji表情配置
  if (editor.emoji === "on") {
    editormd.emoji = {
      path: cdn_url(editor.editormdUrl, "emojify") + "/",
      ext: ".png",
    };
  }

  /**
   * 判断CDN地址
   * @param url 传入CDN地址
   * @param lib 类库名称
   * @returns {*} 重写url
   */
  function cdn_url(url, lib) {
    var lib_url;
    switch (lib) {
      case "emojify":
        lib_url = url + "/assets/Emojify.js/images/basic";
        break;
      case "katex":
        lib_url = url + "/assets/KaTeX";
        break;
      case "mermaid":
        lib_url = url + "/assets/Mermaid";
        break;
      case "prismjs":
        lib_url = url + "/assets/Prism.js";
        break;
      case "codemirror":
        lib_url = url + "/assets/CodeMirror";
        break;
      case "marked":
        lib_url = url + "/assets/Marked";
    }
    return lib_url;
  }

  /**
   * 获取窗口宽度
   * @returns {*}
   */
  function getWidth() {
    if (Window.innerWidth) {
      return Window.innerWidth;
    }
    if (document.documentElement && document.documentElement.clientWidth) {
      return document.documentElement.clientWidth;
    }
    if (document.body) {
      return document.body.clientWidth;
    }
  }

  /**
   * 获取去掉后缀
   * @param text
   * @returns {*}
   */
  function splitFileName(text) {
    var pattern = /\.{1}[a-z]{1,}$/;
    if (pattern.exec(text) !== null) {
      return (text.slice(0, pattern.exec(text).index));
    } else {
      return text;
    }
  }

  //封装FileReader对象
  function readBlobAsDataURL(blob, callback) {
    var reader = new FileReader();
    reader.onload = function (e) {
      callback(e.target.result);
    };
    reader.readAsDataURL(blob);
  }

  function showLoader() {
    jQuery(".editormd-container-mask").css("display", "block");
  }

  function hideLoader(timeout = 0) {
    setTimeout(function() {
      jQuery(".editormd-container-mask").css("display", "none");
    }, timeout);
  }
})(window.jQuery, document, window, window._Editormd);
