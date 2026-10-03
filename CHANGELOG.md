# WP Editor.md

### Version 10.4.0

> 本版继续由 [@tjsky](https://github.com/tjsky) 维护。相较 10.3.0，本版修复了公式解析与
> xmlrpc 的长期缺陷，并新增可选的图片尺寸语法。**不写尺寸的图片渲染结果与之前完全一致，
> 升级无需迁移。**

#### 1. 公式解析

* 修复**代码块 / 行内代码中的 `$` 被当成公式渲染**：原实现的跳过判断写作
  `htmlspecialchars_decode($element) === "<pre>"`，而真实代码块是 `<pre class="...">` 或
  `<pre><code>`，精确等值判断永远不成立。现改为「标签白名单（`pre`/`code`/`style`/`script`/`textarea`）
  + 嵌套深度计数」。这是上游被长期反复反馈的一类问题
* 修复**块级公式被二次解析导致重复渲染**：两个过滤器分别处理 `$$...$$` 与 `$...$`，
  后者判断「是否已处理」时查找的 class 名与实际输出的不一致。现改为单条正则、单次遍历
* 修复**正文中误配对的 `$` 被渲染成公式**：例如 `function update( $a, $b )`、
  `价格从 $100 涨到 $200`。内联公式增加两道防护 —— 定界符须紧贴内容、内容须含字母/数字/反斜杠
  （可用过滤器 `editormd_katex_require_tight_delimiters` 关闭）
* 输出侧的 `esc_html()` 转义保持并扩展到新的统一回调中，10.3.0 修复的存储型 XSS 不会回退

#### 2. 缺陷修复

* 修复 **xmlrpc 请求下抛出 `Class 'EditormdApp\IXR_Message' not found`**
  （`src/App/WPComMarkdown.php`）。该文件声明了命名空间，而 include 进来的 `IXR_Message`
  位于全局命名空间，需要前导反斜杠。**采纳上游 [PR #546](https://github.com/LuRenJiasWorld/WP-Editor.md/pull/546)** 的修法

#### 3. 新功能

* 新增**图片尺寸语法**（可选）：`![alt](img.jpg =600)` / `=600x400` / `=x400`，
  可与 `"title"` 及 `{#id .class}` 共存。尺寸以
  `width` + `max-width:100%` + `height:auto`（同时指定宽高时另加 `aspect-ratio`）的内联样式输出，
  因此宽屏按设定尺寸显示、窄屏等比缩放不变形，且不影响 Medium Zoom 一类看图插件
* 上游 PR [#602](https://github.com/LuRenJiasWorld/WP-Editor.md/pull/602) 与
  [#603](https://github.com/LuRenJiasWorld/WP-Editor.md/pull/603) 的**思路被参考但未直接合并**：
  #602 会丢失 `{#id .class}` 属性语法与 `ref_attr` 支持；#603 的输出处缺少转义，
  合并会回退 10.3.0 的安全修复，且其防护规则可被绕过

#### 4. 升级

* 升级器新增 `10.3.0 → 10.4.0` 迁移，仅推进版本号，无数据变更

------
### Version 10.3.0

> 本版由 [@tjsky](https://github.com/tjsky) 在原作者停止维护后继续维护。
> 上游自 10.2.1 起长期未更新，且该插件已于 2025-04-09 因安全问题被 WordPress.org 下架
> （关联 **CVE-2025-31035**，Stored XSS，影响 `<= 10.2.1`，上游无修复版本）。
> 本版**仅做安全加固与新版兼容性适配，未改动功能设计与数据结构，升级无需迁移**。

#### 1. 安全修复

* 修复 KaTeX 公式渲染链路的**存储型 XSS**（`src/App/KaTeX.php`）。实体编码的公式内容会被解码回真实字符后未转义直接输出，而 `the_content` 与 `comment_text` 均晚于保存期 kses，因此 kses 无法拦截。在文章或评论中投递 `$ &lt; img src=x onerror=... &gt; $` 即可执行脚本；因评论路径同样受影响，可利用门槛低于公开披露的同批问题
* 修复图片粘贴接口（`src/App/ImagePaste.php`）的**无鉴权任意文件写入**：补齐 `upload_files` 能力校验与 nonce（CSRF）校验；改为按文件内容判定真实图片类型；限制单次负载体积；改用 WordPress 官方 API 落盘并在失败时清理残留；图床上传开启 TLS 证书校验
* 修复 sm.ms 图床代理的 **SSRF / 开放代理**（`src/Pages/page/sm-ms-management/`）：上游地址改为固定白名单（仅 `https://smms.app/api/v2/*`），仅透传 `Authorization` 头，开启证书校验，补超时与响应体积上限，补同源校验
* 修复后台管理页渲染器的**授权缺陷**（`src/Pages/Pages.php`）：能力校验由角色名改为 `manage_options`，修掉一处恒真的授权分支，`page` / `entry` 参数改为白名单分发以消除路径穿越，`$_GET` 补 `isset` 与 `sanitize_key`，消除 ReDoS 正则
* 修复设置页**选项写入净化完全失效**（`src/Utils/Settings.php`）：第三方设置库只对显式声明 `sanitize_callback` 的字段生效，此前一个都未声明，等于全部选项未经净化即入库。改为挂 `pre_update_option_*` 按字段类型统一兜底
* 修复**盲 SSRF 与后台卡顿**：设置页初始化会同步拉取远端 `version.json`，使每个后台请求最坏阻塞数秒。改为 `wp_remote_get` + 白名单 + 缓存，并移出后台同步路径
* 修复携带 `wp-editormd-dev-logmode` Cookie 即触发致命错误（白屏）的缺陷
* 修复插件卸载时的语法级致命错误（文件顶层非法使用 `static`）
* 修复日志组件空实现导致所有日志调用抛异常的问题
* 修复调试与设置页面的输出未转义与令牌经 GET 泄漏问题
* 默认不再从第三方 CDN 加载编辑器脚本与样式，改由插件本地提供

#### 2. 兼容性

* `Tested up to` 更新为 WordPress 7.1，`Requires PHP` 明确为 7.4，实测通过 7.4 ~ 8.4
* 修复**插件主文件在插件加载期调用用户上下文函数导致整站白屏**的问题（`pluggable.php` 尚未加载）
* 修复 PHP 8.1+ `htmlspecialchars()` 默认 flags 变化影响存量内容渲染的问题（显式传入 flags 冻结行为）
* 修复 `$GLOBALS['pagenow']` 未定义、Mermaid 配置为空时生成非法 JS、思维导图地址为空时误加载当前页等问题
* 版本号比较改用 `version_compare`；移除会注销 WordPress 自带 jQuery 并改用 1.12.4 的分支
* 多站点激活与卸载改为逐站点处理

#### 3. 构建链

* `node-sass`（仅支持 Node ≤ 18）更换为 `dart-sass`，项目因此可在现代 Node 下构建
* 停止维护的 `webpack-parallel-uglify-plugin` 更换为 `terser-webpack-plugin`
* Vue 子项目修复 `tsconfig`、`peerDependencies` 与 `publicPath` 硬编码插件目录名的问题
* 清理 `assets/FrontStyle/FrontStyle.scss` 中一行残缺语句（libsass 静默忽略，dart-sass 会构建失败）
* 新增 GitHub Actions 发布流程，打 tag 即从源码构建并自动附加可安装 zip

#### 4. 顺带修复的历史遗留缺陷

* `editor_mindmap` 选项被 `editor_style` 数组整体覆盖，导致思维导图设置项丢失、功能失效
* Mermaid 的默认配置被错误地写入了 KaTeX 的默认值

------

### Version 10.4.0

> Maintained by [@tjsky](https://github.com/tjsky). Compared with 10.3.0, this release fixes
> long-standing issues in formula parsing and xmlrpc, and adds an optional image size syntax.
> **Images without a size are rendered exactly as before, so upgrading requires no migration.**

#### 1. Formula Parsing

* Fixed **`$` inside code blocks / inline code being parsed as formulas**. The original skip check was
  `htmlspecialchars_decode($element) === "<pre>"`, while real code blocks render as
  `<pre class="...">` or `<pre><code>` — an exact-match test that never succeeds. It now uses a
  tag whitelist (`pre`/`code`/`style`/`script`/`textarea`) with nesting-depth counting.
  This was among the most frequently reported issues upstream
* Fixed **block formulas being parsed twice (duplicate rendering)**: two filters handled `$$...$$` and
  `$...$` separately, and the class name the latter looked for never matched the emitted markup.
  Now a single regex in a single pass
* Fixed **mismatched `$` pairs in body text being rendered as formulas**, e.g.
  `function update( $a, $b )` or `price from $100 to $200`. Two guards were added for inline formulas:
  delimiters must be tight against the content, and the content must contain a letter, digit or backslash
  (disable via the `editormd_katex_require_tight_delimiters` filter)
* The output-side `esc_html()` escaping is retained and carried over to the new unified callback, so the
  stored XSS fixed in 10.3.0 cannot regress

#### 2. Bug Fixes

* Fixed **`Class 'EditormdApp\IXR_Message' not found` on xmlrpc requests**
  (`src/App/WPComMarkdown.php`). The file declares a namespace while the included `IXR_Message`
  lives in the global namespace, so a leading backslash is required.
  **Adopts the fix from upstream [PR #546](https://github.com/LuRenJiasWorld/WP-Editor.md/pull/546)**

#### 3. New Feature

* Added an **optional image size syntax**: `![alt](img.jpg =600)` / `=600x400` / `=x400`,
  combinable with `"title"` and `{#id .class}`. Sizes are emitted as an inline style of
  `width` + `max-width:100%` + `height:auto` (plus `aspect-ratio` when both dimensions are given), so
  images render at the requested size on wide screens, scale proportionally on narrow ones without
  distortion, and do not interfere with zoom plugins such as Medium Zoom
* The approaches of upstream PRs [#602](https://github.com/LuRenJiasWorld/WP-Editor.md/pull/602) and
  [#603](https://github.com/LuRenJiasWorld/WP-Editor.md/pull/603) were **taken as reference but not merged
  directly**: #602 drops `{#id .class}` attribute syntax and `ref_attr` support, while #603 lacks output
  escaping (merging it would regress the 10.3.0 security fixes) and its guard can be bypassed

#### 4. Upgrade

* The upgrader gains a `10.3.0 → 10.4.0` migration; it only advances the version number, no data changes

------
### Version 10.3.0

> Maintained by [@tjsky](https://github.com/tjsky) after the original author stopped maintaining this project.
> The plugin was **removed from WordPress.org on 2025-04-09 for a security issue** (related to
> **CVE-2025-31035**, Stored XSS, affecting `<= 10.2.1`), and upstream has never released a fix.
> This release contains **security hardening and compatibility fixes only** — no functional or
> data-structure changes, so upgrading requires no migration.

#### 1. Security Fixes

* Fixed a **stored XSS** in the KaTeX rendering path (`src/App/KaTeX.php`). Entity-encoded formula content is decoded back to real characters and then emitted without escaping; both `the_content` and `comment_text` run *after* save-time kses, so kses cannot stop it. Posting `$ &lt; img src=x onerror=... &gt; $` in a post **or a comment** executes script
* Fixed **unauthenticated arbitrary file write** in the image paste endpoint (`src/App/ImagePaste.php`): added `upload_files` capability and nonce (CSRF) checks, real image type detection by content, payload size limit, WordPress-native file handling with cleanup on failure, and TLS verification for remote uploads
* Fixed **SSRF / open proxy** in the sm.ms image host proxy: upstream is now a strict allowlist (`https://smms.app/api/v2/*`), only the `Authorization` header is forwarded, TLS verification is enabled, and timeouts/size limits/same-origin checks were added
* Fixed **authorization flaws** in the admin page renderer (`src/Pages/Pages.php`): capability check now uses `manage_options` instead of a role name, a permanently-true authorization branch was removed, `page`/`entry` are dispatched via an explicit allowlist (removing path traversal), and `$_GET` is validated/sanitized
* Fixed **completely ineffective option sanitization** (`src/Utils/Settings.php`): the bundled settings library only sanitizes fields with an explicit `sanitize_callback`, and none were declared. Now enforced centrally via `pre_update_option_*`
* Fixed **blind SSRF and admin slowdown**: settings init fetched a remote `version.json` synchronously on every admin request. Now uses `wp_remote_get` with an allowlist and caching, outside the admin sync path
* Fixed a fatal error (white screen) triggered by a specific cookie
* Fixed a syntax-level fatal error on plugin uninstall (illegal top-level `static`)
* Fixed an empty logger implementation that made every logging call throw
* Fixed unescaped output and token leakage in the debug/settings pages
* Editor scripts and styles are no longer loaded from a third-party CDN by default

#### 2. Compatibility

* `Tested up to` is now WordPress 7.1; `Requires PHP` is 7.4, verified on 7.4 – 8.4
* Fixed a **full-site white screen caused by calling user-context functions at plugin load time**
* Fixed `htmlspecialchars()` default-flag changes (PHP 8.1+) altering existing content rendering
* Fixed undefined `$GLOBALS['pagenow']`, invalid JS when Mermaid config is empty, and mind map script URL falling back to the current page
* Version comparison now uses `version_compare`; removed the branch that deregistered WordPress' bundled jQuery in favor of 1.12.4
* Multisite activation/uninstall now handled per site

#### 3. Build Chain

* `node-sass` (Node ≤ 18 only) → **`dart-sass`**, so the project builds on modern Node
* Unmaintained `webpack-parallel-uglify-plugin` → **`terser-webpack-plugin`**
* Vue sub-project: fixed `tsconfig`, peer dependencies, and hardcoded plugin-directory `publicPath`
* Removed a malformed statement in `assets/FrontStyle/FrontStyle.scss` (ignored by libsass, fatal for dart-sass)
* Added a GitHub Actions release workflow that builds from source and attaches an installable zip

#### 4. Other Latent Bugs Fixed

* `editor_mindmap` option was overwritten by the `editor_style` array, breaking the mind map feature
* Mermaid's default config was mistakenly written with KaTeX's defaults

------

### Version 10.4.0

> 本版繼續由 [@tjsky](https://github.com/tjsky) 維護。相較 10.3.0，本版修正了公式解析與
> xmlrpc 的長期缺陷，並新增可選的圖片尺寸語法。**不寫尺寸的圖片呈現結果與之前完全一致，
> 升級無需遷移。**

#### 1. 公式解析

* 修正**程式碼區塊 / 行內程式碼中的 `$` 被當成公式渲染**：原實作的跳過判斷寫成
  `htmlspecialchars_decode($element) === "<pre>"`，而真實程式碼區塊是 `<pre class="...">` 或
  `<pre><code>`，精確等值判斷永遠不成立。現改為「標籤白名單（`pre`/`code`/`style`/`script`/`textarea`）
  + 巢狀深度計數」。這是上游長期反覆被回報的一類問題
* 修正**區塊公式被二次解析導致重複渲染**：兩個過濾器分別處理 `$$...$$` 與 `$...$`，
  後者判斷「是否已處理」時查找的 class 名與實際輸出的不一致。現改為單條正規表示式、單次走訪
* 修正**正文中誤配對的 `$` 被渲染成公式**：例如 `function update( $a, $b )`、
  `價格從 $100 漲到 $200`。行內公式增加兩道防護 —— 定界符須緊貼內容、內容須含字母/數字/反斜線
  （可用過濾器 `editormd_katex_require_tight_delimiters` 關閉）
* 輸出側的 `esc_html()` 轉義保持並擴展到新的統一回呼中，10.3.0 修正的儲存型 XSS 不會回退

#### 2. 缺陷修正

* 修正 **xmlrpc 請求下拋出 `Class 'EditormdApp\IXR_Message' not found`**
  （`src/App/WPComMarkdown.php`）。該檔宣告了命名空間，而 include 進來的 `IXR_Message`
  位於全域命名空間，需要前置反斜線。**採用上游 [PR #546](https://github.com/LuRenJiasWorld/WP-Editor.md/pull/546)** 的修法

#### 3. 新功能

* 新增**圖片尺寸語法**（可選）：`![alt](img.jpg =600)` / `=600x400` / `=x400`，
  可與 `"title"` 及 `{#id .class}` 共存。尺寸以
  `width` + `max-width:100%` + `height:auto`（同時指定寬高時另加 `aspect-ratio`）的行內樣式輸出，
  因此寬螢幕依設定尺寸顯示、窄螢幕等比縮放不變形，且不影響 Medium Zoom 一類看圖外掛
* 上游 PR [#602](https://github.com/LuRenJiasWorld/WP-Editor.md/pull/602) 與
  [#603](https://github.com/LuRenJiasWorld/WP-Editor.md/pull/603) 的**思路被參考但未直接合併**：
  #602 會遺失 `{#id .class}` 屬性語法與 `ref_attr` 支援；#603 的輸出處缺少轉義，
  合併會回退 10.3.0 的安全性修正，且其防護規則可被繞過

#### 4. 升級

* 升級器新增 `10.3.0 → 10.4.0` 遷移，僅推進版本號，無資料變更

------
### Version 10.3.0

> 本版本由 [@tjsky](https://github.com/tjsky) 在原作者停止維護後繼續維護。
> 本外掛已於 2025-04-09 因安全問題遭 WordPress.org 下架（對應 **CVE-2025-31035**，
> Stored XSS，影響 `<= 10.2.1`），上游並無修復版本。
> 本版**僅進行安全強化與新版相容性調整，未更動功能設計與資料結構，升級無需遷移**。

#### 1. 安全性修正

* 修正 KaTeX 公式渲染路徑的**儲存型 XSS**（`src/App/KaTeX.php`）。實體編碼的公式內容會被解碼回真實字元後未轉義直接輸出，而 `the_content` 與 `comment_text` 皆晚於儲存期 kses，故 kses 無法攔阻。於文章**或留言**中投遞 `$ &lt; img src=x onerror=... &gt; $` 即可執行腳本
* 修正圖片貼上介面的**未授權任意檔案寫入**：補上 `upload_files` 能力與 nonce（CSRF）檢查、依檔案內容判定真實圖片類型、限制酬載大小、改用 WordPress 官方 API 寫入並於失敗時清理、遠端上傳啟用 TLS 憑證檢查
* 修正 sm.ms 圖床代理的 **SSRF / 開放代理**：上游位址改為固定白名單、僅轉送 `Authorization` 標頭、啟用憑證檢查、補上逾時與大小限制及同源檢查
* 修正後台管理頁渲染器的**授權缺陷**：能力檢查由角色名改為 `manage_options`、移除一處恆真的授權分支、`page` / `entry` 改為白名單分派以消除路徑穿越
* 修正設定頁**選項寫入淨化完全失效**的問題，改以 `pre_update_option_*` 統一依欄位型別處理
* 修正**盲 SSRF 與後台卡頓**：設定頁初始化會同步抓取遠端 `version.json`，改為 `wp_remote_get` 加白名單與快取
* 修正特定 Cookie 即觸發致命錯誤（白畫面）、外掛移除時的語法級致命錯誤、日誌元件空實作導致所有日誌呼叫拋出例外
* 預設不再自第三方 CDN 載入編輯器腳本與樣式

#### 2. 相容性

* `Tested up to` 更新為 WordPress 7.1，`Requires PHP` 明確為 7.4，實測通過 7.4 ~ 8.4
* 修正**外掛主檔於載入期呼叫使用者情境函式導致整站白畫面**的問題
* 修正 PHP 8.1+ `htmlspecialchars()` 預設 flags 變更影響既有內容渲染的問題
* 版本號比較改用 `version_compare`；移除會註銷 WordPress 內建 jQuery 的分支
* 多站點啟用與移除改為逐站處理

#### 3. 建置鏈

* `node-sass` 更換為 `dart-sass`，專案因此可在現代 Node 下建置
* 停止維護的 `webpack-parallel-uglify-plugin` 更換為 `terser-webpack-plugin`
* Vue 子專案修正 `tsconfig`、同儕依賴與寫死外掛目錄的 `publicPath`
* 新增 GitHub Actions 發行流程，推送 tag 即自原始碼建置並附上可安裝 zip

#### 4. 一併修正的歷史缺陷

* `editor_mindmap` 選項被 `editor_style` 陣列覆蓋，導致心智圖功能失效
* Mermaid 的預設設定被誤寫入 KaTeX 的預設值

------

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
