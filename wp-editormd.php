<?php
/**
 * Plugin Name:       WP Editor.md
 * Plugin URI:        https://github.com/tjsky/WP-Editor.md
 * Description:       Perhaps this is the best and most perfect Markdown editor in WordPress
 * Version:           10.5.4
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Tested up to:      7.1
 * Author:            LuRenJiasWorld (maintained fork by tjsky)
 * Author URI:        https://github.com/tjsky/WP-Editor.md
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       editormd
 * Domain Path:       /languages
 *
 * 本 fork 的改造（自 10.3.0 起）：
 *
 * 10.5.3 —— 静态资源默认走维护者 CDN
 *   「编辑器静态资源地址」的默认值从插件目录改为
 *   https://cdn.jsdelivr.net/gh/tjsky/wp-editormd-cdn@<版本>（tag 跟随 WP_EDITORMD_VER）。
 *   资源与插件解耦后，升级不必重新下发几十 MB 静态文件；插件自带的本地资源仍完整保留，
 *   内网环境把地址换回 WP_EDITORMD_URL 即可，另提供 editormd_default_static_address
 *   过滤器供自建镜像覆盖。
 *   - 迁移逻辑收窄：只有「cdn.jsdelivr.net/wp/wp-editormd」（wp.org 已下架通道）才迁到
 *     新默认值。10.5.1 那版是「出现 jsdelivr 就改回本地」，默认值换成 CDN 后会把用户
 *     刚填的地址立刻冲掉；
 *   - KaTeX 地址判断修正：开关取值是 katex 而非 on，原判断永不成立，公式资源一直走
 *     Editor.md 内置的 npm 地址，换 CDN 时不跟随；
 *   - 前台兜底：站点未进过后台时 editor_addres 为空，会拼出指向站点根的 /assets/...，
 *     现在退回默认地址。
 *
 * 10.5.2 —— 后台评论列表里编辑器与原生 textarea 失步
 *   评论的「快速编辑」与「回复」共用同一个 wp_editor（replycontent），核心只通过
 *   $('#replycontent').val() 读写内容；而本插件把这个 textarea 隐藏、改用 CodeMirror
 *   呈现，两边读写完全脱钩，于是：
 *   - 点「快速编辑」时评论原文只写进了看不见的 textarea，弹出的编辑器是空白的；
 *   - 回复完一条再点另一条的「回复」，编辑器里还留着上一段输入。
 *   修法：回复行每次变为可见时以 textarea 为准回灌 CodeMirror，关闭且 textarea 已为
 *   空时再清掉编辑器残留。不在隐藏瞬间无条件清空 —— close() 与 open() 在同一个任务
 *   里先后执行，反向写 textarea 会把刚填入的评论内容抹掉。
 *
 * 10.5.1 —— 跨站资源的完整性校验（integrity）
 *   「编辑器静态资源地址」允许指向本站以外的主机，页面里的 JS / CSS 就由对方提供：
 *   设置填错，或对方主机被入侵、被替换，访客就会执行到别人的代码。本版把
 *   editor_style → editor_integrity 里配置的 sha384 哈希套到插件输出的
 *   <script> / <link> 上，交给浏览器校验，哈希对不上就拒绝执行：
 *   - 只处理主机不在本站的资源。本站资源不加属性 —— 同源资源本就没有第三方能篡改，
 *     而加 integrity 必须同时加 crossorigin，同主机仅协议/端口不同会被浏览器
 *     判成跨源，反而可能把好资源拦下来；
 *   - 两条路径都覆盖：PHP 入队（script_loader_tag / style_loader_tag）与编辑器
 *     运行时按需加载（包装 editormd.loadScript / loadCSS）。Prism 的语法组件是按
 *     语言惰性拉取的、无法逐个校验，故资源地址指向外部主机时固定从本机取；
 *   - 哈希不必手算：设置页提供「用本机文件生成」（本机资源与发布包逐字节一致）；
 *   - 资源地址在别的主机、又没配哈希时，设置页会在相关设置项旁**持续**显示提示；
 *     「高级 → 调试信息」另列出「本机会用到、但还没有哈希」的文件清单。
 *
 *   另修：手机上打开后台「回复评论」框时，编辑区只有半个屏幕宽。窄屏下 WordPress
 *   把评论列表的每一行排成 flex 容器（.wp-list-table tr{display:flex}），而「回复
 *   评论」行只有一个单元格、命中不了任何宽度规则 —— 作为 flex 项退化成「按内容
 *   宽度」。WP 自带的编辑框里有固定宽度的输入区，看着正常；换成 Editor.md 后整棵
 *   子树都是可压缩的 div，宽度塌到最小内容宽度（实测 390px 视口下 171px，而回复行
 *   有 366px）。现在窄屏（≤782px）下把该单元格固定成整行宽。仅影响后台回复框。
 *
 *   另修：「评论正文容器」的重复包裹判断原先用「正文里是否出现过容器类名」来做，
 *   而评论正文是访客可控的 —— 只要评论里写了那串字（<span class="editormd-comment-body">，
 *   KSES 放行 span 的 class），整条评论就会跳过包裹，图片宽度约束与 no-emojify 齐失效。
 *   现在要求它出现在 <div> 的 class 属性里（评论白名单不含 div，只有自己的容器会命中）。
 *
 * 10.5.0 —— 新增「访客评论简版编辑器」
 *   评论区属于**不可信输入**，WordPress 只用一张 15 个标签的 KSES 白名单过滤它，
 *   而编辑器工具栏却提供了图片、代码块、表格、标题、列表等白名单之外的功能：
 *   预览里看着正常（预览是纯前端的），保存时却被 wp_filter_kses 剥掉 ——
 *   同一段内容「预览能看见、发出去就没了」。本版为访客评论提供一套受控的简版模式：
 *   - 单栏编辑，不跟随站点的 live_preview 全局选项（手机不再被双栏挤压输入区），
 *     工具栏收敛为「粗体 / 斜体 / 删除线 / 引用 / 行内代码 / 链接 / 图片 + 纯预览」；
 *   - 链接降级为只读文本「文字：<code>url</code>」，不再生成 <a>；图片去掉外链包裹，
 *     并强制 loading="lazy" 与 referrerpolicy="no-referrer"；
 *   - 公式、任务列表、代码块、emoji 短代码在评论区一并关闭 —— 要点是**预览侧与
 *     服务端同时关**，只关一边只会制造新的预览/发布不一致；
 *   - KSES 白名单按需扩展（只加 img，且不含 class/style/srcset/on*），并在
 *     「高级 → 调试信息」里把当前生效的白名单、以及「哪些条目是本插件扩展、
 *     而非 WordPress 原生」摊开给管理员看；
 *   - 默认关闭，开关位于「设置 → WP Editor.md → Basic Settings」。
 *   另修：升级器的 switch 此前缺少 case "10.4.3" 与 case "10.4.5"，停在这两个版本
 *   上的站点匹配不到任何分支，editor_version 永不推进 —— 而主文件正是用版本号是否
 *   变化来判断要不要跑激活流程，结果每个请求都会重跑一次 Activator::activate()。
 *
 *   另修：后台「回复评论」编辑器在隐藏状态下（回复行 display:none）初始化，会连出三个问题：
 *   打开后没有光标、键盘无法输入（CodeMirror 误判为已聚焦）；行号栏量到 0 宽；
 *   工具栏高度量到 0，使得工具栏压住正文第一行（含行号）。现在回复行变为可见时会
 *   重新测量排版并真正取回焦点；仅影响后台回复框。
 *
 *   同版本一并处理的三件事：
 *   - **移除第三方图床（sm.ms）**。该服务已转向收费模式，而它带来的维护面不小
 *     （令牌代理、固定操作白名单、Vue 管理页、额外的 axios/Vue 依赖）。本次整体下线：
 *     上传分流、访问令牌与代理接口、后台管理页及其子项目、设置项与语言包条目一并删除，
 *     图片粘贴只保存到本站媒体库；升级器负责清理老站点遗留的 imagepaste_sm /
 *     imagepaste_sm_token，避免开关残留让人误以为还在往第三方传；
 *   - **前台编辑器风格可与后台解耦**。「工具栏/预览窗口风格」与「Markdown 编辑器风格」
 *     原先同时喂给后台编辑器和前台评论框，但两者的使用场景（以及主题配色）通常不同。
 *     新增 editor_style → front_style_sync（默认关）：关闭时前台一律用 Editor.md
 *     自带浅色，跟随主题；后台编辑器不受该开关影响，需要夜间模式时由站点自行写 CSS；
 *   - **后台满屏「检验到插件资源包已过时」**。该检查的输出原先挂在 admin_notices 上，
 *     而它是被 get_settings_fields() 里的字段 desc 调用的、跑在 admin_init 阶段 ——
 *     后台**每个**页面都会触发 admin_init，于是一条本该只出现在设置页的状态行变成了
 *     全后台通告。现改为只在插件设置页显示一行状态，并直接写出资源包版本与插件版本；
 *     资源包版本的 transient 键带上插件版本，升级后不再吃 12 小时旧缓存；
 *   - **块主题下评论框漏出一个原生 textarea**。Twenty Twenty-Four / Twenty-Five 的
 *     `.wp-block-post-comments-form .comment-form textarea{display:block}` 权重高于
 *     Editor.md 隐藏中间元素的 `.editormd .editormd-html-textarea{display:none}`，
 *     于是渲染后的 HTML 源码被当成可选文本露在评论框下方。现由插件自有样式直接钉死，
 *     不再与主题比权重。
 *
 * 10.4.5 —— 修复「编辑器预览与保存结果不一致」
 *   预览走的是 Editor.md 自带的 marked，服务端走的是插件自己的解析器，
 *   两边的能力此前并不对等，于是同一段内容「预览里是乱码、发布后却正常」：
 *   - 预览不认识插件的图片尺寸语法。`![alt](img =600)` / `=300x200` / `=x400`
 *     与 `{#id .class}` 只实现在服务端解析器（保存那一刻才生效），marked 会把
 *     整个构造判为「不是图片」并原样吐出裸 Markdown。现由 assets/Config/editormd.js
 *     在解析前把尺寸与属性搬进 URL 的 fragment，再覆写 renderer.image 还原成
 *     带 width / height / style / id / class 的 <img>，输出规则与服务端逐条对齐；
 *   - 预览会把普通文本当成公式。Editor.md 的段落渲染只做 `$…$` 正则配对、不判断
 *     内容像不像公式，「价格从 $100 涨到 $200」「function update( $a, $b )」乃至
 *     行内代码里的 `$` 都会被渲染成公式 —— 而服务端对这些是有防护的（定界符必须
 *     紧贴内容、内容须含字母/数字/反斜杠，并跳过 code/pre）。现把同一套规则搬到
 *     预览侧，两侧的公式数量与正文 `$` 数量实测完全一致；
 *   - 回归补充：新增「预览 vs 发布」一致性脚本，此前的回归只覆盖保存后的 HTML 与
 *     前台渲染，完全没覆盖编辑器预览（这正是本版问题得以漏过测试的原因）。
 *
 * 10.4.4 —— 修复评论侧的 Markdown 链路与 Prism 双份加载
 *   - 访客评论里的 Markdown 不再原样入库。评论的转换开关沿用自 WordPress.com
 *     的 Markdown 模块（设置 → 讨论 → Markdown），默认关闭且与插件自己
 *     「支持前端评论 / 支持后台回复」毫无联动：编辑器里预览得好好的内容，
 *     提交后被原样存进数据库。现改为只要插件开着评论编辑器就自动启用转换，
 *     并隐藏那个会误导人的遗留勾选框（可用 editormd_comment_markdown_enabled 关闭）；
 *   - 修复 html_decode 关闭时「引用」与「带标题的链接」必然失效：解析器原先用
 *     htmlspecialchars(ENT_COMPAT) 把源码整段转义，行首 `>` 变成 `&gt;`（引用块
 *     渲染不出来）、`"` 变成 `&quot;`（`[文字](url "标题")` 直接失配）。现只转义
 *     `&` 与 `<`（已足以阻止任何标签成形），并还原行首的引用标记；
 *   - Prism 只保留一套。编辑器预览此前会再加载 Editor.md 自带的 lib/prism.min.js
 *     （286 KB、132 种语言、且是另一个版本 1.15.0），与插件的 Prism 1.19 互相覆盖
 *     window.Prism —— 后加载的那份把插件侧的 toolbar / autoloader 顶掉，代码块
 *     没有复制按钮也没有语言标签，页面还白白多下载 286 KB。
 *     现该文件改为占位，wp-admin 也复用插件这一套，前后台表现一致；
 *   - Prism 资源的版本指纹由写死的 1.15.0 改为真实的 1.19.0。
 *
 * 10.4.3 —— 修复前端缺陷
 *   - 控制台报 Prism is not defined：设置代码高亮 autoloader 语言包路径的内联脚本
 *     原先挂在 wp_print_footer_scripts 默认优先级（10）上输出，而页脚脚本在优先级 20
 *     才打印，脚本先于 Prism 执行；同时该脚本没跑成会让 languages_path 为空，
 *     autoloader 退回默认相对路径去请求 /components/prism-*.min.js（404）。
 *     现改为 wp_add_inline_script 挂在插件脚本句柄之后，并把纯文本语言预声明为空语法；
 *   - 编辑器工具栏按钮被主题的 Bootstrap 全局 .tooltip（position:absolute;opacity:0）
 *     命中而透明堆叠 —— 插件按钮的类名就叫 tooltip，现由插件按自己的语义钉回定位与透明度；
 *   - 后台回复框右半边空白：编辑器宽度原先被 !important 钉死在 50%，而预览面板默认隐藏，
 *     现改为跟随预览显隐（关闭时占满整行）；
 *   - 前台控制台每秒一次的 wp is not defined：字数统计写成了 `wp && wp.utils`，
 *     而前台没有 wp 全局变量，改为 typeof 判断。
 *
 * 10.4.2 —— 按代码审查报告做安全加固
 *   - Mermaid 8.4.8 → 10.9.8（8.x 有 CVE-2021-43861，8.x/9.x 分支均无修复版本），
 *     并强制 securityLevel=strict；因 10.x 移除了 Editor.md 预览器调用的 mermaid.init()，
 *     另加 mermaid-compat.js 垫片；
 *   - KaTeX 0.11.1 → 0.19.0，渲染显式 trust:false；
 *   - 图片粘贴补解码体积上限、像素与内存预算（都在 GD 解码前拦截）、上游超时 120s → 10s、
 *     按用户限流，临时文件改用 wp_tempnam()；
 *   - sm.ms 令牌不再下发浏览器，代理改为固定操作白名单；
 *   - 后台 AJAX 去掉「按 Host 判同源」兜底并移除 nopriv 入口；日志 URI 脱敏；
 *   - 前台不再默认移除区块样式（改显式 opt-in）；页面渲染函数加插件前缀。
 *
 * 10.4.1 —— 修复发布的包在后台不显示插件信息
 *   - 上一版（10.3.0 / 10.4.0）的 GitHub 包中，插件头里的 Version / Author /
 *     Author URI / Requires at least / Requires PHP / Tested up to 被注释剥离脚本
 *     误删（这些行与块注释续行形态一致，且所在 diff hunk 的 -/+ 行数不等，
 *     「还原基线原文」的兜底未触发），导致后台「上传插件」界面缺字段、更新检查拿不到版本号。
 *     已按修复后的工具重新生成，并补上专用校验与 CI 闸门。
 *
 * 10.3.0 —— 安全加固与兼容性
 *   - 修复 KaTeX 渲染链路的存储型 XSS（对应 CVE-2025-31035 同类问题）；
 *   - 修复图片粘贴接口缺失权限与 CSRF 校验、任意文件写入、图床代理 SSRF 等问题；
 *   - 修复设置页选项数据被相互覆盖、后台每个请求同步外联等缺陷；
 *   - 兼容 WordPress 7.1 与 PHP 8.4。
 *
 * 10.4.0 —— 公式解析与图片尺寸
 *   - 修复代码块 / 行内代码中的 $ 被误当公式渲染（上游长期反馈问题）；
 *   - 修复正文中误配对的 $ 被渲染成公式、块级公式被内联规则二次解析；
 *   - 修复 xmlrpc 请求下抛出 Class 'EditormdApp\IXR_Message' not found（采纳上游 PR #546）；
 *   - 新增图片尺寸语法：![alt](img.jpg =600) / =600x400 / =x400。
 */

