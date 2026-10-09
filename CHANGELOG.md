# WP Editor.md 更新日志

这里只记本维护分支的改动（从 10.3.0 开始）。原版 10.2.1 及更早的历史附在文末。

内容按语言分成三块：**简体中文 · English · 繁體中文**。

[简体中文](#简体中文) · [English](#english) · [繁體中文](#繁體中文) · [原版历史](#原版历史)

---

## 简体中文

### Version 10.5.0

> **给访客评论加了一个「简版编辑器」，去掉了已转收费的第三方图床，并修好后台到处弹提示的问题。**

**新增：访客评论「简版编辑器」（默认关闭）**

评论区一直存在「预览里看得见、发出去就没了」的问题：WordPress 只允许评论中出现 15 种标签，
图片、代码块、表格、标题、列表都会被系统删掉，而工具栏却提供了这些按钮。
开启这个开关后，评论区只保留真正能用的功能：

* 单栏编辑，工具栏只有 8 个按钮：粗体、斜体、删除线、引用、行内代码、链接、图片、预览。
* 手机上不再被双栏预览挤掉输入区；字号 16px，避免 iPhone 聚焦输入框时把整页放大。
* 链接显示成「文字：地址」的形式且不能点击；图片也不能点击 —— 防止访客之间互相钓鱼。
* 公式、代码块、任务列表、emoji 短代码在评论区一并关闭，预览与发出去的结果保持一致。
* 图片对话框里去掉了「图片链接」一栏（评论图片一律不能点，填了也不会生效）。
* 开关位置：**设置 → WP Editor.md → 基本设置**。需要按站点强制开关时，可用
  `editormd_simple_comment_enabled` 过滤器。

**新增：调试信息里能查看评论区允许的 HTML 标签**

**高级设置 → 调试信息**里，现在可以看到评论区实际允许出现的标签清单，
并标注哪些是 WordPress 自带的、哪些是本插件额外放行的。

**新增：编辑器配色可以只作用于后台**

以前后台把编辑器设成深色，访客在浅色主题的网站上看到的评论框也是深色。
现在默认不跟随：前台使用编辑器自带的浅色样式、跟随你的主题，后台保持原样。
需要前台夜间样式时自行写 CSS 即可。

**修复**

* 后台「回复评论」框打开后可以直接输入了 —— 此前光标并不在输入框里，要先点一下工具栏按钮才能打字。
* 后台「回复评论」框的工具栏不再压住正文：此前正文第一行（含左侧的行号）被工具栏挡住，
  看起来像「行号显示异常、输入的第一个字被盖住」。
* 后台**每个页面**都在弹「检验到插件资源包已过时」—— 现在只在插件设置页显示一行状态。
* 官方块主题（Twenty Twenty-Four / Twenty-Five）下，评论框下方多出一块显示 HTML 源码的输入框。
* 停留在 10.4.3 / 10.4.5 的站点升级后，会反复执行一次初始化动作。
* 简版工具栏在窄屏下会把页面撑宽，手机上可以横向拖拽。

**移除：第三方图床（sm.ms）上传**

该服务已改为收费模式。上传分流、访问令牌、后台管理页与相关设置项一并删除；
图片粘贴统一保存到本站媒体库，升级时会自动清理遗留的图床设置。

### Version 10.4.5

> **修「编辑器预览里是乱码、发出去却正常」。**

* **修复**：预览不认识图片尺寸写法 `![说明](图片 =600)`，会把它原样显示成 Markdown 源码。
  现在预览的显示结果与发出去的一致。
* **修复**：预览会把普通文字当成公式，例如「价格从 $100 涨到 $200」、`function update( $a, $b )`、
  行内代码里的 `$`。现在预览与保存结果完全一致。

### Version 10.4.4

> **评论里的 Markdown 终于会生效了；页面也不再重复加载两套代码高亮。**

* **修复**：访客在评论里写的 `**粗体**`、`[链接](...)` 以前会**原样存进数据库**，前台显示的是一堆符号。
  现在评论的 Markdown 会正常转换。
  （进阶：需要关闭时可用 `editormd_comment_markdown_enabled` 过滤器。）
* **修复**：`>` 开头的引用块渲染不出来、带标题的链接解析失败。
* **优化**：页面上曾同时加载两套代码高亮脚本，导致代码块没有复制按钮、没有语言标签。
  现在只保留一套，同一个页面的脚本体积从约 302 KB 降到 24 KB。

### Version 10.4.3

> **集中修前端显示问题。**

* **修复**：主题引入 Bootstrap 后，编辑器工具栏只剩零星几个图标。
* **修复**：后台「回复评论」框只显示左半边，右边一片空白。
* **修复**：控制台不再报 `Prism is not defined`，也不再有 `.../components/prism-*.min.js` 这类 404。
* **修复**：控制台不再每秒报一次 `wp is not defined`。

### Version 10.4.2

> **一轮安全加固。**

* **安全**：升级两个存在公开安全问题的组件 —— Mermaid 8.4.8 → 10.9.8、KaTeX 0.11.1 → 0.19.0。
* **安全**：图片粘贴上传补上资源用量限制（以前一张高压缩图片就可能耗尽服务器内存）。
* **安全**：后台管理页面的权限校验、请求校验与日志脱敏一并收紧。
* **安全**：前端依赖 axios 0.19.2 → 1.20.0。
* **修复**：Mermaid 图表以前在正文里根本无法显示。
* **修复**：升级动作不再要求有人已登录后台（以前只有访客访问时，升级可能一直不执行）。
* **调整**：前台不再默认移除 WordPress 的区块样式；需要时用 `editormd_dequeue_block_styles` 过滤器开启。

### Version 10.4.1

> **修 10.3.0 / 10.4.0 的安装包在后台不显示插件信息。**

* **修复**：安装包里插件主文件头部的版本号、作者、所需 WordPress / PHP 版本等信息丢失，
  导致后台「上传插件」界面看不到版本与作者，也检测不到更新。
* 现已加入自动校验，这类信息再被误删会导致打包直接失败。

### Version 10.4.0

> **修公式解析的老问题，并新增可选的图片尺寸写法。不写尺寸的图片，显示效果与以前完全一样。**

* **修复**：代码块与行内代码里的 `$` 不再被当成公式渲染。
* **修复**：块级公式不再重复渲染。
* **修复**：正文里凑巧成对的 `$`（如「价格从 $100 涨到 $200」）不再被当成公式。
* **修复**：xmlrpc 请求报错。
* **新增**：图片地址后可加尺寸 —— `![说明](图片 =600)` / `=600x400` / `=x400`。
  宽屏按设定尺寸显示，窄屏等比缩放、不变形。

### Version 10.3.0

> **本维护版从这里开始：修安全问题、适配新版 WordPress。**
> 原版自 10.2.1 起停止更新，并已于 2025-04-09 因安全问题被 WordPress 官方应用商店下架。

* **安全**：修掉公式渲染的存储型 XSS —— 在文章**或评论**里发一段特定内容就能执行脚本。
* **安全**：修掉图片粘贴接口的越权上传 —— 补上权限与来源校验、按内容判断真实图片类型、限制体积。
* **安全**：修掉图床代理可被当作跳板的问题，改为固定的上游白名单。
* **安全**：修掉后台页面的越权、设置项保存不做检查、后台每个请求都同步请求外部地址导致卡顿等问题。
* **修复**：带特定 Cookie 即白屏、卸载插件时报错等。
* **调整**：默认不再从第三方 CDN 加载编辑器脚本与样式。
* **兼容**：适配 WordPress 7.1 与 PHP 7.4 – 8.4，修掉会让前台后台同时白屏的致命问题。
* **修复**：思维导图设置项丢失、Mermaid 的默认值被错误写进 KaTeX。

---

## English

### Version 10.5.0

> **Adds a simplified editor for visitor comments, removes the now-paid third-party image host,
> and fixes the admin notice that popped up everywhere.**

**New: simplified editor for visitor comments (off by default)**

Comments have always suffered from "it looks fine in the preview but disappears once posted":
WordPress allows only 15 tags in a comment, so images, code blocks, tables, headings and lists are
all stripped — while the toolbar offered buttons for exactly those things. With this option on, the
comment box only keeps what actually works:

* A single column with just 8 buttons: bold, italic, strikethrough, quote, inline code, link, image, preview.
* On phones the two-column preview no longer squeezes the input area; the font is 16px so iOS Safari
  does not zoom the whole page when a visitor taps the box.
* Links render as "text: address" and are not clickable; images are not clickable either — this is to
  prevent phishing between visitors.
* Formulas, code blocks, task lists and emoji shortcodes are turned off for comments, so the preview
  and the posted result match.
* The "image link" field is gone from the image dialog (it could never take effect).
* Where: **Settings → WP Editor.md → Basic Settings**. To force it per site, use the
  `editormd_simple_comment_enabled` filter.

**New: see which HTML tags comments may use**

Under **Advanced Settings → Debugger**, the comment tag list that is actually in effect is now shown,
marked as either built into WordPress or added by this plugin.

**New: the editor colour scheme can apply to the admin only**

Previously, setting a dark editor style also gave visitors a dark comment box on a light theme.
The front end now keeps the editor's own light style and follows your theme; the admin is unchanged.
Write your own CSS if you want a dark front-end style.

**Fixed**

* The admin "Reply" box now accepts typing as soon as it opens — previously the caret was not actually in
  the editor and you had to click a toolbar button first.
* The toolbar in that box no longer covers the text: it used to sit on top of the first line
  (including its line number), which looked like "the line numbers are broken and the first
  character is hidden".
* Every admin page showed a red "the plugin asset bundle is out of date" notice — it now appears only
  as a single status line on the plugin settings page.
* On official block themes (Twenty Twenty-Four / Twenty-Five) a raw HTML textarea leaked out below the
  comment form.
* Sites sitting on 10.4.3 / 10.4.5 re-ran an initialisation routine on every request after upgrading.
* The simplified toolbar stretched the page sideways on narrow screens.

**Removed: third-party image host (sm.ms)**

That service has moved to a paid model. The upload branch, access token, admin page and related
settings are all gone; pasted images are now always saved to your own media library, and leftover
settings are cleaned up on upgrade.

### Version 10.4.5

> **Fixes "it looks like garbage in the preview but is fine once published".**

* **Fixed**: the preview did not understand the image-size syntax `![alt](img =600)` and showed the raw
  Markdown. The preview now matches the published result.
* **Fixed**: the preview rendered ordinary text as formulas — "prices from $100 to $200",
  `function update( $a, $b )`, or a `$` inside inline code. Preview and saved result now agree.

### Version 10.4.4

> **Markdown in comments finally works, and pages no longer load two copies of the highlighter.**

* **Fixed**: a visitor's `**bold**` or `[link](...)` used to be **stored verbatim**, so the front end
  showed the raw symbols. Comment Markdown is now converted normally.
  (Advanced: disable with the `editormd_comment_markdown_enabled` filter.)
* **Fixed**: blockquotes starting with `>` never rendered, and links with titles failed to parse.
* **Improved**: two copies of the syntax highlighter used to load on the same page, leaving code blocks
  without a copy button or language label. Only one remains: about 302 KB → 24 KB per page.

### Version 10.4.3

> **A batch of front-end fixes.**

* **Fixed**: after a theme loaded Bootstrap, the editor toolbar showed only a couple of icons.
* **Fixed**: the admin reply box only showed its left half, leaving the right half blank.
* **Fixed**: no more `Prism is not defined` or `.../components/prism-*.min.js` 404s in the console.
* **Fixed**: the console no longer logs `wp is not defined` once per second.

### Version 10.4.2

> **A round of security hardening.**

* **Security**: two bundled components upgraded — Mermaid 8.4.8 → 10.9.8, KaTeX 0.11.1 → 0.19.0.
* **Security**: resource limits added to image paste (a single highly compressed image could exhaust
  server memory before).
* **Security**: tightened permission checks, request validation and log redaction on admin pages.
* **Security**: front-end dependency axios 0.19.2 → 1.20.0.
* **Fixed**: Mermaid diagrams never rendered in post content at all.
* **Fixed**: the upgrade routine no longer requires somebody to be logged in (previously it might never
  run on a site visited only by guests).
* **Changed**: the front end no longer removes WordPress block styles by default; opt in with the
  `editormd_dequeue_block_styles` filter.

### Version 10.4.1

> **Fixes the 10.3.0 / 10.4.0 packages not showing plugin information in the admin.**

* **Fixed**: the plugin file header in the released package had lost its version, author and required
  WordPress / PHP versions, so the admin's "Upload Plugin" screen showed no version or author and
  updates could not be detected.
* Automatic validation was added: losing that information again now fails the build.

### Version 10.4.0

> **Fixes long-standing formula issues and adds an optional image-size syntax.
> Images without a size render exactly as before.**

* **Fixed**: `$` inside code blocks and inline code is no longer treated as a formula.
* **Fixed**: block formulas are no longer rendered twice.
* **Fixed**: coincidentally paired `$` in ordinary text (e.g. "price from $100 to $200") is no longer
  treated as a formula.
* **Fixed**: xmlrpc requests errored out.
* **New**: an image address can carry a size — `![alt](img =600)` / `=600x400` / `=x400`.
  It renders at the requested size on wide screens and scales proportionally without distortion.

### Version 10.3.0

> **Where this maintained fork begins: security fixes and compatibility with current WordPress.**
> The original plugin has not been updated since 10.2.1 and was removed from WordPress.org on
> 2025-04-09 over a security issue.

* **Security**: fixed a stored XSS in formula rendering — posting a specific snippet in a post **or a
  comment** executed script.
* **Security**: fixed unauthorised uploads through the image paste endpoint — added permission and
  origin checks, real image type detection, and a size limit.
* **Security**: fixed the image host proxy being usable as an open proxy; the upstream is now a strict
  allowlist.
* **Security**: fixed admin-side authorisation flaws, option saving with no validation, and a slow admin
  caused by a synchronous external request on every page.
* **Fixed**: a white screen triggered by a specific cookie, and errors on plugin uninstall.
* **Changed**: editor scripts and styles are no longer loaded from a third-party CDN by default.
* **Compatibility**: works with WordPress 7.1 and PHP 7.4 – 8.4, including a fatal issue that took down
  both the admin and the front end.
* **Fixed**: the mind map setting was lost, and Mermaid's defaults were mistakenly written into KaTeX's.

---

## 繁體中文

### Version 10.5.0

> **為訪客留言加上「簡版編輯器」，移除已轉為收費的第三方圖床，並修好後台到處跳提示的問題。**

**新增：訪客留言「簡版編輯器」（預設關閉）**

留言區一直存在「預覽裡看得到、送出去卻沒了」的問題：WordPress 只允許留言中出現 15 種標籤，
圖片、程式碼區塊、表格、標題、清單都會被系統刪掉，而工具列卻提供了這些按鈕。
開啟這個選項後，留言框只保留真正能用的功能：

* 單欄編輯，工具列只有 8 個按鈕：粗體、斜體、刪除線、引用、行內程式碼、連結、圖片、預覽。
* 手機上不再被雙欄預覽擠掉輸入區；字級 16px，避免 iPhone 聚焦輸入框時把整頁放大。
* 連結顯示為「文字：網址」且不能點；圖片也不能點 —— 這是為了防止訪客之間互相釣魚。
* 公式、程式碼區塊、任務清單、emoji 短碼在留言區一併關閉，預覽與送出結果保持一致。
* 圖片對話框移除了「圖片連結」欄位（留言圖片一律不能點，填了也不會生效）。
* 開關位置：**設定 → WP Editor.md → 基本設定**。需要依站台強制開關時，可用
  `editormd_simple_comment_enabled` 過濾器。

**新增：除錯資訊裡可查看留言區允許的 HTML 標籤**

在**進階設定 → 除錯資訊**裡，現在可以看到留言區實際允許出現的標籤清單，
並標示哪些是 WordPress 內建、哪些是本外掛額外放行的。

**新增：編輯器配色可以只作用於後台**

以前後台把編輯器設成深色，訪客在淺色主題的站台上看到的留言框也是深色。
現在預設不跟隨：前台使用編輯器自帶的淺色樣式、跟隨你的佈景主題，後台維持原樣。
需要前台夜間樣式時自行撰寫 CSS 即可。

**修正**

* 後台「回覆留言」框開啟後可以直接輸入了 —— 先前游標其實不在輸入框裡，要先點一下工具列按鈕才能打字。
* 後台「回覆留言」框的工具列不再壓住正文：先前正文第一行（含左側行號）被工具列擋住，
  看起來像「行號顯示異常、輸入的第一個字被蓋住」。
* 後台**每個頁面**都在跳「檢驗到外掛資源包已過時」—— 現在只在設定頁顯示一行狀態。
* 官方區塊佈景主題（Twenty Twenty-Four / Twenty-Five）下，留言框下方多出一塊顯示 HTML 原始碼的輸入框。
* 停留在 10.4.3 / 10.4.5 的站台升級後，會反覆執行一次初始化動作。
* 簡版工具列在窄螢幕下會把頁面撐寬，手機上可以橫向拖曳。

**移除：第三方圖床（sm.ms）上傳**

該服務已改為收費模式。上傳分流、存取權杖、後台管理頁與相關設定項一併刪除；
圖片貼上一律存到本站媒體庫，升級時會自動清除殘留的圖床設定。

### Version 10.4.5

> **修正「編輯器預覽裡是亂碼、發佈後卻正常」。**

* **修正**：預覽不認識圖片尺寸寫法 `![說明](圖片 =600)`，會原樣顯示成 Markdown 原始碼。
  現在預覽的顯示結果與發佈後一致。
* **修正**：預覽會把普通文字當成公式，例如「價格從 $100 漲到 $200」、`function update( $a, $b )`、
  行內程式碼裡的 `$`。現在預覽與儲存結果完全一致。

### Version 10.4.4

> **留言裡的 Markdown 終於會生效了；頁面也不再重複載入兩套程式碼高亮。**

* **修正**：訪客在留言裡寫的 `**粗體**`、`[連結](...)` 以前會**原樣存進資料庫**，前台顯示的是一堆符號。
  現在留言的 Markdown 會正常轉換。
  （進階：需要關閉時可用 `editormd_comment_markdown_enabled` 過濾器。）
* **修正**：`>` 開頭的引用區塊渲染不出來、帶標題的連結解析失敗。
* **優化**：頁面上曾同時載入兩套程式碼高亮腳本，導致程式碼區塊沒有複製按鈕、沒有語言標籤。
  現在只保留一套，同一個頁面的腳本體積從約 302 KB 降到 24 KB。

### Version 10.4.3

> **集中修正前端顯示問題。**

* **修正**：佈景主題引入 Bootstrap 後，編輯器工具列只剩零星幾個圖示。
* **修正**：後台「回覆留言」框只顯示左半邊，右邊一片空白。
* **修正**：主控台不再報 `Prism is not defined`，也不再出現 `.../components/prism-*.min.js` 這類 404。
* **修正**：主控台不再每秒報一次 `wp is not defined`。

### Version 10.4.2

> **一輪安全強化。**

* **安全性**：升級兩個存在公開安全問題的元件 —— Mermaid 8.4.8 → 10.9.8、KaTeX 0.11.1 → 0.19.0。
* **安全性**：圖片貼上新增資源用量限制（以前一張高壓縮圖片就可能耗盡伺服器記憶體）。
* **安全性**：後台管理頁面的權限檢查、請求檢查與日誌脫敏一併收緊。
* **安全性**：前端依賴 axios 0.19.2 → 1.20.0。
* **修正**：Mermaid 圖表以前在正文裡根本無法顯示。
* **修正**：升級動作不再要求有人已登入後台（以前只有訪客造訪時，升級可能一直不執行）。
* **調整**：前台不再預設移除 WordPress 的區塊樣式；需要時用 `editormd_dequeue_block_styles` 過濾器開啟。

### Version 10.4.1

> **修正 10.3.0 / 10.4.0 的安裝包在後台不顯示外掛資訊。**

* **修正**：安裝包裡外掛主檔頭部的版本號、作者、所需 WordPress / PHP 版本等資訊遺失，
  導致後台「上傳外掛」介面看不到版本與作者，也偵測不到更新。
* 現已加入自動檢查，這類資訊再被誤刪會導致打包直接失敗。

### Version 10.4.0

> **修正公式解析的老問題，並新增可選的圖片尺寸寫法。不寫尺寸的圖片，顯示結果與以前完全一樣。**

* **修正**：程式碼區塊與行內程式碼裡的 `$` 不再被當成公式渲染。
* **修正**：區塊公式不再重複渲染。
* **修正**：正文裡湊巧成對的 `$`（如「價格從 $100 漲到 $200」）不再被當成公式。
* **修正**：xmlrpc 請求報錯。
* **新增**：圖片網址後可加尺寸 —— `![說明](圖片 =600)` / `=600x400` / `=x400`。
  寬螢幕依設定尺寸顯示，窄螢幕等比縮放、不變形。

### Version 10.3.0

> **本維護版從這裡開始：修安全問題、相容新版 WordPress。**
> 原版自 10.2.1 起停止更新，並已於 2025-04-09 因安全問題被 WordPress.org 下架。

* **安全性**：修掉公式渲染的儲存型 XSS —— 在文章**或留言**裡發一段特定內容就能執行腳本。
* **安全性**：修掉圖片貼上介面的未授權上傳 —— 補上權限與來源檢查、依內容判斷真實圖片類型、限制大小。
* **安全性**：修掉圖床代理可被當成跳板的問題，改為固定的上游白名單。
* **安全性**：修掉後台頁面的越權、設定項寫入未檢查、後台每個請求都同步請求外部網址導致卡頓等問題。
* **修正**：帶特定 Cookie 即白畫面、移除外掛時報錯等。
* **調整**：預設不再從第三方 CDN 載入編輯器腳本與樣式。
* **相容**：相容 WordPress 7.1 與 PHP 7.4 – 8.4，修掉會讓前台與後台同時白畫面的致命問題。
* **修正**：心智圖設定項遺失、Mermaid 的預設值被誤寫進 KaTeX。

---

## 原版历史

下面这些是**原版**（10.2.1 及更早，直到 1.0）的更新记录，原样保留，方便对照。

### Version 10.2.1

#### 1. BUG修复

* 修复特殊情况下包含`$`的文本无法显示的BUG（[#488](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/488)，感谢[@Clloz](https://github.com/Clloz)、[@aixiangfei](https://github.com/aixiangfei)）
* 修复由于代码逻辑导致PHP警告的问题（[#486](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/486)，感谢[@RichardZhang2019](https://github.com/RichardZhang2019)）

------

#### 1. BUG Fixes

* Fix the issue causing blank post when it contains `$` in some cases. （[#488](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/488)，Thanks[@Clloz](https://github.com/Clloz)、[@aixiangfei](https://github.com/aixiangfei)）
* Fix some PHP Warnings. （[#486](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/486)，Thanks[@RichardZhang2019](https://github.com/RichardZhang2019)）

------

#### 1. BUG修復

* 修復特定情況下包含`$`的文本無法正常顯示的問題（[#488](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/488)，鳴謝[@Clloz](https://github.com/Clloz)、[@aixiangfei](https://github.com/aixiangfei)）
* 修復由於代碼邏輯有誤導致PHP警告的問題（[#486](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/486)，鳴謝[@RichardZhang2019](https://github.com/RichardZhang2019)）


### Version 10.2.0

#### 1. 体验提升

* 使用Webpack进行代码构建，体积缩小30%，页面加载速度更快
* 图床功能支持与sm.ms用户绑定，并支持sm.ms图床管理功能，便于更高效管理上传的图片
* 整理代码格式，完善注释，去除冗余代码，便于理解、维护、二次开发
* 编辑器支持鼠标悬浮提示，便于快速了解工具栏各按钮的用途
* 新增高亮语法支持，与Typora功能一致，现在可以使用`==高亮==`来实现高亮文本（[#467](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/467)）
* 粘贴图片时新增Loading窗口，避免误操作导致粘贴上传失败
* 设置菜单选择编辑器/预览/代码高亮样式时支持预览，帮助您更快找到喜欢的样式
* 升级后将为您跳转到发行注记页面，帮助您更快了解最新版本的更新内容

#### 2. 故障修复

* 修复自定义风格在后台无法正常预览的问题
* 修复评论功能相关故障（[#455](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/455)）
* 不再误解析pre标签中的LaTeX代码
* 不对多行LaTeX中的$符号进行循环解析（[#411](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/411)）
* 修复特殊情况下新标签页打开链接功能无法生效的BUG（[#457](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/457)）
* 修复粘贴图片时连带文件名一同粘贴到编辑器的问题
* 修复后台评论无法预览的问题
* 修复全屏后窗口大小无法自适应的BUG
* 进一步解决正文中`$`符号被误识别为LaTeX公式导致文章空白的BUG（[#420](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/420)）
* 解决包含`_`的LaTeX公式被误解析为`<em>`的BUG（[#411](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/411)）
* 修复服务器外网不通情况下静态资源版本检查功能未设置超时导致加载缓慢的问题
* 修复在开启思维导图情况下无法启用前端评论功能的问题

#### 3. 安全加固

* 后台编辑器预览时不再渲染如`<form>`、`<audio>`、`<video>`、`<scripts>`等与排版无关的标签（[#428](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/428)）

#### 4. 其他

* 合并Editor.md代码到本项目主干（原作者@pandao已不再更新，合并便于二次开发，在未来版本实现更多功能）
* 新增依赖库相关开源协议与版权信息，规范本项目开源质量
* 升级相关依赖库到最新版本，避免安全隐患
* 修改本地化相关文本（[#458，感谢@zkl2333](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/458)）
* 兼容WordPress 5.5版本

---

#### 1. Experience Enhancements

* Using webpack to build JavaScript code, reducing the final `.min.js` size by 30%, making WP Editor.md to load faster.
* Image uploading to sm.ms now supports binding to sm.ms user account, and also add sm.ms image management functionality into the settings page of WP Editor.md.
* Reformat the code, enhancing the maintainability, security and performance, also makes it more easily to add more feature.
* Supports mouse hover tip in editor toolbar, makes it more easily to know each button's meaning in the toolbar.
* Add markdown highlight support, just like Typora, now you can use `==highlight==` to highlight any text you want. ([#467](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/467))
* Add loading screen when uploading image, to prevent unexpected user actions to interrupt the uploading process.
* Supports previewing in Editor / Markdown / Code Highlight settings, helps you find your favorite style more efficiently.
* Display the release note after a successful update (like this one), to help you know more about the newest updates.

#### 2. Bug Fixes

* Fix custom code highlightin style can't property displayed in editor.
* Fix markdown comment infinite loading issue. ([#455](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/455))
* WP Editor.md will no longer extract and renderding the LaTeX code in `<pre>` tag.
* WP Editor.md will no longer recursively rendering the `$` sign in LaTeX code. ([#411](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/411))
* Fix issue causing "Open links in new page" function failed to work. ([#457](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/457))
* Fix redundant filename when copy an image file and paste it into editor when image pasting is enabled.
* Fix issue in comments management causing comments invisible.
* Fix issue when the editor window is set to fullscreen then set it back to normal mode, the editor will not auto adapt screen width.
* Further fix the issue causing all post content invisible by the `$` sign. ([#420](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/420))
* Fix the issue that the `_` sign in LaTeX formula would be incorrectly rendered as `<em>` like it in Markdown. ([#411](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/411))
* Fix the slow loading issue when your server has a poor internet connection.
* Fix the issue causing markdown comment load infinitely when MindMap was enabled.

#### 3. Security Reinforcements

* Editor will not preview the labels unrelated to typography like `<form>``<audio>` `<video>` `<scripts>` and more. ([#428](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/428))

#### 4. Other

* Merge Editor.md source code into the editor. (Original author @pandao had not working on this for nearly four years, but WP Editor.md will still add more features into it)
* Add license in every open source project dependency, enhancing the open source quality of WP Editor.md.
* Update dependency into the newest version to avoid any security issue.
* Edit some localization text. ([#458，Thanks to @zkl2333](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/458))
* Compatibility work to make it works with WordPress 5.5.

---

#### 1. 體驗提升

* 使用Webpack進行代碼構建，體積縮小30%，頁麵加載速度更快
* 圖床功能支援與sm.ms用戶綁定，並支援sm.ms圖床管理功能，便於更高效管理上載的圖片
* 整理代碼格式，完善註釋，去除冗餘代碼，便於理解、維護、二次開發
* 編輯器支援滑鼠懸浮提示，便於快速了解工具欄各按鈕的用途
* 新增高亮文法支援，與Typora功能一緻，現在可以使用`==高亮==`來實現高亮文本（[#467](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/467)）
* 粘貼圖片時新增Loading視窗，避免誤操作導緻粘貼上載失敗
* 設定菜單選擇編輯器/預覽/代碼高亮樣式時支援預覽，幫助您更快找到喜歡的樣式
* 升級後將為您跳轉到發行註記頁麵，幫助您更快了解最新版本的更新內容

#### 2. 故障修複

* 修複自定義風格在後臺無法正常預覽的問題
* 修複評論功能相關故障（[#455](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/455)）
* 不再誤解析pre標簽中的LaTeX代碼
* 不對多行LaTeX中的$符號進行循環解析（[#411](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/411)）
* 修複特殊情況下新標簽頁打開鏈接功能無法生效的BUG（[#457](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/457)）
* 修複粘貼圖片時連帶文件名一同粘貼到編輯器的問題
* 修複後臺評論無法預覽的問題
* 修複全屏後視窗大小無法自適應的BUG
* 進一步解決正文中`$`符號被誤識別為LaTeX公式導緻文章空白的BUG（[#420](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/420)）
* 解決包含`_`的LaTeX公式被誤解析為`<em>`的BUG（[#411](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/411)）
* 修複服務器外網不通情況下靜態資源版本檢查功能未設定超時導緻加載緩慢的問題
* 修複在開啓思維導圖情況下無法啓用前端評論功能的問題

#### 3. 安全加固

* 後臺編輯器預覽時不再渲染如`<form>`、`<audio>`、`<video>`、`<scripts>`等與排版無關的標簽（[#428](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/428)）

#### 4. 其他

* 合並Editor.md代碼到本項目主幹（原作者@pandao已不再更新，合並便於二次開發，在未來版本實現更多功能）
* 新增依賴庫相關開源協議與版權信息，規範本項目開源質量
* 升級相關依賴庫到最新版本，避免安全隱患
* 修改在地化相關文本（[#458，感謝@zkl2333](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/458)）
* 兼容WordPress 5.5版本

### Version 10.1.2

* 紧急修复无法从插件页面打开设置的BUG

---

* An urgent fix the issue that can't open plugin's settings in plugins page.

### Version 10.1.1

* 修复部分代码高亮主题在编辑页面存在样式错误的问题
* 更新相关JavaScript依赖到最新版本
* 缩减代码体积，提升加载性能
* 新增静态资源地址一键重置功能，避免误操作出现错误后无法恢复
* 完善调试信息，新增一键导出调试信息功能
* 修复sm.ms图床无法使用的BUG(#427)
* 修复前台评论功能和兼容模式冲突的BUG
* 新增繁体中文本地化
* 修复自定义代码高亮样式无法加载的问题(#425)
* 优化安装后的默认配置，增强可用性
* 修复无法从插件页面打开设置的BUG(#429)
* 进一步修复包含$符号的文本被误识别为LaTeX文本的BUG(#420)
* 修复WordPress媒体按钮无法添加短标签到编辑器的BUG(#433)

---

* Fix issues with code highlighting styles the in post edit page.
* Update JavaScript dependencies to the newest version.
* Reducing code sizes and increase the loading performance.
* Add "Use Local" ans "Use CDN" buttons in static resource settings form for easily resetting resource addresses.
* Improving the information in debugging info and add features for exporting these info.
* Fix the issue when using sm.ms image hosting service.(#427)
* Fix the issue with post comment editor ans compatible mode conflicts.
* Add Traditional Chinese Localization support.
* Fix the issue when using customized code highlight styles.(#425)
* Optimize the default configurations after installed for better usability.
* Fix the issue that can't open plugin's settings in plugins page.(#429)
* Fix the issue that post text contains character $ was misinterpreted as LaTeX code.(#420)
* Fix the issue that can't add shortcode properly into editor from WordPress's Media Buttons.(#433)

### Version 10.1.0

* 修复启用LaTeX情况下文章内包含`$`符号导致内容空白的BUG（#359）
* 修复编辑器中字数统计不准确的问题
* 修复与PHP7.4的不兼容
* 修复列表中无法插入多行代码的BUG
* 新增实时字数统计功能
* 新增在新窗口打开链接功能（需手动启用）
* 将设置菜单变更到『设置』板块，符合插件一般规范
* 优化WordPress媒体管理器图片添加用户体验

---

* Fix the issue causing the page being blank when containing the `$` character in LaTeX-enabled editor. (#359 & #390)
* Fix the issue with inaccurate word counts in the editor.
* Fix the incompatibility with PHP7.4. (#399)
* Fix the issue when inserting multiline code in lists. (#392)
* Add the real time word count feature.
* Add the open in new tab feature (manually enable in plugin settings).
* Move the settings menu to Settings menu of the WordPress admin page.
* Optimize the experience of inserting image using the WordPress Media Utilities.

### Version 10.0.8

* 修复与WordPress5.3版本不兼容的问题
* 解决部分浏览器环境下图片粘贴功能无效的BUG
* 解决Nginx反向代理环境下`is_ssl()`判断失误的问题
* 解决`sm.ms`图床功能失效的BUG

### Version 10.0.7

* 移除富文本文章转换 markdown 文章功能
* 新增 mathjax 支持，编辑器暂未实现。

### Version 10.0.6

* 功能渲染转义问题修复

### Version 10.0.5

* 功能渲染转义问题修复
* 新增富文本文章转换 markdown 文章功能

### Version 10.0.4

* 修复思维导图地址错误问题
* code 块内 katex 公式不解析的问题
* 修复多媒体无法插入附件的问题
* 更新编辑器依赖文件和功能核心文件

### Version 10.0.3

* 修复思维导图地址错误问题

### Version 10.0.2

* 兼容Jetpack Markdown
* 兼容WordPress 5.0版本
* 修复思维导图地址错误问题

### Version 10.0.1

* 升级CodeMirror资源
* 升级Marked.js资源
* 添加丢失的资源

### Version 10.0.0

* 暂无

### Version 6.1.6

* 重写WP Media插入图片业务逻辑
* 修复markdown斜体语法编译失败的问题
* 添加切换编辑器时需要转义字符
* 优化一些配置文件

### Version 6.1.5

* [x] 优化公式矩阵换行（俩个\可以实现，不需要四个\）
* [x] 允许一些Markdown特征解析，不排除短代码
* [x] 修复公式和斜体语法引起的冲突
* [x] 修复编辑器公式可视化展示问题
* [x] 修复编辑器输入邮箱链接显示错误的问题
* [x] 修复编辑器配置逻辑错误的问题
* [x] 可自定义编辑器静态资源地址
* [x] 文章内超链接设置target属性
* [x] 修复编辑器按钮显示问题: Emoji按钮
* [x] 修复编辑器在某些情况下提示变量访问属性错误的问题
* [x] 修复编辑器在某些情况下图片粘贴失效的问题
* [x] 修复编辑器在特定分辨率错位的问题 eg: 1600 * 900
* [X] 原生编辑器和Markdown编辑器的切换 e.g: Visual Composer Support

### Version 6.1.4

* 添加PHP版本检测并添加温馨提示
* 前端资源逻辑判断的错误

### Version 6.1.3

* 修复历史上遗留下来的KaTex和代码块冲突问题 感谢[@jizhidemowang](https://github.com/jizhidemowang)代码贡献
* KaTeX公式风格习惯调整（单个$识别符号为行内公式，双个$识别符号为多行公式）
* 删除老版本资源，请升级最新版插件即可

### Version 6.1.2

* 修复前端文章跳转编辑器区域的问题
* 修复编辑器主题风格丢失的问题

### Version 6.1.1

* 修复KaTeX无法工作的问题

### Version 6.1.0

* 支持管理评论渲染
* 优化插件逻辑
* 修复图片上传失效的问题
* 完善KaTeX公式识别过程，支持$ S>1 $单个$识别公式

### Version 6.0.9

* 重写前端评论加载逻辑
* 修复编辑器列表和表格无法加载KaTeX科学公式的问题

### Version 6.0.8

* 优化插件逻辑
* 更换构建工具
* 支持前端评论渲染（注意：目前测试对默认主题兼容，其它主题未测试，如果有问题请及时反馈！）

### Version 6.0.7

* 修复mermaid在某些情况下失效的问题
* 添加分页符和摘要符可视化样式
* 提高对其他插件的兼容性

### Version 6.0.6

* Editormd核心文件更新
* 修复语法高亮选项逻辑警告
* 修复移动端错位的问题
* 修复PrismJS自动加载不规范加载css的问题

### Version 6.0.5.1

* -

### Version 6.0.5

* 添加版本最低支持
* 添加Mermaid选项配置
* 图片粘贴支持SM图库
* 图片上传支持SM图库
* 升级KaTeX版本为0.10.0-beta
* 升级PrismJS版本为1.15.0

### Version 6.0.4

* 添加Prism Copy功能
* 修复代码块含有公式被解析的问题
* 优化前端脚本显示
* 编辑器脚本加载优化
* 扩展插件链接的错误
* 添加隐藏捐赠信息选项
* 更新Markedjs版本为0.4.0 [#158](https://github.com/LuRenJiasWorld/WP-Editor.md/issues/158)
* 时序图的优化

### Version 6.0.3

* 取消单个$公式识别
* 重写Mermaid渲染，比之前快几百毫秒
* 修复Editor.md Prism不可自定义URL的问题
* 优化代码语言显示多余DOM的问题
* 更新Mermaid 8.0.0-rc.8

### Version 6.0.2

* 修复mindMap已知bug
* 修复KaTeX公式已知bug
* 其它代码改善

### Version 6.0.1

* fix the prismjs bug

### Version 6.0.0

Oh,Sorry, the version of WP Editor.md 6.0 replaces the flowchart and timing diagram features, using the more powerful [Mermaid.js](https://mermaidjs.github.io) tool. Thank you for using WP Editor.md and have a good time!

6.X版本已经废弃原有的时序图和流程图，启用更加强大的[Mermaid](https://mermaidjs.github.io)绘图工具。有原来的需求请不要升级！

* Support Mermaid(FlowChart,SequenceDiagram and GantDiagrams)
* 新的绘图工具(支持流程图，时序图和甘特图)
* 一些问题的修复


### Version 5.0.8

* 修复编辑器在特列的情况下，出现关闭按钮的情况
* 修复编辑器在全屏下被其它样式遮住
* 添加Mind Map(思维导图)支持
* 修复触屏屏幕的支持

### Version：5.0.7

* 添加Prism自定义风格选项
* 添加代码语言
* 修复前端调用自带编辑器引起的问题（类似投稿功能）

### Version：5.0.6

* 修复prism失效问题

### Version：5.0.5

* 优化Prism自动加载模式（大幅度提升性能）
* 解决一些加载问题

### Version：5.0.4

* 添加Prism Tomorrow Night主题风格
* 更换编辑器语法高亮引擎为Prism
* 修复debugger警告提示问题
* 添加KaTeX兼容选项

### Version：5.0.3

* 修复单独加载KaTeX失效的问题
* 修复暗色系主题标题看不清的问题
* 添加兼容模式（如果前端页面不正常请启用该选项）
* 添加调试信息

### Version：5.0.2

* 优化公式展示(KaTeX：displayMode)
* 添加JSDelivr CDN
* 添加[~~md~~]语法支持
* 添加图像粘贴（还有些小问题，类似web粘贴和QQ的截图正常使用，像在桌面粘贴图像会失效）
* 完善一些翻译

### Version：5.0.1
 
* 针对PHP 7.2.X某些函数做了兼容，感谢某个朋友！
* 修复重要的bug

### Version：5.0

**改版亮点：**
* 5.0版本插件代码重构
* 插件代码遵循 PSR-4 规则编写
* 大部分逻辑业务重写，业务分明，方便扩展
* 精简无用代码，大大提升插件资源性能
* 提高插件对主题以及其它插件的兼容性

**改版细节：**
 1. 允许某些HTML标签上的`markdown`属性 [Jetpack #9366](https://github.com/Automattic/jetpack/pull/9366/commits/427065a5c56aaf1d850fd10396c2afdfaf6f313a)
 1. 升级PHP Markdown内核版本为[1.8.0](https://github.com/michelf/php-markdown#version-history)
 1. 升级Editor.md依赖库版本，详细请见[Editormd](https://github.com/LuRenJiasWorld/Editormd)
 1. 全新KaTeX科学公式的前端页面渲染优化
 1. 提高KaTeX科学公式的易用性（行内公式和多行公式注意事项请看文档）
 1. 修复Prism新版本PHP语法失效
 1. 提高设置选项的易用性
 1. [TOC]不分大小写
 1. 新增十几套代码主题风格（夜间风格：dark + pastel-on-dark）

 ### 4.1
 
* 修复多行公式转义失败的问题
 
### 4.0
 
* 增强xss安全性
* 修复关闭同步滚动后输入内容右侧不更新的问题
* 修复关闭同步滚动后输入内容右侧滚动条跳转的问题
* 修复上个版本有几率公式失效的问题
* 修复编辑器marked重要漏洞
* 优化公式/时序(序列)图/流程图逻辑
* 优化列表语法逻辑 @感谢未知朋友修改
* 升级Underscore版本为1.8.3
* 升级Marked版本为0.3.17
* 升级Sequence Diagram版本为2.0.1
* 升级Raphael版本为2.2.7
* 升级FlowChart版本为1.10.0
* 升级Katex版本为0.9.0
* 升级Emojify.js版本为1.1.0
 
### 3.8
 
* 不可描述的修复
 
### 3.7
 
* 修复Katex逻辑小于号失效
 
### 3.6

* 兼容新版本Jetpack（新建文章出现500的错误）
* 修复Jetpack核心问题
 
### 3.5
 
* 升级一些库文件
* 修复一些问题
 
### 3.4
 
* 取消XSS过滤
* 添加描点支持
* 修复一些bugs
 
### 3.3
 
* 修复加粗语法和插入图片语法导致部分语法失效的问题
* 修复编辑器在独立页面失效的问题
* 修复描点和脚注的过滤问题 Thank for [@David Kuo](https://github.com/david50407)
* 修复多媒体文件插入逻辑错误
* 支持xss和editor.md自定义外链，为后期方便扩展
* 添加文章目录按钮工具栏
* 添加插件的兼容通知
 
### 3.2
 
* 修复图片粘贴失效的问题
* 修复多媒体附件不同格式导致的一系列的错误
* 修复保存设置重定向问题
* 添加对时序图风格的支持
* 添加音频/视频媒体的短代码的支持
 
### 3.1
 
* fix some bugs
 
### 3.0
 
* 重写后台选项框架
* 修复流程图和时序图渲染问题[#45](https://github.com/LuRenJiasWorld/WP-Editor.MD/issues/45) [46](https://github.com/LuRenJiasWorld/WP-Editor.MD/issues/46)
* 修复Prism高亮文件重复加载的问题[#38](https://github.com/LuRenJiasWorld/WP-Editor.MD/issues/38) Thank for [@giuem](https://github.com/giuem)
* 修复短代码引起的问题
* 其他一些问题的修复
 
### 2.8
 
* Fix Some Bugs
 
### 2.7
 
* `video`标签支持
* 支持Pjax环境的语法高亮
* 支持GFM Task Lists
 
### 2.6
 
* 支持流程图
* 支持时序图/序列图
* Fix bug for touch device [@TechCiel](https://github.com/TechCiel)
* 修复(s)ftp协议过滤的问题
 
### 2.5
 
* 优化科学公式加载
 
### 2.4
 
* 优化科学公式加载
 
### 2.3
 
* 修复上标和下标被过滤的问题
* 修复居中标签被过滤的问题
* 修复公式行内展示问题
* 更新marked.js版本
* 更新CodeMorror为最新版本
* 更新对新公式语法支持
* 支持评论语法高亮
* 增强文章渲染
 
### 2.2
 
* 优化图片粘贴逻辑代码
* 优化语法高亮逻辑
* 更新同步预览开关
* 修复某些md语法被过滤的问题
* 更新翻译
 
### 2.1
 
* 支持图片粘贴上传
* 支持预览窗口是否同步滚动
* 前端KaTeX科学公式和编辑器一致
* 修复部分语法高亮失效的问题
 
### 2.0
 
* fix bugs
 
### 1.9
 
* 修复toc被xss过滤的问题
* 支持自定义KaTeX加载地址
* 优化加载配置文件
* 修复`<!--more-->`被过滤的问题
 
* Repair toc xss filter
* Supports custom KaTeX load address
* Optimize the loading of the configuration file
* Fix `<! - more ->` filtered
 
### 1.8
 
* 修复Jetpack已存在的问题 [Github Jetpack #7107](https://github.com/Automattic/jetpack/pull/7107)
* 支持LaTeX公式
* 支持Prism识别代码语法高亮，感谢[@Kewell Tsao](https://github.com/kewell-tsao)和[@Giuem](https://github.com/giuem)
* 支持删除线Markdown语法
* 支持Toc文章目录功能，需要插件支持
* 支持html解析开关
* 优化编辑器显示
* 修复一些bug
 
* Fix Jetpack already exists [Github Jetpack #7107](https://github.com/Automattic/jetpack/pull/7107)
* Support LaTeX formula
* Support Prism recognition code syntax highlight, thanks for [@Kewell Tsao](https://github.com/kewell-tsao) and [@Giuem](https://github.com/giuem)
* Support to remove the line Markdown syntax
* Support Toc article directory function, need plug-in support
* Support html resolution switch
* Optimize the editor display
* Fix some bugs
 
### 1.7
* 修复某些情况下语法高亮渲染失败的问题;
* 修复设置超链接错误的问题,感谢@[giuem](https://github.com/giuem);
* 修复某些情况下启用选项会失效的问题;
* 修复某些情况下前端语法高亮会失效的问题;
* 修复后台回复快捷键丢失的问题;
* 更换语法高亮库为Prism,感谢@[千千](https://www.dreamwings.cn/)提供核心代码;
 
* Fixed some cases where syntax highlighting failed to render the problem;
* Fix the problem of setting hyperlinks,thank @[giuem](https://github.com/giuem);
* Fixed a problem where the option was disabled in some cases;
* Fixed some cases where the front-end syntax highlighting would fail;
* Repair background back to the shortcut keys lost;
* Replace the syntax highlight library for Prism, thanks @[千千](https://www.dreamwings.cn/) provide the core code;
 
### 1.6
* 修复样式被覆盖的问题;
* 支持国际化;
* 支持前端语法高亮主题更换，[详细](https://iiong.com/wordpress-plugins-wp-editormd.html#support_highlight_library);
* 从WP多媒体库插入图片语法转换成Markdown;
* 兼容Jetpack插件;
* 修复一些问题;
 
* Fix style is covered by the problem;
* Support internationalization;
* Support front-end syntax highlight theme replacement, [more](https://iiong.com/wordpress-plugins-wp-editormd.html#support_highlight_library);
* From the WP multimedia library to insert the image syntax into Markdown;
* Compatible with Jetpack plugin;
* Fix some bugs;
 
### 1.5
* 删除WordPress不支持的Markdown语法快捷键;
* 添加Emoji表情支持;
* 添加暗系风格主题支持;
* 添加前端语法高亮支持;
* 修复Jetpack Markdown漏洞;
* 修复某些情况下无法解析Markdown的问题，[Github #3](https://github.com/LuRenJiasWorld/WP-Editor.MD/issues/3);
 
* Remove WordPress unsupported Markdown syntax shortcuts;
* Add Emoji support;
* Add dark theme support;
* Add syntax highlighting support;
* Repair the Jetpack Markdown vulnerability;
* Fixed some cases can not be resolved Markdown the problem,[Github #3](https://github.com/LuRenJiasWorld/WP-Editor.MD/issues/3);
 
### 1.4
* 修复安全性功能;
* 除去Emoji表情支持;
 
* Repair the security feature;
* Remove Emoji expression support;
 
### 1.3
* 支持WP多媒体文件插入;
* 一些样式错位修复;
* 提高插件稳定性;
 
* Support WP Media module;
* Some style dislocation repair;
* Eliminate the unstable factors;
 
### 1.2
* 修复编辑器无法全屏的问题;
 
* Fix the editor can not be full screen;
 
### 1.1
* 重写框架，优化规范代码;
* 支持Emoji表情;
 
* Rewrite Rahmenverordnung Code-Optimierung;
* Support Emoji expression;
 
### 1.0
* 第一版本
 
* Initial version
