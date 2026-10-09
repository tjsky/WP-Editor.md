<?php

namespace EditormdApp;

class TaskList {

    public function __construct() {
        add_filter("the_content", array($this, "taskList_markup")); // before wptexturize

        /**
         * 评论路径是否渲染任务列表复选框（10.5.0）
         *
         * 简版评论模式不提供任务列表。除了编辑器侧关闭 `taskList`，
         * 服务端也必须摘掉这条钩子：复选框是渲染期由本方法生成的 `<input>`，
         * 不受保存期 KSES 约束，而 `<ul>/<li>` 早已被剥掉 —— 留着只会得到
         * 「没有列表结构的孤立复选框」这种半残形态。
         */
        if (! SimpleCommentMode::enabled()) {
            add_filter("comment_text", array($this, "taskList_markup")); // before wptexturize
        }
    }

    public function taskList_markup($content) {
        $textarr = wp_html_split($content);

        $regex = "/^\s*\[[x\s]\]\s*/";

        foreach ($textarr as &$element) {

            if ("" == $element || "<" === $element[0]) {
                continue;
            }

            $element = preg_replace_callback($regex, array($this, "taskList_src"), $element);

        }

        return implode("", $textarr);
    }

    public function taskList_src($matches) {

        $taskList = $matches[0];

        $regex_1 = "/^\s*\[\s\]\s*/";

        $regex_2 = "/^\s*\[x\]\s*/";

        if (preg_match($regex_1, $taskList)) {
            $taskList = preg_replace("/^\s*\[\s\]\s*/", '<input style="margin-right:5px" type="checkbox" class="task-list-item-checkbox" disabled />', $taskList);
        }

        if (preg_match($regex_2, $taskList)) {
            $taskList = preg_replace("/^\s*\[x\]\s*/", '<input style="margin-right:5px" type="checkbox" class="task-list-item-checkbox" checked disabled />', $taskList);
        }

        return $taskList;
    }
}