namespace EditormdRoot;

use Editormd\Main;
use EditormdUtils\Activator;
use EditormdUtils\Logger;
use EditormdUtils\Deactivator;

define( 'WP_EDITORMD_VER', '10.5.4' );                      // 版本说明
define( 'WP_EDITORMD_URL', plugins_url( '', __FILE__ ) );   // 插件资源路径
define( 'WP_EDITORMD_PATH', dirname( __FILE__ ) );          // 插件路径文件夹
define( 'WP_EDITORMD_NAME', plugin_basename( __FILE__ ) );  // 插件名称

// 自动载入文件
require_once WP_EDITORMD_PATH . '/vendor/autoload.php';

/**
 * 调试-日志模式
 *
 * 安全修复：原实现把这段逻辑放在 require vendor/autoload.php **之前**，
 * 于是在自动加载器注册前就调用了 Logger，任何携带 wp-editormd-dev-logmode
 * Cookie 的访客都会触发 "Class EditormdUtils\Logger not found" 致命错误（白屏）；
 * 而且判断用的 Cookie 名（-dev-logmode）与读取的键名（-logmode-dev）并不一致，
 * 读取到未定义键时还会向 set_log_level() 传入 null。
 * 现在改为：autoload 之后执行、限定管理员、取值净化、异常兜底。
 *
 * 兼容性修复（致命，实测发现）：本段逻辑**不得写在插件主文件顶层**。
 * 插件主文件由 wp-settings.php 在「加载活动插件」阶段 include（第 582 行），
 * 而提供 current_user_can() / wp_get_current_user() 的 pluggable.php 要到
 * 第 612 行才加载。在顶层调用 current_user_can() 会直接抛
 *   Fatal error: Call to undefined function wp_get_current_user()
 * 且发生在插件加载期 —— 前台与后台**同时白屏**，属最高级别的可用性事故。
 * 因此改挂 plugins_loaded：该钩子（第 630 行触发）晚于 pluggable.php。
 */
