# WP Editor.md（维护版）

**给 WordPress 用的 Markdown 编辑器。** 你在后台用 Markdown 写文章，它负责把内容排出来——公式、流程图、代码高亮、图片、目录，都是现成的。

> **English:** A Markdown editor for WordPress. This repository is a maintained fork of a plugin that is no longer developed — the original was removed from WordPress.org over a security issue. We fix the security problems and keep it working on current WordPress. Full notes are in Chinese below.

> ## ⚠️ 这是修改版，不是原版
>
> 本仓库是 [LuRenJiasWorld/WP-Editor.md](https://github.com/LuRenJiasWorld/WP-Editor.md) 的**维护分支**。
> 原版早已停止更新，并已于 **2025-04-09 被 WordPress 官方应用商店下架**，原因是安全问题
> （**CVE-2025-31035**，影响 10.2.1 及更早版本），原版没有推出修复版本。
>
> 本维护分支做的事情：**修好安全问题，让它能在新版 WordPress 上正常使用。**
> 功能设计与数据格式没有改动，**升级不需要迁移数据**。
>
> |  |  |
> | --- | --- |
> | 原版项目 | [LuRenJiasWorld/WP-Editor.md](https://github.com/LuRenJiasWorld/WP-Editor.md)（最后版本 10.2.1） |
> | 本维护版 | [tjsky/WP-Editor.md](https://github.com/tjsky/WP-Editor.md) |
> | 维护者 | [tjsky](https://github.com/tjsky) |
> | 修改起始 | 2026-10-02 |
> | 当前版本 | 10.5.0（2026-10-09） |
>
> 按 GPL-3.0 第 5 条的要求，此处保留修改声明与日期。逐项明细见
> [CHANGELOG.md](https://github.com/tjsky/WP-Editor.md/blob/master/CHANGELOG.md)。

[![GitHub issues](https://img.shields.io/github/issues/tjsky/WP-Editor.md.svg)](https://github.com/tjsky/WP-Editor.md/issues)
[![GitHub stars](https://img.shields.io/github/stars/tjsky/WP-Editor.md.svg)](https://github.com/tjsky/WP-Editor.md/stargazers)
[![GitHub releases](https://img.shields.io/github/v/release/tjsky/WP-Editor.md)](https://github.com/tjsky/WP-Editor.md/releases)
[![GitHub license](https://img.shields.io/github/license/tjsky/WP-Editor.md.svg)](https://github.com/tjsky/WP-Editor.md/blob/master/LICENSE)
[![Requires PHP](https://img.shields.io/badge/PHP-%E2%89%A5%207.4-blue)](https://www.php.net/supported-versions.php)
[![Tested up to](https://img.shields.io/badge/WordPress-7.1-blue)](https://wordpress.org/download/)

---

## 安装

1. 到 [Releases](https://github.com/tjsky/WP-Editor.md/releases) 页面下载 `wp-editormd-x.y.z.zip`
2. WordPress 后台 → **插件 → 安装插件 → 上传插件** → 选择刚下载的 zip
3. 点「启用」

> ⚠️ **不要**下载 GitHub 页面上的 `Source code (zip)`。
> 那种包是源码，缺少运行必需的 `vendor/`、前端编译产物和语言包，安装后会直接报错。
> Release 里的 zip 由构建流程产出，包含全部所需文件。

---

## 功能

| 功能 | 说明 |
| --- | --- |
| **边写边看** | 左边写 Markdown，右边实时显示效果 |
| **代码** | 语法高亮、行号、语言标签、一键复制 |
| **公式** | 用 KaTeX 渲染，写成 `$…$` 即可 |
| **图表** | 流程图、时序图、甘特图等，用 Mermaid 绘制 |
| **表格 / 任务清单 / Emoji / 目录** | 均支持 |
| **图片** | 从 WordPress 媒体库插入，或直接粘贴上传 |
| **图片尺寸** | 写成 `![说明](图片地址 =600)` 指定显示宽度 |
| **评论也能写 Markdown** | 可选功能，另可给访客启用「简版编辑器」 |

更详细的用法见上游 Wiki（部分内容可能已过期）：
<https://github.com/LuRenJiasWorld/WP-Editor.md/wiki>

**图片尺寸怎么写**（10.4.0 起，可选）

> ⚠️ **图片地址与等号之间必须有一个空格。**
> 写成 `图片地址=600`（没有空格）不会被识别，`=600` 会被当成图片地址的一部分。

    ![说明](图片地址 =600)        宽度 600px，高度按原图比例
    ![说明](图片地址 =600x400)    宽 600px、高 400px
    ![说明](图片地址 =x400)       高度 400px

不写尺寸即按原图显示；窄屏下会自动等比缩小，不会变形。

---

## 最近更新

**10.5.0（2026-10-09）**

* 新增「访客评论简版编辑器」（默认关闭）：手机单栏、只保留评论区真正能用的语法，
  链接与图片不可点击。
* 移除第三方图床（sm.ms）上传功能（该服务已转为收费）。
* 修复后台「回复评论」框：打开后无法直接输入，工具栏压住正文第一行（看起来像行号异常、第一个字被挡）。
* 修复后台每个页面都弹「检验到插件资源包已过时」。
* 修复官方块主题下评论框下方多出一块输入框。
* 前台编辑器配色可不再跟随后台设置。

完整记录见 [CHANGELOG.md](https://github.com/tjsky/WP-Editor.md/blob/master/CHANGELOG.md)。

---

## 和原版有什么不同

**只做必要的安全加固与兼容适配，不改功能设计，也不改数据格式。**

* **安全修复**（本次维护的主要原因）—— 修复公式渲染的**存储型 XSS**（与公开披露的
  **CVE-2025-31035** 同类），以及图片粘贴接口的越权上传、图床代理被当作跳板、
  后台页面越权、设置项保存未校验、携带特定 Cookie 导致白屏等一批问题；
  并升级了两个存在公开安全问题的组件（**Mermaid → 10.9.8、KaTeX → 0.19.0**）。
* **兼容新版** —— 支持 **WordPress 7.1** 与 **PHP 7.4 – 8.4**，包括修掉
  「插件加载时调用了尚不存在的函数，导致前台后台同时白屏」这类致命问题。
* **内容渲染修复** —— 公式误判、代码块里的 `$` 被当成公式、块级公式重复渲染；
  编辑器预览与发布结果不一致；评论里的 Markdown 不生效等。
* **新增两个可选功能** —— 图片尺寸语法（10.4.0）、访客评论简版编辑器（10.5.0）。
* **默认不再从第三方 CDN 加载资源**，改由插件本地提供。

**改动的边界**

* 不重构编辑器、不改数据存储结构、不改已有设置项的命名与取值。
* 涉及功能时只做两类改动：**删掉已经失效的功能**（例如第三方图床上传 —— 该服务已转为收费），
  以及**修好「本来就不可能生效」的功能**（例如评论区里会被 WordPress 剥掉的那些语法）。
* 新增的只有两个标注为「可选」的小功能（图片尺寸语法、访客评论简版编辑器），
  不启用时行为与升级前完全一致。

---

## 常见问题

**Q：后台一直提示「检验到插件资源包已过时」，怎么办？**
A：10.5.0 已修复。该提示原本只应出现在插件设置页，旧版本写错了输出位置，变成了全后台通告。
升级到 10.5.0 及以上即可，不需要手动修改任何设置。

**Q：为什么访客评论里的图片点不开、链接也不能点？**
A：这是有意设计。评论属于不可信内容，此举用于防止访客之间互相钓鱼。
图片照常显示，只是不可点击；链接会显示为「链接文字：`网址`」。

**Q：评论里为什么不能贴代码块、表格、标题？**
A：WordPress 对评论内容只放行 15 种标签，代码块、表格、标题、列表本就会被系统剥掉——
不是插件不提供，而是贴了也留不住。开启「简版编辑器」后，工具栏会直接收起用不了的按钮。
当前生效的标签清单可在**高级设置 → 调试信息**中查看。

**Q：升级会不会弄丢文章或设置？**
A：不会。本维护版从未改动数据结构，升级不需要迁移。

**Q：装完白屏了？**
A：几乎都是使用了 `Source code (zip)` 源码包。请改用 Release 页面里的 zip。

---

## 从源码构建

自行构建的流程（自动发布走的就是这一套）：

```bash
# 1. PHP 依赖（生成 vendor/）
composer install --no-dev --prefer-dist --optimize-autoloader

# 2. 前端产物
npm ci
npm run build-prod

# 3. 语言包
for po in languages/*.po; do msgfmt -o "${po%.po}.mo" "$po"; done

# 4. 打包成可安装的 zip
python3 .github/scripts/build_package.py . dist/wp-editormd-10.5.0.zip 10.5.0
```

环境要求：Node.js ≥ 18（推荐 22）、PHP ≥ 7.4、Composer、gettext（`msgfmt`）、Python 3。

---

## 授权协议

![GPLv3](https://www.gnu.org/graphics/gplv3-127x51.png)

本分支沿用原版的授权协议：**GNU General Public License Version 3 or later**。

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

* 原始作品版权归 [LuRenJiasWorld](https://github.com/LuRenJiasWorld) 及
  [WP-Editor.md 贡献者](https://github.com/LuRenJiasWorld/WP-Editor.md/graphs/contributors) 所有。
* 本分支的修改部分版权归 [tjsky](https://github.com/tjsky) 所有，同样以 GPL-3.0-or-later 授权。
* 本项目依赖 [Editor.md](https://github.com/pandao/editor.md)、[KaTeX](https://katex.org/)、
  [Mermaid](https://mermaid.js.org/)、[Prism.js](https://prismjs.com/)、[CodeMirror](https://codemirror.net/)
  等第三方开源组件，其版权与许可见各组件自身的 LICENSE。
* 依 GPL-3.0 第 5 条，本修改版保留原有版权声明，并随源码附上完整的 `LICENSE` 全文。
