# WP Editor.md 更新日志

这里只记**本维护分支**的改动（从 10.3.0 开始）。原版 10.2.1 及更早的历史附在文末。

内容按语言分成三块，三块说的是同一件事：**简体中文 · English · 繁體中文**。
看不懂的术语，多半是代码里的名字，保留原样反而好查。

[简体中文](#简体中文) · [English](#english) · [繁體中文](#繁體中文) · [原版历史](#原版历史)

---

## 简体中文

### Version 10.5.0

> **这次给访客评论做了个「简版编辑器」，顺手清掉了几处旧毛病。**
> 另外：第三方图床（sm.ms）功能整体下线，前台编辑器的配色不再跟随后台。
> **没有改数据结构，升级不用迁移。**（sm.ms 的遗留设置会在升级时自动清掉。）

#### 一、评论区的老问题：为什么「预览里有、发出去就没了」

WordPress 把评论当成**不可信内容**，只允许里面出现 15 个标签：
`a` `abbr` `acronym` `b` `blockquote` `cite` `code` `del` `em` `i` `q` `s` `span` `strike` `strong`。

图片（`img`）、代码块（`pre`）、表格、标题、列表、分割线……**统统会被系统删掉**。
可编辑器工具栏偏偏提供了这些按钮——预览是浏览器里画的，所以看着一切正常；
等到保存时被系统一剥，东西就没了。

这不是「WordPress 的硬限制」。插件可以通过 `wp_kses_allowed_html` 给评论多放行几个标签，
只是以前一直没做。

#### 二、新增：访客评论简版编辑器（默认关闭）

* 开关在：**设置 → WP Editor.md → Basic Settings → 给前台评论使用简版编辑器**。
* 打开后，评论框只留 8 个按钮：**粗体、斜体、删除线、引用、行内代码、链接、图片、纯预览**。
  收起来的（标题、列表、分割线、表格、代码块、双栏预览、全屏、清除、关于），
  要么系统会删掉、要么在手机上只会占地方。
* **默认单栏**，输入框高 200px，字号 16px（小于 16px 时，iPhone 上点一下输入框会整页放大）。
* **图片对话框里去掉了「图片链接」一栏**：既然评论图片一律不能点，这一栏填了也白填。
* **窄屏下工具栏的提示气泡不再撑破页面**：以前手机上能把整页横向拖走，现在不会了。
* **真正生效的限制在服务端**，不是靠藏按钮。想让某个站点强制开关，
  用 `editormd_simple_comment_enabled` 过滤器。

打开后，评论里会发生这些变化：

* **链接不能点**：`[文字](http://x.com)` 会显示成「文字：`http://x.com`」。
  这是防止访客之间互相钓鱼，跟 SEO 无关（WordPress 本来就给评论链接加了 `rel="nofollow ugc"`）。
  页内跳转（`#` 开头）保留原样，`mailto:` 显示成纯文本。
* **图片不能点**：评论图片不再被链接包住；同时强制加上「懒加载」和「不发送来源页地址」
  两个属性，因为访客贴的图片地址可能是任意第三方网站。
* **公式、任务列表、代码块、emoji 短代码一并关闭**——预览和服务端同时关，免得两边显示不一致。
* **图片粘贴在评论里关闭**：访客没有上传权限，以前粘图只会得到一个「上传失败」。

#### 三、调试信息面板新增「评论语法」两块

**高级设置 → 调试信息**里，现在能看到评论区**真正生效**的标签清单，
每一行都标了是「WordPress 原生」还是「本插件额外放行」；以及本插件对评论做了哪些改造。

#### 四、顺手修好：升级器漏了两个分支，站点每次请求都重跑一遍初始化

升级器少写了 10.4.3 和 10.4.5 两个分支，停在这两个版本上的站点匹配不到升级路径，
版本号永远不前进——于是**每次访问都要重跑一遍激活流程**。现在补齐了。

#### 五、修好：后台每个页面都在弹「检验到插件资源包已过时」

升级后，写文章、审评论、看仪表盘……后台**每个页面**都有这么一条红字。
两个原因：一是资源包里的版本号没跟着一起升；二是**提示挂到了错误的钩子上**——
本该只在设置页显示的一行状态，被放大成了全后台通告。
现在只在插件设置页显示一行状态，并直接写出「资源包版本 X / 插件版本 Y」，一眼能看出哪里对不上。

#### 六、新增：前台编辑器配色是否跟随后台（默认关闭）

以前后台把编辑器设成深色，访客在浅色主题的站点上看到的评论框也是深色。
现在默认**不跟随**：前台用 Editor.md 自带的浅色样式，跟你的主题走；后台该什么样还什么样。
想自定义夜间样式，README 里列了可以挂钩子的选择器。

#### 七、修好：官方块主题下，评论框下方多出一块输入框

在 Twenty Twenty-Four / Twenty Five 这类官方块主题下，评论框下面会露出一块原生输入框，
里面是一堆 HTML，看着像「预览变成了源码」。
原因是块主题给评论框写的样式权重更高，盖住了插件「把这块中间元素藏起来」的规则。
现在插件不再跟主题比优先级，直接钉死。

#### 八、移除：第三方图床（sm.ms）上传功能

那家免费图床已经全面转向收费，继续留着只是徒增维护面。
本次一并删掉：上传分流、访问令牌与后台代理接口、配套的后台管理页，以及只为它引入的设置项。
**图片粘贴统一保存到本站媒体库**，升级时会自动清掉遗留的图床配置。

#### 九、设置页与文案调整

* 「支持前端评论」后面那句括号说明，挪到选项下方单独一行，和别的选项排版一致。
* PC 上把设置项标签列加宽，中文标签不再折成两行。
* 「高级设置 → 捐赠」末尾加了一段说明：本修改版只做安全加固与兼容适配，不单独接受捐赠。

#### 十、发布流程调整

GitHub Release 的「本版变更」改为**直接从本文件对应版本段落生成**，不再只给一个链接；
「维护分支累计改动」不再逐条列出，压缩成一句话。这样 Release 页面和本文件只有一个事实来源。

### Version 10.4.5

> **这次修的是「预览里是乱码、发出去却正常」。没有改数据结构，升级不用迁移。**

* **预览不认识插件的图片尺寸写法。** `![说明](图片 =600)` 这类写法只做在了服务端（保存那一刻才生效），
  预览用的是 Editor.md 自带的解析器，完全不认识，会把整行当普通文字原样吐出来。
  现在预览也认识这套写法，显示结果和服务端逐项对齐。
* **预览把普通文字当成公式。** 比如「价格从 $100 涨到 $200」、`function update( $a, $b )`、
  行内代码里的 `$`，都会被渲染成公式——而服务端是有防护的，这又是一处两边不一致。
  现在预览和服务端用同一套判断规则，同一篇文章两边的公式数量完全一致。
* 顺带修了测试脚本自己的 bug（它把内容里本该保留的反斜杠吃掉了，导致被拿去测试的文章本身就是坏的），
  并补上了一个新回归：把「编辑器预览」和「前台显示」的 DOM 抓下来逐项比对。
  以前的回归只测「保存后的结果」，**完全没测过编辑器预览**。

### Version 10.4.4

> **评论里的 Markdown 现在真的会被转换了；页面上也不再同时加载两套代码高亮库。**

* **访客评论的 Markdown 以前根本不生效。** 访客写的 `**粗体**`、`[链接](...)` 会**原样存进数据库**，
  前台看到的还是这些源码。编辑器里的预览是浏览器画的，所以写的时候看着一切正常。
  根因是这个开关藏在**「设置 → 讨论 → Markdown」**里，默认关闭、入口很深，
  还和插件自己的「支持前端评论 / 支持后台回复」没有任何联动。
  现在只要插件开着评论编辑器，评论的 Markdown 转换就一并启用，那个容易误导人的勾选框也隐藏了。
* 修了 `html_decode` 关闭时的一处解析问题：行首的 `>` 和链接标题里的引号被错误转义，
  导致**引用块渲染不出来**、**带标题的链接解析失败**。
* **页面上曾同时加载两套 Prism。** 一套是 Editor.md 自带的（286KB、132 种语言），
  和插件提供的那套互相覆盖，结果代码块没有复制按钮、没有语言标签。
  现在只保留插件这一套，同一个页面上 Prism 从 **302.5KB 降到 24.2KB**。

### Version 10.4.3

> **这次集中修前端：代码高亮的加载顺序、工具栏图标消失、后台回复框一片空白、控制台每秒刷屏。**

* 修 `Prism is not defined`：设置语言包路径的脚本跑在了 Prism 前面，现在改成挂在 Prism 之后。
* 修 `https://站点/components/prism-*.min.js` 这类 404。
* 修「主题引入 Bootstrap 之后，工具栏只剩零星几个图标」：
  按钮的类名和 Bootstrap 的全局样式撞了名，导致按钮全部透明堆叠。现在由插件钉回自己的样式。
* 修后台回复框**右半边永远是一片空白**：现在跟着预览开关走，关闭时占满整行。
* 修控制台**每秒刷一次**的 `wp is not defined`。

### Version 10.4.2

> **按一份代码审查报告，做了一轮小而集中的安全加固。没有改数据结构，升级不用迁移。**

* **升级两个有公开漏洞的组件**：Mermaid 8.4.8 → **10.9.8**、KaTeX 0.11.1 → **0.19.0**。
  老版本没有修复版可用，且恶意图表可以把安全等级降下来再执行注入内容。
* **顺带修好：Mermaid 图表以前在正文里根本渲染不出来**（输出的内联脚本必然语法错误）。
  现在改成数据驱动渲染，并且兼容旧格式，**历史文章不需要重新保存**。
* **图片粘贴接口补上资源用量限制**。以前只限制了请求体积，
  但一张几 MB 的高压缩 PNG 解压后能吃掉几百 MB 内存。现在在解码前就拦下来。
* **sm.ms 令牌不再下发到浏览器**，改由服务端注入；后台代理收窄为固定操作白名单。
* **后台 AJAX 授权收紧**：删掉「校验失败就看域名」的兜底，并移除未登录入口。
* 前端依赖 axios 0.19.2 → **1.20.0**。
* 升级/迁移**不再要求用户登录**（以前只有匿名访客访问时，迁移会一直不执行）。
* CI 新增安全检查，防止已修的问题被改回去。

### Version 10.4.1

> **修 10.3.0 / 10.4.0 的安装包在后台不显示插件信息。没有逻辑改动，升级不用迁移。**

* 安装包里，插件主文件头部的 `Version`、`Author`、所需的 WordPress / PHP 版本等信息**丢失了**，
  导致后台「上传插件」界面看不到版本和作者，更新检查也读不到版本号。
* 成因：这些行的写法和注释续行**一模一样**，被清理脚本当成注释删掉了。
  语法检查和代码比对都发现不了（注释本来就被排除在比对之外）。
  所以这次加了**专门的插件头校验脚本**和 **CI 闸门**：缺字段、或版本号对不上，直接构建失败。

### Version 10.4.0

> **修公式解析和 xmlrpc 的老问题，新增一个可选的图片尺寸语法。**
> **不写尺寸的图片，显示效果和以前完全一样，升级不用迁移。**

* **修「代码块里的 `$` 被当成公式渲染」**：原来的跳过判断永远不成立，
  现在改成按标签白名单加嵌套深度判断。这是上游被反复反馈的一类问题。
* **修「块级公式渲染两遍」**：两个过滤器各管各的，判断条件对不上。现在合并成一次处理。
* **修「正文里凑巧成对的 `$` 被当成公式」**：比如 `function update( $a, $b )`。
  行内公式加了两道判断，也可以用过滤器关掉。
* **修 xmlrpc 下报 `IXR_Message not found`**（采用上游 PR #546 的修法）。
* **新增：图片尺寸语法（可选）**：`![说明](图片 =600)` / `=600x400` / `=x400`。
  宽屏按设定尺寸显示，窄屏等比缩放、不变形。

### Version 10.3.0

> **原作者停止维护之后，本维护版从这里开始。核心是把安全问题修掉，并适配新版 WordPress。**
> 原插件已于 **2025-04-09 因安全问题被 WordPress.org 下架**
> （**CVE-2025-31035**，影响 10.2.1 及更早版本，上游没有修复版）。
> **没有改功能设计和数据结构，升级不用迁移。**

* **修公式渲染的存储型 XSS**：在文章**或评论**里发一段特定内容就能执行脚本。
  因为评论路径同样受影响，利用门槛比公开披露的那些还要低。
* **修图片粘贴接口的任意文件写入**：补上权限校验、按文件内容判断是不是真图片、限制体积。
* **修图床代理被当成跳板（SSRF / 开放代理）**：上游地址改为固定白名单，只转发必要的请求头。
* **修后台页面的越权、设置项保存不净化、后台卡顿（盲 SSRF）等一批问题**。
* 修「带某个 Cookie 就直接白屏」「卸载时报语法错误」「日志功能一调就抛异常」。
* **默认不再从第三方 CDN 加载编辑器脚本和样式**，改由插件本地提供。
* **适配 WordPress 7.1 和 PHP 7.4 ~ 8.4**，修掉「插件加载时调用了还不存在的函数，
  导致前台后台一起白屏」这种致命问题。
* **换掉已经用不了的构建工具**（`node-sass` → `dart-sass` 等），项目重新能构建，
  并加上了 GitHub Actions 自动打包发布。
* **顺手修的老毛病**：思维导图设置项丢失、Mermaid 的默认值被错误地写进了 KaTeX。

---

## English

### Version 10.5.0

> **A "simple editor" for visitor comments, plus a handful of long-standing annoyances cleaned up.**
> Also: the third-party image host (sm.ms) feature is gone, and the front-end editor no longer follows
> the admin colour scheme. **No data-structure changes, so upgrading needs no migration.**
> (Leftover sm.ms settings are cleaned up automatically on upgrade.)

#### 1. Why comments used to lose half of what you typed

WordPress treats comments as **untrusted input** and allows only 15 tags inside them:
`a` `abbr` `acronym` `b` `blockquote` `cite` `code` `del` `em` `i` `q` `s` `span` `strike` `strong`.

Images (`img`), code blocks (`pre`), tables, headings, lists and horizontal rules are **all stripped**.
The toolbar offered buttons for exactly those things — the preview is drawn in the browser, so it looked
fine until you saved, at which point the tags were removed.

This is not a hard WordPress limit: a plugin can allow extra tags for comments through
`wp_kses_allowed_html`. It just had never been done here.

#### 2. New: a simple editor for visitor comments (off by default)

* Switch: **Settings → WP Editor.md → Basic Settings → Use Simple Editor For Front Comments**.
* When on, the comment box keeps just 8 buttons: **bold, italic, strikethrough, quote, inline code,
  link, image, preview**. The rest are hidden — they would either be stripped by WordPress anyway,
  or simply eat screen space on a phone.
* **Single column by default**, 200px tall, 16px font (below 16px, iOS Safari zooms the page in when
  you tap the box).
* **The "image link" field is gone from the image dialog** — comments never get clickable images,
  so the field did nothing.
* **Tooltips no longer stretch the page on narrow screens** (on a phone the whole page used to be
  draggable sideways).
* **The real limit lives on the server**, not in hidden buttons. To force the switch on or off per site,
  use the `editormd_simple_comment_enabled` filter.

With it on, comments change like this:

* **Links are not clickable**: `[text](http://x.com)` renders as `text：http://x.com`.
  This is to stop phishing between visitors, not for SEO — WordPress already adds
  `rel="nofollow ugc"` to comment links. In-page anchors (`#…`) stay as-is; `mailto:` becomes plain text.
* **Images are not clickable**: comment images are no longer wrapped in a link, and they always get
  `loading="lazy"` plus `referrerpolicy="no-referrer"`, because a visitor can point at any third-party URL.
* **Formulas, task lists, code blocks and emoji shortcodes are all turned off** — on both the preview
  and the server, so the two cannot disagree.
* **Image paste is off for comments**: visitors have no upload permission, so it only ever produced
  an "upload failed" placeholder.

#### 3. Debug screen: a new "comment syntax" section

Under **Advanced Settings → Debugger** you can now see the tag list that is **actually in effect** for
comments, each line marked as "[WordPress native]" or "[added by this plugin]", plus what this plugin
changes about comments.

#### 4. Fixed: the upgrader missed two branches, so the site re-ran activation on every request

The upgrader had no branch for 10.4.3 or 10.4.5. Sites sitting on those versions matched nothing,
the version number never advanced, and the **activation routine ran again on every single request**.
Both branches are now in place.

#### 5. Fixed: "the plugin asset bundle is out of date" popped up all over the admin

After upgrading, every admin page — writing a post, reviewing comments, the dashboard — showed a red
notice. Two causes: the asset bundle's version number had not been bumped, and **the notice was printed
on the wrong hook** — a one-line status meant for the settings page was turned into a site-wide admin
notice. It now appears only on the plugin settings page, and it names both versions
("bundle 10.4.5 vs plugin 10.5.0") so the mismatch is obvious.

#### 6. New: choose whether the front-end editor follows the admin style (off by default)

The admin used to set a dark editor style, and visitors on a light theme saw a dark comment box too.
By default the front end now **does not follow**: it uses Editor.md's own light style and your theme.
The admin editor is unaffected either way. README lists the selectors you can hook for custom dark styling.

#### 7. Fixed: block themes showed a stray input box under the comment form

On official block themes such as Twenty Twenty-Four / Twenty Five, a raw textarea leaked out below the
comment box, showing a pile of HTML — it looked like "the preview turned into source code".
The block theme's CSS was more specific than the rule the plugin used to hide that intermediate element.
The plugin no longer competes on specificity; it pins the rule down.

#### 8. Removed: the third-party image host (sm.ms)

That free image host has moved to a paid model, so keeping it only added maintenance surface. Removed in
one go: the upload branch, the access token and proxy endpoint, the companion admin page, and the settings
that existed only for it. **Image paste now always saves to your own media library**, and leftover image
host settings are cleaned up on upgrade.

#### 9. Settings page and wording

* The parenthetical note after "Support Front Comment" moved to its own line under the option, matching
  the other options.
* The settings label column is wider on desktop, so Chinese labels no longer wrap onto two lines.
* The "Advanced Settings → Donate" section gained a note explaining that this maintained fork only does
  security and compatibility work and does not accept donations.

#### 10. Release process

The "What's new" section of a GitHub Release is now **generated directly from the matching version
section of this file**, instead of just linking to it. "Cumulative changes on this branch" is no longer
listed in full and is compressed into a single sentence — one source of truth for both.

### Version 10.4.5

> **This release fixes "it looks like garbage in the preview but is fine once published".**
> **No data-structure changes, so upgrading needs no migration.**

* **The preview did not understand the plugin's image-size syntax.** `![alt](img =600)` and friends are
  implemented on the server (applied at save time), but the preview uses Editor.md's own parser, which
  does not know the syntax at all and emits the raw Markdown. The preview now understands it, matching
  the server rule for rule.
* **The preview rendered ordinary text as formulas** — "prices from $100 to $200",
  `function update( $a, $b )`, or a `$` inside inline code. The server had guards for exactly that, so
  this was another preview/published mismatch. Both sides now share one set of rules, and the formula
  count matches on the same article.
* A **bug in the test harness itself** was fixed (it swallowed backslashes that should have been kept,
  so the test article was broken before it was even tested), and a new regression now compares the
  **editor preview** against the published page. Previous regressions only checked the saved result and
  **never touched the editor preview at all**.

### Version 10.4.4

> **Markdown in visitor comments is now actually converted, and pages no longer load two copies of the
> syntax highlighter.**

* **Markdown in visitor comments did nothing.** A visitor's `**bold**` or `[link](...)` was **stored
  verbatim**, so the front end showed the raw source. The in-editor preview is drawn in the browser,
  which is why it looked fine while typing. The switch lived under
  **Settings → Discussion → Markdown** — off by default, buried, and completely disconnected from the
  plugin's own "Support Front Comment / Support Reply Comment" options. Now, whenever the plugin has a
  comment editor enabled, comment Markdown is enabled with it, and the misleading checkbox is hidden.
* Fixed a parser issue that only appeared with `html_decode` off: a line-leading `>` and the quotes in a
  link title were escaped by mistake, so **blockquotes never rendered** and **links with titles failed
  to parse**.
* **Two copies of Prism used to load on the same page.** Editor.md's bundled copy (286 KB, 132 languages)
  overwrote the plugin's, so code blocks lost their copy button and language label. Only the plugin's
  copy remains now: on the same page, Prism went from **302.5 KB to 24.2 KB**.

### Version 10.4.3

> **A front-end fix-up: highlight loading order, missing toolbar icons, a blank admin reply box, and a
> console error firing once a second.**

* Fixed `Prism is not defined`: the script that sets the language pack path ran before Prism existed.
* Fixed the `https://example.com/components/prism-*.min.js` 404s.
* Fixed "after a theme loads Bootstrap, the toolbar only shows a couple of icons" — the button class name
  collided with Bootstrap's global style, making every button transparent and stacked.
* Fixed the admin reply box's **permanently blank right half**; it now follows the preview toggle.
* Fixed a `wp is not defined` console error that fired **once per second**.

### Version 10.4.2

> **Focused security hardening following a code review report. No data-structure changes, so upgrading
> needs no migration.**

* **Two bundled libraries upgraded**: Mermaid 8.4.8 → **10.9.8**, KaTeX 0.11.1 → **0.19.0**. The old
  lines have no fixed release, and a malicious diagram could downgrade the security level and execute
  injected content.
* **Incidental fix: Mermaid diagrams never rendered in post content at all** (the emitted inline script
  was always a syntax error). Rendering is now data-driven and backward compatible, so **existing posts
  do not need re-saving**.
* **Resource budgets added to the image paste endpoint.** It only limited request size before, while a
  few-MB compressed PNG can expand into hundreds of MB of memory. Requests are now rejected before decoding.
* **The sm.ms token is no longer sent to the browser**; the proxy was narrowed to a fixed operation allowlist.
* **Admin AJAX authorization tightened**: the "fall back to the host name when the nonce fails" path was
  removed, along with the logged-out entry point.
* Front-end dependency axios 0.19.2 → **1.20.0**.
* Upgrades/migrations **no longer require a logged-in user** (previously, a site only visited by anonymous
  users would never run them).
* CI gained a security check so that already-fixed issues cannot silently come back.

### Version 10.4.1

> **Fixes the 10.3.0 / 10.4.0 packages not showing plugin information in the admin. No logic changes, so
> upgrading needs no migration.**

* The plugin file header in the released package had **lost** `Version`, `Author` and the required
  WordPress / PHP versions, so the admin's "Upload Plugin" screen showed no version or author, and the
  update check could not read a version at all.
* Cause: those lines look **exactly** like block-comment continuation lines, so the comment-stripping
  script deleted them. Neither the syntax check nor the code comparison could catch it (comments are
  excluded from the comparison by design). This release adds a **dedicated plugin-header checker** and a
  **CI gate**: a missing field or a version mismatch now fails the build.

### Version 10.4.0

> **Fixes long-standing formula-parsing and xmlrpc issues, and adds an optional image-size syntax.**
> **Images without a size render exactly as before, so upgrading needs no migration.**

* **Fixed `$` inside code blocks being parsed as formulas** — the original skip check could never
  succeed. It now uses a tag allowlist plus nesting depth. This was one of the most frequently reported
  issues upstream.
* **Fixed block formulas rendering twice** — two filters each handled part of the job and disagreed on
  the marker. Now a single pass.
* **Fixed coincidentally paired `$` in body text being rendered as formulas**, e.g.
  `function update( $a, $b )`. Two guards were added, and they can be disabled with a filter.
* **Fixed `IXR_Message not found` on xmlrpc requests** (adopts upstream PR #546).
* **New: optional image-size syntax** — `![alt](img =600)` / `=600x400` / `=x400`. Renders at the
  requested size on wide screens and scales proportionally, without distortion, on narrow ones.

### Version 10.3.0

> **Where this maintained fork begins, after the original author stopped maintaining the project.**
> The plugin was **removed from WordPress.org on 2025-04-09 over a security issue**
> (**CVE-2025-31035**, affecting 10.2.1 and earlier, with no upstream fix).
> **No functional or data-structure changes, so upgrading needs no migration.**

* **Fixed a stored XSS in formula rendering**: posting a specific snippet in a post **or a comment**
  executed script. Because the comment path was affected too, the bar to exploit it was lower than for
  the publicly disclosed issues of the same batch.
* **Fixed unauthenticated arbitrary file write** in the image paste endpoint: added permission checks,
  real image type detection by content, and a payload size limit.
* **Fixed the image host proxy being usable as an open proxy (SSRF)**: the upstream is now a strict
  allowlist and only the necessary header is forwarded.
* **Fixed a batch of admin-side issues**: authorization flaws, option saving with no sanitization, and a
  slow admin (blind SSRF).
* Fixed "a specific cookie causes a white screen", a syntax error on uninstall, and a logger that threw
  on every call.
* **No longer loads editor scripts and styles from a third-party CDN by default.**
* **Works with WordPress 7.1 and PHP 7.4 – 8.4**, including a fatal "the plugin calls a function that
  does not exist yet, taking down both the admin and the front end" issue.
* **Replaced build tools that no longer work** (`node-sass` → `dart-sass`, and others), so the project
  builds again, and added automated packaging and release through GitHub Actions.
* **Other latent bugs fixed**: the mind map setting was being lost, and Mermaid's defaults were mistakenly
  written into KaTeX's.

---

## 繁體中文

### Version 10.5.0

> **這次為訪客留言做了一個「簡版編輯器」，順手清掉幾處老毛病。**
> 另外：第三方圖床（sm.ms）功能整體下線，前台編輯器的配色不再跟隨後台。
> **沒有更動資料結構，升級無需遷移。**（sm.ms 的殘留設定會在升級時自動清除。）

#### 一、留言區的老問題：為什麼「預覽裡有、送出去就沒了」

WordPress 把留言當成**不可信內容**，只允許裡面出現 15 個標籤：
`a` `abbr` `acronym` `b` `blockquote` `cite` `code` `del` `em` `i` `q` `s` `span` `strike` `strong`。

圖片（`img`）、程式碼區塊（`pre`）、表格、標題、清單、分隔線……**全部會被系統刪掉**。
可是編輯器工具列偏偏提供了這些按鈕——預覽是瀏覽器畫出來的，所以看起來一切正常；
等到儲存時被系統剝掉，東西就沒了。

這不是「WordPress 的硬限制」。外掛可以透過 `wp_kses_allowed_html` 為留言多放行幾個標籤，
只是以前一直沒做。

#### 二、新增：訪客留言簡版編輯器（預設關閉）

* 開關在：**設定 → WP Editor.md → Basic Settings → 給前台留言使用簡版編輯器**。
* 開啟後，留言框只留 8 個按鈕：**粗體、斜體、刪除線、引用、行內程式碼、連結、圖片、純預覽**。
  收起來的（標題、清單、分隔線、表格、程式碼區塊、雙欄預覽、全螢幕、清除、關於），
  不是系統會刪掉，就是在手機上只會佔位子。
* **預設單欄**，輸入框高 200px，字級 16px（低於 16px 時，iPhone 上點一下輸入框會整頁放大）。
* **圖片對話框裡移除了「圖片連結」欄位**：既然留言圖片一律不能點，填了也是白填。
* **窄螢幕下工具列的提示氣泡不再撐破頁面**（以前手機上整頁可以被橫向拖走）。
* **真正生效的限制在伺服端**，不是靠藏按鈕。想讓某個站台強制開關，
  用 `editormd_simple_comment_enabled` 過濾器。

開啟後，留言會有這些變化：

* **連結不能點**：`[文字](http://x.com)` 會顯示成「文字：`http://x.com`」。
  這是為了防止訪客之間互相釣魚，與 SEO 無關（WordPress 本來就為留言連結加上 `rel="nofollow ugc"`）。
  頁內錨點（`#` 開頭）保留原樣，`mailto:` 顯示為純文字。
* **圖片不能點**：留言圖片不再被連結包住；並強制加上「延遲載入」與「不送出來源頁網址」兩個屬性，
  因為訪客貼的圖片網址可能是任意第三方網站。
* **公式、任務清單、程式碼區塊、emoji 短碼一併關閉**——預覽與伺服端同時關，避免兩邊顯示不一致。
* **留言的圖片貼上功能關閉**：訪客沒有上傳權限，以前貼圖只會得到一個「上傳失敗」。

#### 三、除錯資訊面板新增「留言語法」兩塊

在**進階設定 → 除錯資訊**裡，現在能看到留言區**真正生效**的標籤清單，
每一行都標示是「WordPress 原生」還是「本外掛額外放行」；以及本外掛對留言做了哪些調整。

#### 四、順手修好：升級器漏了兩個分支，站台每次請求都重跑一次初始化

升級器漏寫了 10.4.3 與 10.4.5 兩個分支，停在這些版本的站台比對不到升級路徑，
版本號永遠不會前進——於是**每次存取都要重跑一次啟用流程**。現在補齊了。

#### 五、修好：後台每個頁面都在跳「檢驗到外掛資源包已過時」

升級後，寫文章、審留言、看儀表板……後台**每個頁面**都有這麼一條紅字。
兩個原因：一是資源包裡的版本號沒有跟著一起升；二是**提示掛到了錯誤的鉤子上**——
本來只該在設定頁顯示的一行狀態，被放大成全後台通告。
現在只在設定頁顯示一行狀態，並直接寫出「資源包版本 X / 外掛版本 Y」，一眼看得出哪裡對不上。

#### 六、新增：前台編輯器配色是否跟隨後台（預設關閉）

以前後台把編輯器設成深色，訪客在淺色主題的站台上看到的留言框也是深色。
現在預設**不跟隨**：前台使用 Editor.md 自帶的淺色樣式，跟著你的主題走；後台該什麼樣還什麼樣。
想自訂夜間樣式，README 裡列出了可以掛鉤子的選擇器。

#### 七、修好：官方區塊佈景主題下，留言框下方多出一塊輸入框

在 Twenty Twenty-Four / Twenty Five 這類官方區塊佈景主題下，留言框下面會露出一塊原生輸入框，
裡面是一堆 HTML，看起來像「預覽變成了原始碼」。
原因是佈景主題給留言框寫的樣式權重更高，蓋過了外掛「把這塊中間元素藏起來」的規則。
現在外掛不再跟佈景主題比權重，直接釘死。

#### 八、移除：第三方圖床（sm.ms）上傳功能

那家免費圖床已經全面轉向收費，繼續留著只是徒增維護面。
本次一併刪除：上傳分流、存取權杖與後台代理介面、配套的後台管理頁，以及只為它引入的設定項。
**圖片貼上一律存到本站媒體庫**，升級時會自動清除殘留的圖床設定。

#### 九、設定頁與文案調整

* 「支援前端留言」後面那句括號說明，移到選項下方單獨一行，與其他選項排版一致。
* PC 上把設定項標籤欄加寬，中文標籤不再折成兩行。
* 「進階設定 → 捐贈」末尾加了一段說明：本修改版只做安全強化與相容性調整，不單獨接受捐贈。

#### 十、發佈流程調整

GitHub Release 的「本版變更」改為**直接從本檔案對應版本段落產生**，不再只給一個連結；
「維護分支累計改動」不再逐條列出，壓縮成一句話。這樣 Release 頁面與本檔案只有一個事實來源。

### Version 10.4.5

> **這次修的是「預覽裡是亂碼、發佈後卻正常」。沒有更動資料結構，升級無需遷移。**

* **預覽不認識外掛的圖片尺寸寫法。** `![說明](圖片 =600)` 這類寫法只做在伺服端（儲存那一刻才生效），
  預覽用的是 Editor.md 自帶的解析器，完全看不懂，會把整行當普通文字原樣吐出來。
  現在預覽也認識這套寫法，顯示結果與伺服端逐項對齊。
* **預覽把普通文字當成公式。** 例如「價格從 $100 漲到 $200」、`function update( $a, $b )`、
  行內程式碼裡的 `$`，都會被算成公式——而伺服端是有防護的，這又是一處兩邊不一致。
  現在預覽與伺服端用同一套判斷規則，同一篇文章兩邊的公式數量完全一致。
* 順帶修了測試腳本自己的 bug（它把內容裡本該保留的反斜線吃掉了，導致用來測試的文章本身就是壞的），
  並補上一個新回歸：把「編輯器預覽」與「前台顯示」的 DOM 抓下來逐項比對。
  以前只測「儲存後的結果」，**完全沒測過編輯器預覽**。

### Version 10.4.4

> **留言裡的 Markdown 現在真的會被轉換了；頁面上也不再同時載入兩套程式碼高亮函式庫。**

* **訪客留言的 Markdown 以前根本沒生效。** 訪客寫的 `**粗體**`、`[連結](...)` 會**原樣存進資料庫**，
  前台看到的還是這些原始碼。編輯器裡的預覽是瀏覽器畫的，所以寫的時候看起來一切正常。
  根因是這個開關藏在**「設定 → 討論 → Markdown」**裡，預設關閉、入口很深，
  還與外掛自己的「支援前端留言 / 支援後台回覆」沒有任何連動。
  現在只要外掛開著留言編輯器，留言的 Markdown 轉換就一併啟用，那個容易誤導人的勾選框也隱藏了。
* 修了 `html_decode` 關閉時的一處解析問題：行首的 `>` 與連結標題裡的引號被錯誤轉義，
  導致**引用區塊渲染不出來**、**帶標題的連結解析失敗**。
* **頁面上曾同時載入兩套 Prism。** 一套是 Editor.md 自帶的（286KB、132 種語言），
  與外掛提供的那套互相覆蓋，結果程式碼區塊沒有複製按鈕、沒有語言標籤。
  現在只保留外掛這一套，同一個頁面上 Prism 從 **302.5KB 降到 24.2KB**。

### Version 10.4.3

> **這次集中修前端：程式碼高亮的載入順序、工具列圖示消失、後台回覆框一片空白、主控台每秒洗頻。**

* 修 `Prism is not defined`：設定語言包路徑的腳本跑在 Prism 前面，現在改為掛在 Prism 之後。
* 修 `https://站台/components/prism-*.min.js` 這類 404。
* 修「佈景主題引入 Bootstrap 之後，工具列只剩零星幾個圖示」：
  按鈕的類名與 Bootstrap 的全域樣式撞名，導致按鈕全部透明堆疊。
* 修後台回覆框**右半邊永遠是一片空白**：現在跟著預覽開關走。
* 修主控台**每秒刷一次**的 `wp is not defined`。

### Version 10.4.2

> **依一份程式碼審查報告，做了一輪小而集中的安全強化。沒有更動資料結構，升級無需遷移。**

* **升級兩個有公開漏洞的元件**：Mermaid 8.4.8 → **10.9.8**、KaTeX 0.11.1 → **0.19.0**。
  舊分支沒有可用修正版，且惡意圖表可以把安全等級降下來再執行注入內容。
* **順帶修好：Mermaid 圖表以前在正文裡根本渲染不出來**（輸出的內聯腳本必然語法錯誤）。
  現在改為資料驅動渲染並相容舊格式，**歷史文章無需重新儲存**。
* **圖片貼上介面補上資源用量限制**。以前只限制請求大小，
  但一張幾 MB 的高壓縮 PNG 解開後能吃掉數百 MB 記憶體。現在在解碼前就攔下來。
* **sm.ms 權杖不再下發到瀏覽器**，改由伺服端注入；代理收窄為固定操作白名單。
* **後台 AJAX 授權收緊**：移除「驗證失敗就看網域」的兜底，並移除未登入入口。
* 前端依賴 axios 0.19.2 → **1.20.0**。
* 升級/遷移**不再要求使用者登入**（以前只有匿名訪客造訪時，遷移會一直不執行）。
* CI 新增安全檢查，避免已修好的問題被改回去。

### Version 10.4.1

> **修 10.3.0 / 10.4.0 的安裝包在後台不顯示外掛資訊。沒有邏輯改動，升級無需遷移。**

* 安裝包裡，外掛主檔頭部的 `Version`、`Author`、所需的 WordPress / PHP 版本等資訊**遺失了**，
  導致後台「上傳外掛」介面看不到版本與作者，更新檢查也讀不到版本號。
* 成因：這些行的寫法與區塊註解續行**一模一樣**，被清理腳本當成註解刪掉了。
  語法檢查與程式碼比對都發現不了（註解本來就被排除在比對之外）。
  因此這次加了**專用的外掛頭檢查腳本**與 **CI 閘門**：缺欄位、或版本號對不上，直接建置失敗。

### Version 10.4.0

> **修公式解析與 xmlrpc 的老問題，新增一個可選的圖片尺寸語法。**
> **不寫尺寸的圖片，顯示結果與以前完全一樣，升級無需遷移。**

* **修「程式碼區塊裡的 `$` 被當成公式渲染」**：原本的跳過判斷永遠不成立，
  現在改為標籤白名單加上巢狀深度判斷。這是上游被反覆回報的一類問題。
* **修「區塊公式渲染兩次」**：兩個過濾器各管各的，判斷條件對不上。現在合併成一次處理。
* **修「正文裡湊巧成對的 `$` 被當成公式」**：例如 `function update( $a, $b )`。
  行內公式加了兩道判斷，也可以用過濾器關閉。
* **修 xmlrpc 下擲出 `IXR_Message not found`**（採用上游 PR #546 的修法）。
* **新增：圖片尺寸語法（可選）**：`![說明](圖片 =600)` / `=600x400` / `=x400`。
  寬螢幕依設定尺寸顯示，窄螢幕等比縮放、不變形。

### Version 10.3.0

> **原作者停止維護之後，本維護版從這裡開始。核心是把安全問題修掉，並相容新版 WordPress。**
> 本外掛已於 **2025-04-09 因安全問題被 WordPress.org 下架**
> （**CVE-2025-31035**，影響 10.2.1 及更早版本，上游沒有修正版）。
> **沒有更動功能設計與資料結構，升級無需遷移。**

* **修公式渲染的儲存型 XSS**：在文章**或留言**裡發一段特定內容就能執行腳本。
  因為留言路徑同樣受影響，利用門檻比公開揭露的同批問題更低。
* **修圖片貼上介面的未授權任意檔案寫入**：補上權限檢查、依檔案內容判斷是否為真圖片、限制大小。
* **修圖床代理被當成跳板（SSRF / 開放代理）**：上游位址改為固定白名單，只轉送必要的標頭。
* **修後台頁面的越權、設定項寫入未淨化、後台卡頓（盲 SSRF）等一批問題**。
* 修「帶某個 Cookie 就直接白畫面」「移除外掛時報語法錯誤」「日誌功能一呼叫就擲出例外」。
* **預設不再從第三方 CDN 載入編輯器腳本與樣式**，改由外掛本地提供。
* **相容 WordPress 7.1 與 PHP 7.4 ~ 8.4**，修掉「外掛載入時呼叫了還不存在的函式，
  導致前台與後台一起白畫面」這類致命問題。
* **汰換已經不能用的建置工具**（`node-sass` → `dart-sass` 等），專案重新可以建置，
  並加上 GitHub Actions 自動打包發佈。
* **順手修的老毛病**：心智圖設定項遺失、Mermaid 的預設值被誤寫進 KaTeX。

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
