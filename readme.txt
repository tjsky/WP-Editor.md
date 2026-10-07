=== WP Editor.md - The Perfect WordPress Markdown Editor ===
Contributors: LuRenJiasWorld, tjsky
Donate link: https://untitled.pw/
Tags: Editor, Markdown, Markdown Editor, LaTeX, KaTeX, PrismJS, Mermaid
Requires at least: 5.0
Tested up to: 7.1
Stable tag: 10.4.2
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WP Editor.md is a beautiful and practical Markdown document editor.

== Description ==

WP Editor.md is a beautiful and practical Markdown document editor.

Build support for the WordPress on Editor.md.

The plugin parses and stores Markdown with a **bundled** fork of Automattic's Jetpack Markdown
module (`src/App/WPComMarkdown.php` + `src/App/WPMarkdownParser.php`). Jetpack itself is
**not** required — the parser has been vendored into this plugin since 8.x, and Jetpack has
long since dropped its own Markdown module.

=== 10.3.0 安全加固与兼容性说明 ==

本版本由 tjsky 在原作者停止维护（最后版本 10.2.1）后继续维护，主要修复安全问题并适配
WordPress 7.1 / PHP 8.4：

* 修复 KaTeX 公式渲染链路的存储型 XSS（`src/App/KaTeX.php`）。该问题与公开披露的
  CVE-2025-31035（Stored XSS，影响 <= 10.2.1）属同类，且受影响的权限门槛更低。
* 修复图片粘贴上传接口（`src/App/ImagePaste.php`）：补齐权限与 nonce 校验、按文件内容
  校验真实图片类型、限制体积、改用 WordPress 官方 API 落盘、开启图床上传的 TLS 校验。
