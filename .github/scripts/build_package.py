#!/usr/bin/env python3
"""
打包可安装的 WordPress 插件 zip

用法:
    python3 build_package.py <插件根目录> <输出 zip 路径> [版本号]

为什么需要它：
  本仓库的 .gitignore 忽略了 vendor/、所有 *.min.js / *.min.css、languages/*.mo ——
  也就是说「从 GitHub 源码直接安装」必然因缺少 vendor/autoload.php 而致命报错。
  因此发布前必须先真实构建，再按本脚本的规则打包。

要点：
  * zip 内顶层目录固定为 wp-editormd/（WordPress 安装包要求）
  * 必须包含构建产物与 vendor/ —— gitignore 忽略了它们，但运行期必需
  * 排除开发用文件（node_modules、构建配置、锁文件、演示目录等）
  * 打包后自动校验关键运行时文件确实在包内，缺失则返回非 0
"""

import os
import sys
import zipfile

TOP_DIR = "wp-editormd"

# 整个目录跳过
SKIP_DIRS = {".git", ".github", "node_modules", "docker", "dist", "__pycache__", ".idea", ".vscode"}

# 根级开发文件跳过
SKIP_ROOT_FILES = {
    "webpack.common.js", "webpack.conf.js", "webpack.dev.js", "webpack.prod.js",
    ".babelrc", ".npmrc", ".eslintrc.js", "release.php",
    "package-lock.json", "yarn.lock", ".gitignore", ".npmrc",
}

# 跳过任意深度的这些文件/目录
SKIP_ANY = {"examples", "tests", ".gitignore", ".DS_Store", "Thumbs.db"}

# assets 下明确排除的演示/测试目录（已确认运行期无引用）
SKIP_REL_PREFIXES = (
    "assets/Editormd/examples/",
    "assets/Editormd/tests/",
)

# 打包后必须存在（否则视为打包失败）
REQUIRED = [
    "wp-editormd.php",
    "uninstall.php",
    "vendor/autoload.php",
    "vendor/michelf/php-markdown/Michelf/MarkdownExtra.php",
    "vendor/league/html-to-markdown/src/HtmlConverter.php",
    "vendor/lurenjiasworld/wp-settings-api-class/src/SettingsApi.php",
    "assets/Editormd/editormd.min.js",
    "assets/Editormd/editormd.min.css",
    "assets/Editormd/editormd.preview.min.css",
    "assets/Editormd/lib/modes.min.js",
    "assets/Editormd/lib/addons.min.js",
    "assets/Config/editormd.min.js",
    "assets/Config/editormd-nonce.js",
    "assets/FrontStyle/FrontStyle.min.js",
    "assets/FrontStyle/FrontStyle.min.css",
    "assets/version.json",
    "languages/editormd-zh_CN.mo",
    "languages/editormd-zh_TW.mo",
    "src/Pages/page/upgrade-release/release-note/10.2.1/zh-CN.md",
]


def should_skip(rel):
    """rel 为相对插件根的 POSIX 路径"""
    parts = rel.split("/")

    for p in parts:
        if p in SKIP_DIRS:
            return True
        if p in SKIP_ANY:
            return True

    if len(parts) == 1 and parts[0] in SKIP_ROOT_FILES:
        return True

    for pref in SKIP_REL_PREFIXES:
        if rel.startswith(pref):
            return True

    if rel.endswith(".map"):
        return True

    return False


def main():
    if len(sys.argv) < 3:
        print(__doc__)
        return 1

    root = os.path.abspath(sys.argv[1])
    out_zip = os.path.abspath(sys.argv[2])
    version = sys.argv[3] if len(sys.argv) > 3 else ""

    os.makedirs(os.path.dirname(out_zip), exist_ok=True)

    added = []
    skipped_count = 0

    with zipfile.ZipFile(out_zip, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for dirpath, dirnames, filenames in os.walk(root):
            rel_dir = os.path.relpath(dirpath, root).replace("\\", "/")
            if rel_dir == ".":
                rel_dir = ""

            # 目录级剪枝
            dirnames[:] = sorted(
                d for d in dirnames
                if not should_skip((rel_dir + "/" + d).lstrip("/"))
            )

            for fname in sorted(filenames):
                rel = (rel_dir + "/" + fname).lstrip("/")
                if should_skip(rel):
                    skipped_count += 1
                    continue
                zf.write(os.path.join(dirpath, fname), TOP_DIR + "/" + rel)
                added.append(rel)

    # ---- 校验 ----
    print("=" * 72)
    print("打包完成: %s" % out_zip)
    if version:
        print("版本    : %s" % version)
    print("体积    : %.2f MB" % (os.path.getsize(out_zip) / 1024.0 / 1024.0))
    print("纳入文件: %d 个" % len(added))
    print("排除文件: %d 个" % skipped_count)
    print("=" * 72)

    names = set(added)
    missing = [r for r in REQUIRED if r not in names]
    print()
    if missing:
        print("!! 打包校验失败，缺少以下运行时必需文件：")
        for m in missing:
            print("   - %s" % m)
        return 2

    print("打包校验通过：%d 项运行时必需文件全部在包内。" % len(REQUIRED))

    # 按目录汇总
    print()
    print("包内目录分布：")
    buckets = {}
    for rel in added:
        key = rel.split("/")[0] if "/" in rel else "(根文件)"
        if rel.startswith("src/"):
            key = "src/"
        elif rel.startswith("assets/"):
            key = "assets/"
        elif rel.startswith("vendor/"):
            key = "vendor/"
        elif rel.startswith("languages/"):
            key = "languages/"
        buckets[key] = buckets.get(key, 0) + 1
    for k in sorted(buckets, key=lambda x: -buckets[x]):
        print("   %-16s %d 个文件" % (k, buckets[k]))

    return 0


if __name__ == "__main__":
    sys.exit(main())
