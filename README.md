# WP Editor.md（维护版）

**给 WordPress 用的 Markdown 编辑器。** 你在后台用 Markdown 写文章，它负责把内容排得漂漂亮亮——公式、流程图、代码高亮、图片、目录，都是现成的。

> **English:** A Markdown editor for WordPress. This repository is a maintained fork of a plugin that is no longer developed — the original was removed from WordPress.org over a security issue. We fix the security problems and keep it working on current WordPress. Full notes are in Chinese below.

> ## ⚠️ 先看这里：这是修改版，不是原版
>
> 本仓库是 [LuRenJiasWorld/WP-Editor.md](https://github.com/LuRenJiasWorld/WP-Editor.md) 的**维护分支**。
> 原版早已停更，并且**已于 2025-04-09 被 WordPress 官方应用商店下架**——原因是安全问题
> （**CVE-2025-31035**，影响 10.2.1 及更早版本），而原版至今没有推出修复版本。
>
> 我们做的事很简单：**把安全问题修好，让它能在新版 WordPress 上正常跑起来。**
> 功能设计和数据格式都没动，**升级不需要迁移数据**。
>
> |  |  |
> | --- | --- |
> | 原版项目 | [LuRenJiasWorld/WP-Editor.md](https://github.com/LuRenJiasWorld/WP-Editor.md)（最后版本 10.2.1） |
> | 本维护版 | [tjsky/WP-Editor.md](https://github.com/tjsky/WP-Editor.md) |
> | 维护者 | [tjsky](https://github.com/tjsky) |
> | 修改起始 | 2026-10-02 |
> | 最近更新 | 2026-10-09（10.5.0） |
> | 改动性质 | 以**安全加固和新版兼容**为主；另外加了两个可选的实用小功能 |
>
> GPL-3.0 第 5 条要求修改版写明「改了什么、什么时候改的」，所以有了上面这段。
> 具体改动见 [和原版有什么不同](#和原版有什么不同)，逐条明细见
> [CHANGELOG.md](https://github.com/tjsky/WP-Editor.md/blob/master/CHANGELOG.md)。

[![GitHub issues](https://img.shields.io/github/issues/tjsky/WP-Editor.md.svg)](https://github.com/tjsky/WP-Editor.md/issues)
[![GitHub stars](https://img.shields.io/github/stars/tjsky/WP-Editor.md.svg)](https://github.com/tjsky/WP-Editor.md/stargazers)
[![GitHub releases](https://img.shields.io/github/v/release/tjsky/WP-Editor.md)](https://github.com/tjsky/WP-Editor.md/releases)
[![GitHub license](https://img.shields.io/github/license/tjsky/WP-Editor.md.svg)](https://github.com/tjsky/WP-Editor.md/blob/master/LICENSE)
[![Requires PHP](https://img.shields.io/badge/PHP-%E2%89%A5%207.4-blue)](https://www.php.net/supported-versions.php)
[![Tested up to](https://img.shields.io/badge/WordPress-7.1-blue)](https://wordpress.org/download/)

---

## 装它（三步）

1. 到 [Releases](https://github.com/tjsky/WP-Editor.md/releases) 页面，下载 `wp-editormd-x.y.z.zip`
2. WordPress 后台 → **插件 → 安装插件 → 上传插件** → 选中刚下载的 zip
3. 点「启用」

> ⚠️ **别下载 GitHub 页面上的 `Source code (zip)`。**
> 那种包是源码，里面缺了运行必需的 `vendor/`、编译好的前端文件和语言包，装上去会直接报错。
> Release 里的 zip 是我们构建好的，装完就能用。

---

## 它现在能做什么

| 功能 | 说明 |
| --- | --- |
| **边写边看** | 左边写 Markdown，右边实时显示效果 |
| **代码** | 语法高亮、行号、语言标签、一键复制代码 |
| **公式** | 用 KaTeX 渲染，写成 `$…$` 就行 |
| **图表** | 流程图、时序图、甘特图等，用 Mermaid 画 |
| **表格 / 任务清单 / Emoji / 目录** | 都支持 |
| **图片** | 从 WordPress 媒体库插入，或直接粘贴上传 |
| **图片尺寸** | 写成 `![说明](图片地址 =600)`，指定显示宽度 |
| **评论也能写 Markdown** | 可选功能；还能给访客换成「简版编辑器」 |

更细的用法见上游 Wiki（部分内容可能已过期）：
<https://github.com/LuRenJiasWorld/WP-Editor.md/wiki>

**图片尺寸怎么写**（10.4.0 起，可选）：

    ![说明](图片地址 =600)        宽度 600px，高度按原图比例
    ![说明](图片地址 =600x400)    宽 600px、高 400px
    ![说明](图片地址 =x400)       高度 400px

不写尺寸就按原图显示。窄屏下会自动等比缩小，不会变形。

---

## 这一版（10.5.0）改了什么

- **访客评论有了「简版编辑器」（默认关闭）**：手机上单栏、只留 8 个按钮，
  去掉那些评论里本来就用不了的功能，免得「预览里好好的、发出去就没了」。
- **链接和图片在评论里都不能点了**：防的是访客之间的钓鱼点击。
- **后台不再到处弹「检验到插件资源包已过时」**：那条提示本来只该出现在设置页。
- **前台编辑器的配色可以不再跟着后台走**：前台跟随你的主题，后台该深色还深色。
- **修好了官方块主题下评论框多出一块输入框**的问题。
- **去掉了 sm.ms 图床**：那家已经改成收费服务了。
- **修掉一个会让站点每次都重跑一遍初始化的问题**。

完整说明见 [CHANGELOG.md](https://github.com/tjsky/WP-Editor.md/blob/master/CHANGELOG.md)。

---

## 和原版有什么不同

一句话：**只做必要的安全加固和兼容适配，不动功能设计，也不动数据格式。**

* **修安全**（维护它的主要原因）——
  修掉公式渲染的**存储型 XSS**（就是被公开的那类问题，**CVE-2025-31035**），
  以及图片粘贴接口的任意文件写入、图床代理被当成跳板、后台页面的越权、设置项保存不净化、
  带某个 Cookie 就直接白屏等一批问题；同时升级了两个有公开漏洞的组件
  （**Mermaid → 10.9.8、KaTeX → 0.19.0**），默认也不再从第三方 CDN 加载资源。
* **适配新版** —— 支持 **WordPress 7.1** 和 **PHP 7.4 ~ 8.4**，包括修掉「插件加载时调用了
  还不存在的函数，导致前台后台一起白屏」这种致命问题。
* **修构建链** —— 换掉早就用不了的构建工具，项目重新能构建，并加上了自动打包发布。
* **顺手修的老毛病** —— 比如思维导图设置丢失、Mermaid 的默认值被写进 KaTeX 等历史遗留问题。
* **重写公式解析（10.4.0）** —— 修掉「代码块里的 `$` 被当成公式」「块级公式渲染两遍」
  「正文里凑巧成对的 `$` 被当成公式」。
* **新增：图片尺寸语法（10.4.0，可选）** —— 写法见上一节。不写尺寸时，效果和以前一模一样。
* **预览和发布结果对齐（10.4.5）** —— 以前预览不认识图片尺寸语法，还会把价格、函数签名里的 `$`
  当成公式；现在两边显示结果逐项一致。
* **访客评论不再「发出去就没了」（10.4.4 / 10.5.0）** —— 原因见下面的常见问题。

**明确不做的事**：不重构编辑器、不改数据存储结构、不改已有设置项的命名和取值。
一切都是为了把影响面控制住。

---

## 常见问题

**Q：后台一直在提示「检验到插件资源包已过时」，怎么办？**
A：10.5.0 已经修好。这个提示原本只该出现在插件设置页，旧版本写错了输出位置，变成了全后台通告。
升级到 10.5.0 即可，不需要手动改任何设置。

**Q：为什么访客评论里的图片点不开、链接也不能点？**
A：这是有意为之。评论属于不可信内容，防的是访客之间互相钓鱼。图片照样显示，只是不能点。
链接会显示成「链接文字：`网址`」的样子。

**Q：评论里为什么不能贴代码块、表格、标题？**
A：WordPress 对评论内容只放行 15 个标签，代码块、表格、标题、列表本来就会被系统剥掉——
不是插件不给你用，是贴了也留不住。开启「简版编辑器」后，工具栏会直接把用不了的按钮收起来。
当前生效的标签清单，可以在**高级设置 → 调试信息**里查看。

**Q：升级会不会弄丢我的文章或设置？**
A：不会。本维护版从未改动数据结构，升级不需要迁移。

**Q：装完白屏了？**
A：几乎都是用了 `Source code (zip)` 源码包。请改用 Release 页面里的 zip。

---

## 从源码构建

要自己构建的话（我们的自动发布流程走的就是这套）：

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
* 按 GPL-3.0 第 5 条，本修改版保留原有版权声明，并随源码附上完整的 `LICENSE` 全文。