* 修复 sm.ms 图床代理的 SSRF / 开放代理问题，改为固定上游白名单并开启证书校验。
* 修复设置页 `editor_mindmap` 选项被 `editor_style` 数组覆盖导致思维导图失效的数据损坏缺陷。
* 修复后台每个请求都会同步发起外部 HTTP 请求（最坏阻塞 6 秒）的问题。
* 修复携带特定 Cookie 即可触发致命错误（白屏）的缺陷；修复插件卸载时的语法级致命错误。
* 默认静态资源改由插件本地提供，不再默认从第三方 CDN 加载编辑器脚本与样式。
* 移除会 `wp_deregister_script("jquery")` 并改用 jQuery 1.12.4 的分支，统一使用 WordPress 自带 jQuery。

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-editormd` directory, or install
   the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Settings -> WP Editor.md screen to configure the plugin.

== Frequently Asked Questions ==

= The network resource appears "http://cdn.staticfile.org/emoji-cheat-sheet/1.0.0" and the "https://staticfile.qnssl.com/emoji-cheat-sheet/1.0.0" connection, which What is it? =

This is where you open the Emoji option, the page needs to load some emoji picture resources if you are not sure you can turn off the Emoji option.

= CDN Accelerated Service List =

prism.js
prism.css
emojify.js
emojify.css
emoji-cheat-sheet

= Enable plugins does not work properly =

We recommend that you enable plugins in a clean environment (please disable other plugins and use default themes).

== Screenshots ==

1. Editor.md Interface - Edit Mode
2. Editor.md Interface - Page Display

== Changelog ==

= 10.4.2 =
* 安全修复：Mermaid 由 8.4.8 升级到 10.9.8（8.x / 9.x 各自存在无修复版本的 XSS / CSS 注入问题），
  并把 securityLevel 强制钉为 strict，站点配置与图表指令都无法降低安全等级
* 缺陷修复：Mermaid 图表此前在正文里无法渲染（内联脚本被内容过滤器改写引号），
  现改为数据驱动渲染并兼容旧格式，历史文章无需重新保存
* 安全修复：KaTeX 由 0.11.1 升级到 0.19.0，渲染时显式 trust:false
* 安全修复：图片粘贴新增解码后体积上限、像素预算与内存预算，全部在进入 GD 解码前拦截；
  sm.ms 上游超时由 120 秒收紧到 10 秒（连接超时 3 秒）；新增按用户的速率限制
* 安全修复：sm.ms 令牌不再下发到浏览器，改由服务端代理注入；代理改为固定操作白名单
  （仅 profile / upload_history / delete），方法与目标地址均由服务端决定
* 安全修复：后台 AJAX 不再以 Origin/Referer 主机名作为 nonce 失败的兜底授权，并移除未登录入口
* 安全修复：临时文件改用 wp_tempnam() 生成唯一名称（原先可预测且并发会互相覆盖）
* 安全修复：日志中的请求 URI 对查询参数做脱敏，不再把 nonce 写进 error_log
* 安全修复：前端依赖 axios 由 0.19.2 升级到 1.20.0（Vue 2.x 全系无可用修复，留待 Vue 3 迁移）
* 兼容性：不再默认移除前台的区块样式（wp-block-library 等），改为显式 opt-in
* 兼容性：页面渲染函数改用插件前缀命名，避免与其它代码的全局 display_page() 冲突
* 改进：插件升级/迁移不再依赖「有管理员登录」；增加并发锁与失败日志
* 构建：CI 新增安全不变量检查（依赖安全基线 + 关键防护点）、composer 校验与 composer audit

= 10.4.1 =
* 缺陷修复：插件头缺少 Version / Author / Requires at least / Requires PHP / Tested up to
  （后台「上传插件」界面不显示版本、作者与所需环境，更新检查拿不到版本号）
* 构建：注释剥离工具增加插件头字段保护，并新增插件头校验与 CI 闸门，防止复发

= 10.4.0 =
* 安全修复：KaTeX 公式解析的输出转义（存储型 XSS，CVE-2025-31035 同类问题）
* 缺陷修复：代码块 / 行内代码中的 $ 被误当公式渲染（上游长期反馈问题）
* 缺陷修复：正文里误配对的 $ 被渲染成公式（如 function update( $a, $b )）
* 缺陷修复：块级公式被内联规则二次解析导致重复渲染
* 缺陷修复：xmlrpc 请求下抛出 Class 'EditormdApp\IXR_Message' not found（采纳上游 PR #546）
* 新功能：图片尺寸语法 ![alt](img.jpg =600) / =600x400 / =x400
* 兼容性：公式解析改为「标签白名单 + 嵌套深度」跳过，代码块内的 $ 不再被处理

= 10.3.0 =
* 安全修复：KaTeX 公式渲染的存储型 XSS（CVE-2025-31035 同类问题）
* 安全修复：图片粘贴接口缺失权限/CSRF 校验、任意文件写入、无体积上限
* 安全修复：sm.ms 图床代理 SSRF / 开放代理、TLS 证书校验被关闭
* 安全修复：后台设置页与调试面板的输出未转义、sm.ms 令牌经 URL 泄漏
* 缺陷修复：editor_mindmap 选项被 editor_style 覆盖导致思维导图失效
* 缺陷修复：后台每个请求同步外联（最坏 6 秒）、登录用户每请求重跑升级器
* 缺陷修复：携带 wp-editormd-dev-logmode Cookie 触发致命错误（白屏）
* 缺陷修复：卸载插件时的文件级 static 语法错误
* 兼容性：适配 WordPress 7.1 与 PHP 8.4（动态属性、null 传参、htmlspecialchars 默认值变更等）
* 兼容性：不再默认从第三方 CDN 加载静态资源；移除 jQuery 1.12.4 分支
* 构建：node-sass 迁移至 dart-sass，移除已停止维护的 webpack-parallel-uglify-plugin

= 10.2.1 =
* 请参见 https://github.com/LuRenJiasWorld/WP-Editor.md/blob/master/CHANGELOG.md

= 10.2.0 =
* 请参见 https://github.com/LuRenJiasWorld/WP-Editor.md/blob/master/CHANGELOG.md

注意：如果使用插件请不要使用 Gutenberg 区块编辑器，会出现文章数据丢失的问题。
本版本默认继续禁用区块编辑器，如需放开可使用 `editormd_disable_block_editor` 过滤器。
