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

    vue_pkg = json.loads(read("src/Pages/page/sm-ms-management/package.json"))
    deps = dict(vue_pkg.get("dependencies", {}), **vue_pkg.get("devDependencies", {}))
    # 说明：Vue 保持在 2.6.11 —— 2.x 全系都落在 GHSA-5j4c-8p2g-v4jx 的范围内，
    # 官方修复只存在于 3.0，属于 Vue 3 迁移的范畴，不在本版范围。
    for name, minimum in (("axios", (1, 20, 0)),):
        raw = re.sub(r"[^\d.]", "", str(deps.get(name, "")))
        ok = raw and version_tuple(raw) >= minimum
        failures += not check(ok, "sm-ms 依赖 %s >= %s（当前 %s）" % (name, ".".join(map(str, minimum)), raw or "缺失"))

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
        ("src/Pages/page/sm-ms-management/sm-ms-management.php", "wp_editormd_sm_ms_resolve_operation", "sm.ms 代理必须走固定操作白名单"),
        ("src/Utils/Logger.php", "REDACTED", "日志 URI 必须脱敏"),
        ("src/Front/FrontStyle.php", 'editormd_dequeue_block_styles", false', "block CSS 移除应为显式 opt-in"),
        ("wp-editormd.php", "wp_editormd_migrating", "迁移必须与登录态解耦并加锁"),
    ]
    for path, needle, label in must_have:
        failures += not check(needle in read(path), label, "%s 中未找到 %s" % (path, needle))

    # ---------- 3. 不得复现的写法 ----------
    print("不得复现的写法：")
    must_not = [
        ("src/Pages/Pages.php", "isSameOriginRequest", "不得再以 Origin/Host 作为 nonce 兜底"),
        ("src/Pages/Pages.php", "wp_ajax_nopriv_wp_editormd_pages", "后台页面不得注册 nopriv 入口"),
        ("src/Pages/page/sm-ms-management/sm-ms-management.php", '"token"', "sm.ms 令牌不得下发给浏览器"),
        ("src/Pages/page/sm-ms-management/sm-ms-management.php", "function display_page(", "不得再定义全局 display_page()"),
        ("src/Pages/page/upgrade-release/upgrade-release.php", "function display_page(", "不得再定义全局 display_page()"),
        ("src/App/ImagePaste.php", '"timeout"     => 120', "上游请求不得保留 120 秒超时"),
        ("composer.json", '"php" : ">=5.6.0"', "composer PHP 约束必须与插件头一致"),
        ("src/App/WPMarkdownParser.php", '<div class="mermaid mermaid-diagram no-emojify"><script',
         "Mermaid 不得再输出 document.write 形式的内联脚本"),
        ("src/Front/FrontStyle.php", 'apply_filters("editormd_dequeue_block_styles", true)', "block CSS 移除不得默认开启"),
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
