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
> | 修改性质 | **以安全加固与新版兼容性为主；另新增一项小功能（图片尺寸语法），未做功能重构** |
>
> 依据本项目的授权协议 **GNU General Public License v3**（或更新版本）第 5 条要求，
> 修改后的版本必须带有**显著的修改声明与日期**，故在此说明。修改要点见
> [与上游的差异](#与上游的差异)，逐项明细见 [CHANGELOG.md](https://github.com/tjsky/WP-Editor.md/blob/master/CHANGELOG.md)。

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

## 与上游的差异

本分支只做**必要的安全加固与兼容适配**，没有改动功能设计与数据结构，**升级不需要迁移**。
下面只列要点，逐项明细见 [CHANGELOG.md](https://github.com/tjsky/WP-Editor.md/blob/master/CHANGELOG.md)。

* **安全修复**（本次维护的主要原因）—— 修复 KaTeX 公式渲染的**存储型 XSS**（同类问题即已公开的
  **CVE-2025-31035**），以及图片粘贴接口的任意文件写入与资源耗尽、图床代理的 SSRF、
  后台管理页的授权缺陷、选项保存未净化、设置页盲 SSRF、携带特定 Cookie 即白屏等一批漏洞；
  升级了存在公开漏洞的捆绑库（**Mermaid → 10.9.8、KaTeX → 0.19.0**）、把图床令牌移出浏览器、
  默认不再从第三方 CDN 加载资源
* **兼容性** —— 适配 **WordPress 7.1** 与 **PHP 7.4 ~ 8.4**，含「插件加载期调用用户上下文函数
  导致前台后台同时白屏」这类致命问题
* **构建链** —— 替换已无法使用的 `node-sass`、`uglify` 插件，项目恢复可构建；新增 GitHub Actions 自动打包
* **顺带修复** —— 思维导图设置项丢失、Mermaid 默认值串到 KaTeX 等历史遗留缺陷
* **公式解析重写**（10.4.0）—— 修复代码块里的 `$` 被当公式、块级公式重复渲染、正文误配对的 `$` 被渲染
* **新增（可选）**（10.4.0）—— 图片尺寸语法 `![说明](图片地址 =600)`，不写尺寸时渲染结果与原来完全一致

有意**未做**的事：为避免影响面扩大，编辑器前端行为、数据存储结构、既有选项的命名与取值均保持不变。

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
python3 .github/scripts/build_package.py . dist/wp-editormd-10.4.3.zip 10.4.3
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

**图片尺寸语法（10.4.0 新增，可选）**：在图片地址后加 `=宽x高`，可只写一维：

    ![说明](图片地址 =600)        显示宽度 600px，高度按原图比例
    ![说明](图片地址 =600x400)    显示为 600×400
    ![说明](图片地址 =x400)       显示高度 400px

留空即按原尺寸显示。窄屏下会自动等比缩小，不会变形。

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