add_action( 'plugins_loaded', function () {
    if ( ! isset( $_COOKIE['wp-editormd-dev-logmode'] ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $editormd_log_level = sanitize_text_field( wp_unslash( $_COOKIE['wp-editormd-dev-logmode'] ) );

    try {
        Logger::set_log_level( $editormd_log_level );
    } catch ( \Throwable $e ) {
        // 日志等级非法时不应影响页面渲染
        unset( $e );
    }
} );

/**
 * 插件激活期间运行的代码
 *
 * @param bool $network_wide 多站点环境下是否为「网络启用」
 */
function activate_editormd( $network_wide = false ) {
    Activator::activate( $network_wide );
}

/**
 * 在插件停用期间运行的代码
 * includes/class-plugin-name-deactivator.php
 */
function deactivate_editormd() {
    Deactivator::deactivate();
}

register_activation_hook( __FILE__, '\EditormdRoot\activate_editormd' );
register_deactivation_hook( __FILE__, '\EditormdRoot\deactivate_editormd' );

/**
 * 执行插件函数
 */
function run_editormd() {
    if ( version_compare( PHP_VERSION, '7.4.0' ) < 0 ) {
        unset( $_GET['activated'] );
        add_action( 'admin_notices', function () {
            $message = __( 'Hey, we\'ve noticed that you\'re running an outdated version of PHP which is no longer supported. Make sure your site is fast and secure, by upgrading PHP to the latest version.', 'editormd' );
            printf( '<div class="error"><p>%1$s</p></div>', esc_html( $message ) );
        } );
    } else {
        /**
         * 版本更新后需要执行 Activator 中的 Upgrader。
         *
         * 性能修复（10.3.0）：原实现只要用户已登录，就在每次请求都重跑一遍
         * Activator::activate()（内含十余次 get_option 与 new Upgrader()），
         * 而 Upgrader 中还带有 wp_redirect()，开销与副作用都很大。
         * 现在改为仅在版本号发生变化时才执行。
         *
         * 可用性修复（10.4.2）：上面那条判断原先额外要求「当前用户已登录」，
         * 于是升级后若长期只有匿名访客访问、没有管理员登录，迁移就迟迟不执行，
         * editor_version 一直停在旧值。现改为与登录态无关：
         *   - 版本一致直接返回，正常请求只多一次 get_option（已缓存）；
         *   - 加一把 transient 锁，避免多个并发请求同时执行激活/迁移；
         *   - 迁移异常写入 error_log，避免静默失败。
         */
        add_action( 'init', function () {
            $installed = get_option( 'editor_version' );

            if (
                is_array( $installed )
                && isset( $installed['wp_editormd_ver'] )
                && $installed['wp_editormd_ver'] === WP_EDITORMD_VER
            ) {
                return;
            }

            if ( get_transient( 'wp_editormd_migrating' ) ) {
                return;
            }

            set_transient( 'wp_editormd_migrating', 1, MINUTE_IN_SECONDS );

            try {
                Activator::activate();
            } catch ( \Throwable $e ) {
                error_log( 'WP Editor.md: 升级/迁移失败 - ' . $e->getMessage() );
                delete_transient( 'wp_editormd_migrating' );
                return;
            }

            delete_transient( 'wp_editormd_migrating' );
        } );

        new Main();
    }
}

/**
 * 开始执行插件
 */
run_editormd();
