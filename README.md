# WP Editor.md

> ## ⚠️ 这是修改版（Modified Version），不是上游原版
>
> 本仓库是 [LuRenJiasWorld/WP-Editor.md](https://github.com/LuRenJiasWorld/WP-Editor.md) 的**维护分支（fork）**。
>
> | | |
> | --- | --- |
> | 上游项目 | [LuRenJiasWorld/WP-Editor.md](https://github.com/LuRenJiasWorld/WP-Editor.md) |
> | 上游最后版本 | **10.2.1**（本分支的起点 commit `d73d725`） |
> | 上游状态 | 长期停止更新；已于 **2025-04-09 被 WordPress.org 以安全问题下架**（reason: `security-issue`） |
> | 关联漏洞 | **CVE-2025-31035**（Stored XSS，影响 `<= 10.2.1`），上游**无修复版本** |
> | 本分支 | [tjsky/WP-Editor.md](https://github.com/tjsky/WP-Editor.md) |
> | 修改者 | tjsky |
> | 修改日期 | 起始 **2026-10-02**；后续变更见 [CHANGELOG](https://github.com/tjsky/WP-Editor.md/blob/master/CHANGELOG.md) |
> | 修改性质 | **仅做安全加固与新版兼容性适配，未做功能重构** |
>
> 依据本项目的授权协议 **GNU General Public License v3**（或更新版本）第 5 条要求，
> 修改后的版本必须带有**显著的修改声明与日期**，故在此说明。逐项差异见
> [与上游的差异](#与上游的差异修改清单)。

[![GitHub issues](https://img.shields.io/github/issues/tjsky/WP-Editor.md.svg)](https://github.com/tjsky/WP-Editor.md/issues)
[![GitHub stars](https://img.shields.io/github/stars/tjsky/WP-Editor.md.svg)](https://github.com/tjsky/WP-Editor.md/stargazers)
[![GitHub releases](https://img.shields.io/github/v/release/tjsky/WP-Editor.md)](https://github.com/tjsky/WP-Editor.md/releases)
[![GitHub license](https://img.shields.io/github/license/tjsky/WP-Editor.md.svg)](https://github.com/tjsky/WP-Editor.md/blob/master/LICENSE)
[![Requires PHP](https://img.shields.io/badge/PHP-%E2%89%A5%207.4-blue)](https://www.php.net/supported-versions.php)
[![Tested up to](https://img.shields.io/badge/WordPress-7.1-blue)](https://wordpress.org/download/)

### 说明 Description

WP Editor.md 是一个漂亮又实用的在线 Markdown 文档编辑器。

WP Editor.md is a beautiful and practical Markdown document editor.

基于 [Editor.md](https://github.com/pandao/editor.md) 构建对 WordPress 平台的支持。

Build support for the WordPress on [Editor.md](https://github.com/pandao/editor.md).

使用 WordPress [Jetpack](http://jetpack.me) 的 Markdown 模块来解析和保存内容（该模块已内置于本插件，**无需安装 Jetpack**）。

The plugin uses the Markdown module from WordPress [Jetpack](http://jetpack.me) for parsing and saving content (bundled in this plugin, **Jetpack is not required**).

> 上游版本可从 WordPress 插件库下载，但该插件已于 2025-04-09 下架，官方目录已无可用安装包：
> ~~https://wordpress.org/plugins/wp-editormd/~~（已关闭）

---

## 与上游的差异（修改清单）

### 1. 安全修复 —— 本次维护的主要原因

| # | 问题 | 位置 | 修复方式 |
| --- | --- | --- | --- |
| 1 | **存储型 XSS（严重）**：公式内容中的 HTML 实体会被解码回真实字符后**未转义**直接输出，`the_content` / `comment_text` 均晚于保存期 kses，因此 kses 拦不住。在文章或**评论**中投递 `$ &lt; img src=x onerror=... &gt; $` 即可执行脚本 | `src/App/KaTeX.php` | 三处输出补 `esc_html()`；前端错误提示由 `.html()` 改为 `.text()`。保留实体解码表以免影响公式渲染 |
| 2 | **无鉴权任意文件写入（高）**：图片粘贴接口没有能力校验、没有 nonce，base64 内容直接写盘 | `src/App/ImagePaste.php` | 补 `current_user_can('upload_files')` 与 nonce 校验；按文件内容判定真实图片类型；限制体积；改用 WordPress 官方 API 落盘并在失败时清理；图床上传开启 TLS 校验 |
| 3 | **SSRF / 开放代理（高）**：图床代理接受请求体中的任意 URL 与任意请求头，且显式关闭了证书校验 | `src/Pages/page/sm-ms-management/` | 上游地址改为固定白名单（仅 `https://smms.app/api/v2/*`）；仅透传 `Authorization` 头；开启证书校验；补超时与响应体积上限；补同源校验 |
| 4 | **授权缺陷（高）**：能力校验把「角色名」当「能力名」；另有一处恒真的授权分支；`page` 参数直接进文件路径 | `src/Pages/Pages.php` | 改为 `manage_options`；修掉恒真分支；`page`/`entry` 改为显式白名单分发；`$_GET` 补 `isset` + `sanitize_key`；消除 ReDoS 正则 |
| 5 | **选项写入净化完全失效（高）**：第三方设置库只对显式声明 `sanitize_callback` 的字段生效，本插件一个都没声明，等于所有选项未经净化即入库 | `src/Utils/Settings.php` | 改为挂 `pre_update_option_*` 按字段类型统一兜底，覆盖设置页保存、`Config::update_option()`、升级器三条写入路径 |
| 6 | **盲 SSRF + 后台卡顿（高）**：设置页初始化时同步拉取远端 `version.json`，每个后台请求与 AJAX 都可能阻塞数秒 | `src/Utils/Settings.php` | 改为 `wp_remote_get` + scheme/host 白名单 + transient 缓存，并移出后台同步路径 |
| 7 | **任意 Cookie 触发致命错误（中）**：`wp-editormd-dev-logmode` Cookie 的处理代码位于自动加载器注册**之前**，任何携带该 Cookie 的访客都会白屏 | `wp-editormd.php` | 移入 `plugins_loaded` 钩子，补充净化、管理员限定与异常兜底 |
| 8 | **卸载时语法级致命错误（中）** | `uninstall.php` | 删除文件顶层的非法 `static`（PHP 只允许在函数内使用） |
| 9 | **日志组件空实现（中）**：`save_log()` 是空方法，任何日志调用都会抛异常 | `src/Utils/Logger.php` | 补齐实现，写入 PHP error_log |
| 10 | **输出未转义与令牌泄漏（中）** | `src/Utils/Debugger.php`、`settings.js.php` | 全部值 `esc_html()`；令牌打码；令牌由 GET 改为服务端注入 |
| 11 | **默认从第三方 CDN 加载资源（供应链风险）** | 多处 | 默认改由插件本地提供编辑器脚本与样式 |

> 关于漏洞 1：它与公开披露的 **CVE-2025-31035**（Stored XSS，影响 `<= 10.2.1`）属于同类问题。
> 本分支在真实 WordPress 7.1.2 环境做过对照实验 —— 上游原始代码确实会渲染出可执行的
> `<img src=x onerror=alert(1)>`（文章正文、评论、多行公式、代码块公式四条路径均命中），
> 修复后四条路径全部阻断，而正常公式（`E=mc^2`、`a^2+b^2=c^2`、`\frac{1}{2}`）渲染结果逐字节一致。

### 2. WordPress / PHP 兼容性

* `Tested up to` 更新为 **WordPress 7.1**；`Requires PHP` 明确为 **7.4**，实测通过 7.4 ~ 8.4
* 修复 **插件主文件在插件加载期调用用户上下文函数导致整站白屏** —— WordPress 在 `wp-settings.php`
  中先加载活动插件、之后才加载 `pluggable.php`，因此插件文件顶层调用 `current_user_can()` 等函数会
  抛出 `Call to undefined function wp_get_current_user()`，前台与后台同时白屏
* 修复 PHP 8.1+ `htmlspecialchars()` 默认 flags 变化会改变存量内容渲染的问题（显式传入 flags 冻结行为）
* 修复 `$GLOBALS['pagenow']` 未定义提示、Mermaid 配置为空时生成非法 JS、思维导图地址为空时误把当前页当脚本加载等问题
* 版本号比较改用 `version_compare`，不再用字符串比较
* 移除会 `wp_deregister_script('jquery')` 并改用 jQuery 1.12.4 的分支，统一使用 WordPress 自带 jQuery
* 多站点（Network）激活与卸载改为逐站点处理

### 3. 构建链（原项目「无法构建」的根因）

* `node-sass`（仅支持 Node ≤ 18，且需要本地编译）→ **`dart-sass`**，项目因此可在 Node 22 下构建
* 停止维护的 `webpack-parallel-uglify-plugin` → **`terser-webpack-plugin`**
* Vue 子项目（sm.ms 图片管理页）修复 `tsconfig`、`peerDependencies`、以及 `publicPath` 硬编码插件目录名的问题
  （改为构建期占位符 + 运行期替换，重命名插件目录不再导致 404）
* 清理 `assets/FrontStyle/FrontStyle.scss` 中一行残缺语句（libsass 静默忽略，dart-sass 会直接构建失败）
* 新增 **GitHub Actions 发布流程**：打 tag 即从源码构建并自动生成可安装 zip

### 4. 顺带修复的历史遗留缺陷（非安全）

* `editor_mindmap` 选项被 `editor_style` 数组整体覆盖，导致思维导图设置项丢失、功能失效
* Mermaid 的默认配置被错误地写入了 KaTeX 的默认值

### 本次维护「没有做」的事

为避免影响面扩大，以下**有意未改**：功能设计与交互逻辑、编辑器前端行为、数据存储结构、
既有选项的命名与取值。升级到本版本**不需要迁移数据**，设置项保持原样即可。

---

## 安装 Installation

**推荐从 [Releases](https://github.com/tjsky/WP-Editor.md/releases) 下载 zip 安装**：

1. 下载 `wp-editormd-x.y.z.zip`
2. WordPress 后台 → 插件 → 安装插件 → 上传插件 → 选择该 zip
3. 启用插件

> ⚠️ **不要**使用 GitHub 自动生成的 `Source code (zip)` 压缩包。
> 本仓库的 `.gitignore` 忽略了 `vendor/`、前端编译产物与语言包 `.mo` 文件，
> 源码包缺少这些**运行期必需**文件，安装后会因找不到 `vendor/autoload.php` 而致命报错。
> Release 里的 zip 由 CI 从源码完整构建，已包含全部所需文件。

---

## 从源码构建 Building

若要从源码自行构建（CI 走的就是这套流程）：

```bash
# 1. PHP 依赖（生成 vendor/）
composer install --no-dev --prefer-dist --optimize-autoloader

# 2. 前端产物
npm ci
npm run build-prod

# 3. sm.ms 图片管理页（Vue 子项目）
cd src/Pages/page/sm-ms-management
npm ci
npm run build
cd -

# 4. 语言包
for po in languages/*.po; do msgfmt -o "${po%.po}.mo" "$po"; done

# 5. 打包成可安装 zip
python3 .github/scripts/build_package.py . dist/wp-editormd-10.3.0.zip 10.3.0
```

环境要求：Node.js ≥ 18（推荐 22）、PHP ≥ 7.4、Composer、gettext（`msgfmt`）、Python 3。

---

## 特征 Feature

- [x] 支持实时预览 / 代码插入 / 代码折叠 / 列表插入 / 搜索替换 / 语法高亮等功能
- [x] 支持 [Emoji 表情](http://www.emoji-cheat-sheet.com/)
- [x] 支持 WordPress 的多媒体插入
- [x] 支持 Toc 文章目录显示
- [x] 支持 GFM Task lists
- [x] 支持 [KaTeX 科学公式](https://khan.github.io/KaTeX/)
- [x] 支持 [Mermaid](https://mermaidjs.github.io/)
- [x] 支持图像粘贴

---

- [x] Real-time Preview, Preformatted text/Code blocks/Tables insert, Search replace, Code syntax highlighting
- [x] Support [Emoji](http://www.emoji-cheat-sheet.com/)
- [x] Support WordPress multimedia insertion
- [x] Support Toc
- [x] Support GFM Task lists
- [x] Support [KaTeX](https://khan.github.io/KaTeX/)
- [x] Support [Mermaid](https://mermaidjs.github.io/)
- [x] Support Image Paste

### 使用说明 ReadMe

请参考上游 Wiki（部分内容可能已过期）：
<https://github.com/LuRenJiasWorld/WP-Editor.md/wiki>

### 更新日志 ChangeLog

请见 [CHANGELOG.md](https://github.com/tjsky/WP-Editor.md/blob/master/CHANGELOG.md)

---

## 授权协议 License

![GPLv3](https://www.gnu.org/graphics/gplv3-127x51.png)

本分支沿用上游的授权协议：**GNU General Public License Version 3 or later**。

WP-Editor.MD is licensed under [GNU General Public License](https://www.gnu.org/licenses/gpl.html) Version 3 or later.

```
WP-Editor.MD is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

WP-Editor.MD is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with WP-Editor.MD.  If not, see <http://www.gnu.org/licenses/>.
```

**版权与归属**

* 原始作品版权归 [LuRenJiasWorld](https://github.com/LuRenJiasWorld) 及 [WP-Editor.md 贡献者](https://github.com/LuRenJiasWorld/WP-Editor.md/graphs/contributors) 所有。
* 本分支的修改部分版权归 [tjsky](https://github.com/tjsky) 所有，同样以 GPL-3.0-or-later 授权。
* 本项目依赖 [Editor.md](https://github.com/pandao/editor.md)、[KaTeX](https://katex.org/)、
  [Mermaid](https://mermaid.js.org/)、[Prism.js](https://prismjs.com/)、[CodeMirror](https://codemirror.net/)
  等第三方开源组件，其版权与许可见各组件自身的 LICENSE。
* 依据 GPL-3.0 第 5 条，本修改版保留原有版权声明，并随源码一并提供完整的 `LICENSE` 全文。
