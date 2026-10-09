#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""安全不变量检查（CI 闸门）

背景：本项目的安全修复分散在多个文件里，而此前的 CI 只验证「能不能打包」，
无法回答「修好的地方有没有被改回去」。更早还发生过「插件头元数据被注释清理
脚本删掉、语法校验与 token 比对都发现不了」的事故。

因此这里做**低成本的抗回归检查**：
  1. 捆绑的第三方库不得低于安全基线（对照 10.4.2 的评估结论）；
  2. 若干个「已经修掉的高危写法」不得重新出现；
  3. 若干个「必须存在的防护」必须存在。

这些检查是文本级的，只能覆盖「明确的回归」，不能替代单元测试与浏览器回归
（那部分仍在计划中，见 CHANGELOG/报告中的说明）。

用法：
    python3 .github/scripts/check_security_invariants.py [仓库根目录]
"""
import json
import os
import re
import sys

ROOT = sys.argv[1] if len(sys.argv) > 1 else "."


def read(path):
    with open(os.path.join(ROOT, path), encoding="utf-8", errors="replace") as fh:
        return fh.read()


def version_tuple(v):
    return tuple(int(x) for x in re.findall(r"\d+", str(v))[:4])


def check(ok, label, detail=""):
    print("  %s  %s%s" % ("PASS" if ok else "FAIL", label, ("  → " + detail) if detail and not ok else ""))
    return bool(ok)


def main():
    failures = 0

    # ---------- 1. 依赖安全基线 ----------
    print("依赖安全基线：")
    ver = json.loads(read("assets/version.json"))
    assets = ver.get("assets", {})

    baselines = {
        # CVE-2021-43861 等在 8.x/9.x 分支内均无修复版本，10.9.8 起才干净
        "Mermaid": (assets.get("Mermaid"), (10, 9, 8)),
        # CVE-2024-28245 / CVE-2025-23207 / 原型链绕过等，0.19.0 已超出全部公告范围
        "KaTeX": (assets.get("KaTeX"), (0, 19, 0)),
    }
    for name, (current, minimum) in baselines.items():
        ok = current is not None and version_tuple(current) >= minimum
        failures += not check(ok, "%s >= %s（当前 %s）" % (name, ".".join(map(str, minimum)), current))


    # 捆绑文件里的真实版本串（防止只改了 version.json 而没换文件）
    katex_js = read("assets/KaTeX/katex.min.js")
    failures += not check('version:"0.19.' in katex_js, "assets/KaTeX/katex.min.js 实际为 0.19.x")
    mermaid_js = read("assets/Mermaid/mermaid.min.js")
    failures += not check("10.9.8" in mermaid_js, "assets/Mermaid/mermaid.min.js 实际为 10.9.8")

    # ---------- 2. 必须存在的防护 ----------
    print("必须存在的防护：")
    must_have = [
        ("src/App/Mermaid.php", "securityLevel", "Mermaid securityLevel 必须由插件强制指定"),
        ("src/App/Mermaid.php", '"strict"', "securityLevel 必须为 strict"),
        ("assets/Mermaid/mermaid-compat.js", "securityLevel", "兼容垫片需同样钉住 securityLevel"),
        ("assets/Mermaid/mermaid-compat.js", "wpEditormdMermaidPrepare", "兼容垫片需提供图表源码还原函数"),
        ("src/App/WPMarkdownParser.php", "data-mermaid", "Mermaid 输出必须是数据驱动（不得再用 document.write 拼脚本）"),
        ("src/App/ImagePaste.php", "MAX_PIXELS", "图片必须带像素预算"),
        ("src/App/ImagePaste.php", "MAX_BINARY_BYTES", "图片必须带解码后体积上限"),
        ("src/App/ImagePaste.php", "wp_tempnam", "临时文件必须使用唯一文件名"),
        ("src/App/ImagePaste.php", "editormd_rate_limit_exceeded", "图片接口必须有限流"),
        ("src/Pages/Pages.php", 'wp_verify_nonce($nonce, "wp_editormd_pages")', "后台 AJAX 必须校验 nonce"),
        ("src/Utils/Logger.php", "REDACTED", "日志 URI 必须脱敏"),
        ("src/Front/FrontStyle.php", 'editormd_dequeue_block_styles", false', "block CSS 移除应为显式 opt-in"),
        ("wp-editormd.php", "wp_editormd_migrating", "迁移必须与登录态解耦并加锁"),
        # 10.4.3：Prism autoloader 的路径必须在 Prism 之后设置
        ("src/App/PrismJSAuto.php", "wp_add_inline_script", "Prism autoloader 配置必须挂在内联脚本队列上以保证顺序"),
        ("src/App/PrismJSAuto.php", "prism_autoloader_init_script", "Prism autoloader 初始化必须走独立方法"),
        # 10.4.3：工具栏按钮必须挡住 Bootstrap 的 .tooltip
        ("assets/Editormd/scss/editormd.menu.scss", "&.tooltip", "工具栏按钮必须钉住 Bootstrap 的 .tooltip 撞名"),
        # 10.4.3：回复框宽度与 wp 未声明变量的防护
        ("assets/Config/editormd.js", "syncReplyEditorWidth", "回复框编辑器宽度必须跟随预览面板显隐"),
        ("assets/Config/editormd.js", 'typeof wp !== "undefined" && wp.utils', "读取 wp 前必须做 typeof 判断"),
        # 10.4.4：评论侧的 Markdown 转换必须能跟着插件的评论编辑器设置自动启用，
        # 否则编辑器里写的 Markdown 会被原样存进数据库
        ("src/App/WPComMarkdown.php", "editormd_comment_markdown_enabled", "评论 Markdown 转换必须可跟随插件设置并保留过滤器"),
        ("src/App/WPComMarkdown.php", 'Config::get_option("support_front", "editor_basics")', "评论 Markdown 转换必须联动 support_front"),
        # 10.4.4：Prism 只允许有一套 —— wp-admin 也必须复用插件这一套
        ("src/Admin/Controller.php", "PrismJSAuto::enqueue_assets", "wp-admin 必须复用插件的 Prism（不得依赖第二套）"),
        ("src/Front/FrontEditor.php", "PrismJSAuto::enqueue_assets", "前台编辑器必须显式提供 Prism"),
        ("src/App/PrismJSAuto.php", "static function enqueue_assets", "Prism 资源入队必须是可复用的静态方法"),
        # 10.4.5：编辑器预览必须认识插件自定义的图片尺寸/属性语法。
        # 这套语法只实现在服务端解析器里，预览侧若不补齐，就会「预览显示裸 Markdown、
        # 发布后却正常」—— 预览失去参考价值（用户实际报障）。
        ("assets/Config/editormd.js", "wp-editormd-img", "预览侧必须补齐图片尺寸语法（fragment 载体）"),
        ("assets/Config/editormd.js", "installRendererHook", "预览侧必须覆写 renderer.image 输出尺寸"),
        ("assets/Config/editormd.js", "Object.setPrototypeOf", "包装 editormd.$marked 后必须透出 marked 的静态成员"),
        # 10.4.5：预览侧的公式误渲染防护必须与服务端同规则
        ("assets/Config/editormd.js", "guardPseudoTex", "预览侧必须压掉行内公式的误渲染"),
        ("assets/Config/editormd.js", "looksLikeFormula", "预览侧的公式判定函数必须存在"),
        ("src/App/KaTeX.php", "katex_looks_like_formula", "服务端的公式判定函数必须存在（与预览侧同规则）"),
    ]
    for path, needle, label in must_have:
        failures += not check(needle in read(path), label, "%s 中未找到 %s" % (path, needle))

    # 10.4.4：Editor.md 自带的第二套 Prism 必须只剩占位文件，
    # 否则它会覆盖 window.Prism 并顶掉插件侧的 toolbar / autoloader（286 KB 白下载）
    prism_lib = read("assets/Editormd/lib/prism.min.js")
    failures += not check(
        len(prism_lib) < 5000,
        "assets/Editormd/lib/prism.min.js 必须只是占位（当前 %d 字节）" % len(prism_lib),
    )
    failures += not check(
        "Prism.languages." not in prism_lib and "Prism.plugins" not in prism_lib,
        "assets/Editormd/lib/prism.min.js 不得再携带第二套 Prism",
    )

    # ---------- 3. 不得复现的写法 ----------
    print("不得复现的写法：")
    must_not = [
        ("src/Pages/Pages.php", "isSameOriginRequest", "不得再以 Origin/Host 作为 nonce 兜底"),
        ("src/Pages/Pages.php", "wp_ajax_nopriv_wp_editormd_pages", "后台页面不得注册 nopriv 入口"),
        ("src/Pages/page/upgrade-release/upgrade-release.php", "function display_page(", "不得再定义全局 display_page()"),
        ("src/App/ImagePaste.php", '"timeout"     => 120', "上游请求不得保留 120 秒超时"),
        ("composer.json", '"php" : ">=5.6.0"', "composer PHP 约束必须与插件头一致"),
        ("src/App/WPMarkdownParser.php", '<div class="mermaid mermaid-diagram no-emojify"><script',
         "Mermaid 不得再输出 document.write 形式的内联脚本"),
        ("src/Front/FrontStyle.php", 'apply_filters("editormd_dequeue_block_styles", true)', "block CSS 移除不得默认开启"),
        # 10.4.3：autoloader 配置若再次挂到 wp_print_footer_scripts（默认优先级 10），
        # 会早于优先级 20 的脚本打印执行 → Prism is not defined + 语言包路径丢失导致 404
        ("src/App/PrismJSAuto.php", 'add_action("wp_print_footer_scripts"', "Prism autoloader 配置不得再挂 wp_print_footer_scripts"),
        ("src/App/PrismJSAuto.php", "prism_wp_footer_scripts", "旧的页脚输出方法必须已移除"),
        # 10.4.3：回复框编辑器宽度不得再被 !important 钉死（会导致右半边永远空白）
        ("assets/Config/editormd.css", "width: 50%!important;\n  margin-left: 0!important;",
         "回复框编辑器宽度不得再被 !important 钉死在 50%"),
        # 10.4.4：解析器不得再用 ENT_COMPAT 整段转义源码 ——
        # 它会把行首 `>` 变成 `&gt;`（引用块渲染不出来）、把 `"` 变成 `&quot;`
        # （带标题的链接直接失配）
        ("src/App/WPMarkdownParser.php", "htmlspecialchars($text, ENT_COMPAT)",
         "Markdown 源码不得再用 ENT_COMPAT 整段转义"),
        # 10.4.5：预览侧不得再直接把 $ 交给 Editor.md 的公式正则 ——
        # Editor.md 只做正则配对、不判断「像不像公式」，会把价格、函数签名、
        # 行内代码一并渲染成公式（与服务端行为不一致）
        ("assets/Config/editormd.js", "baseParagraph.call(this, text)",
         "段落渲染必须先经 guardPseudoTex 再交给 Editor.md"),
        ("src/App/ImagePaste.php", "smms", "第三方图床上传逻辑不得回流"),
        ("src/Utils/Settings.php", "imagepaste_sm", "图床设置项不得回流"),
        ("src/Utils/Activator.php", "imagepaste_sm", "图床默认值不得回流"),
        ("src/Pages/Pages.php", "sm-ms-management", "图床管理页不得回流"),
    ]
    for path, needle, label in must_not:
        failures += not check(needle not in read(path), label, "%s 中仍存在 %s" % (path, needle))

    # composer 不得依赖浮动分支
    composer = read("composer.json")
    floating = re.search(r'"lurenjiasworld/wp-settings-api-class"\s*:\s*"dev-master"', composer)
    failures += not check(floating is None, "composer 不得使用未固定 commit 的 dev-master")

    print()
    if failures:
        print("安全不变量检查未通过：%d 项" % failures)
        return 1

    print("安全不变量检查通过")
    return 0


if __name__ == "__main__":
    sys.exit(main())
