#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""生成 GitHub Release 正文（CI 用）

背景（用户约定）：
  1. 「本版变更」必须写出**本版本具体的变更内容**，形态与 CHANGELOG.md 里
     对应版本段落一致 —— 而不是只给一个「见 CHANGELOG」的链接；
  2. 「维护分支累计改动」不再逐条列出（它会随版本无限增长，Release 页面越读越长），
     整段压缩成一句：`差异与逐项说明见仓库 README 与 CHANGELOG。`

因此正文不再硬编码在 workflow 里，而是由本脚本从 CHANGELOG.md 抽取对应版本段落
拼装而成。这样「Release 页面」与「CHANGELOG」只有一处事实来源：CHANGELOG.md。

用法：
    python3 .github/scripts/release_body.py <版本号> <输出文件路径>

示例：
    python3 .github/scripts/release_body.py 10.5.0 /tmp/release-body.md
"""
import os
import re
import sys

REPO = "https://github.com/tjsky/WP-Editor.md"
README_URL = REPO + "#和原版有什么不同"
CHANGELOG_URL = REPO + "/blob/master/CHANGELOG.md"

# CHANGELOG.md 里的版本标题形态：`### Version 10.5.0`
SECTION_RE = re.compile(r"^###\s+Version\s+(\S+)\s*$", re.MULTILINE)

HEADER = """> **这是修改版（Modified Version），非上游原版。**
> 上游 [LuRenJiasWorld/WP-Editor.md](https://github.com/LuRenJiasWorld/WP-Editor.md)
> 最后版本为 10.2.1，长期未更新，并已于 2025-04-09 被 WordPress.org 以安全问题下架
> （对应 **CVE-2025-31035**，Stored XSS，影响 `<= 10.2.1`）。本 Release 为在其基础上
> 完成安全加固与新版兼容性适配的维护版本。

## 安装

下载下方 `wp-editormd-{version}.zip`，在 WordPress 后台
「插件 → 安装插件 → 上传插件」中上传并启用即可。

该 zip 已包含 `vendor/` 与全部前端编译产物，**可直接安装**。
请勿使用 GitHub 自动生成的 Source code 压缩包 —— 它缺少这些运行期必需文件。
"""

TAIL = """## 授权

本项目沿用上游的 **GNU GPL v3 or later**。
"""


def extract_section(changelog_text, version):
    """从 CHANGELOG.md 抽取指定版本的段落（不含标题行本身）

    @param str changelog_text CHANGELOG.md 全文
    @param str version       形如 "10.5.0"
    @return str              段落正文（首尾空白已去除）
    @raise SystemExit        找不到该版本时
    """
    lines = changelog_text.splitlines()
    start = None

    for index, line in enumerate(lines):
        match = SECTION_RE.match(line)
        if not match:
            continue
        if match.group(1) == version and start is None:
            start = index + 1
            break

    if start is None:
        raise SystemExit(
            "CHANGELOG.md 中找不到 Version %s 的段落；"
            "发版前请先补上该版本的变更说明。" % version
        )

    body = []
    for line in lines[start:]:
        if SECTION_RE.match(line):
            break
        body.append(line)

    return "\n".join(body).strip()


def build_body(version, section):
    """拼装完整 Release 正文"""
    return "\n".join([
        HEADER.format(version=version),
        "## 本版变更\n",
        section + "\n",
        "## 维护分支累计改动\n",
        "差异与逐项说明见仓库 [README](%s) 与 [CHANGELOG](%s)。\n"
        % (README_URL, CHANGELOG_URL),
        TAIL,
    ])


def main(argv):
    if len(argv) != 3:
        raise SystemExit("用法：release_body.py <版本号> <输出文件路径>")

    version = argv[1].strip()
    out_path = argv[2]

    repo_root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
    changelog_path = os.path.join(repo_root, "CHANGELOG.md")

    with open(changelog_path, encoding="utf-8") as handle:
        changelog_text = handle.read()

    section = extract_section(changelog_text, version)
    body = build_body(version, section)

    with open(out_path, "w", encoding="utf-8") as handle:
        handle.write(body)

    print("已生成 Release 正文：%s（版本 %s，本版变更 %d 字符）"
          % (out_path, version, len(section)))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
