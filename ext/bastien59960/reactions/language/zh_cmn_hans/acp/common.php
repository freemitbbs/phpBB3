<?php
/**
 * File: language/en/acp/common.php
 * Extension: bastien59960/reactions
 *
 * Description:
 * English language strings for the Administration Control Panel (ACP)
 * of the Reactions extension.
 *
 * @copyright (c) 2025 Bastien59960
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined('IN_PHPBB')) {
    exit;
}

$lang = array_merge($lang ?? [], [
    // Module Titles
    'ACP_REACTIONS_TITLE'                => '回应 (Reactions) 扩展',
    'ACP_REACTIONS_SETTINGS'             => '设置',
    'ACP_REACTIONS_SETTINGS_EXPLAIN'     => '配置回应行为、限制和外观。',

    // Setting Descriptions
    'REACTIONS_SPAM_TIME'                => '邮件摘要延迟',
    'REACTIONS_SPAM_TIME_EXPLAIN'        => '向同一用户发送两封邮件摘要之间的最小延迟时间（以分钟为单位）。',
    'REACTIONS_MAX_PER_POST'             => '单帖最大回应类型数',
    'REACTIONS_MAX_PER_POST_EXPLAIN'     => '单个帖子上允许出现的不同回应类型的最大数量。',
    'REACTIONS_MAX_PER_USER'             => '单人单帖最大回应数',
    'REACTIONS_MAX_PER_USER_EXPLAIN'     => '单个用户可以对单个帖子添加的不同回应的最大数量。',

    'REACTIONS_DISPLAY_SETTINGS'         => '显示与选择器设置',
    'REACTIONS_POST_EMOJI_SIZE'          => '帖子下方表情大小',
    'REACTIONS_POST_EMOJI_SIZE_EXPLAIN'  => '定义帖子下方显示的回应图标大小（以像素为单位）。',
    'REACTIONS_PICKER_EMOJI_SIZE'        => '选择器图标大小',
    'REACTIONS_PICKER_EMOJI_SIZE_EXPLAIN'=> '选择器及分类选项卡中表情的大小（以像素为单位）。',
    'REACTIONS_PICKER_WIDTH'             => '选择器宽度',
    'REACTIONS_PICKER_WIDTH_EXPLAIN'     => '表情选择器的宽度（以像素为单位）。',
    'REACTIONS_PICKER_HEIGHT'            => '选择器高度',
    'REACTIONS_PICKER_HEIGHT_EXPLAIN'    => '表情选择器的高度（以像素为单位）。',
    'REACTIONS_PICKER_SHOW_CATEGORIES'   => '显示分类',
    'REACTIONS_PICKER_SHOW_CATEGORIES_EXPLAIN' => '取消勾选将隐藏分类选项卡，仅显示常用表情。',
    'REACTIONS_PICKER_SHOW_SEARCH'       => '显示搜索',
    'REACTIONS_PICKER_SHOW_SEARCH_EXPLAIN' => '取消勾选将从选择器中移除搜索框。',
    'REACTIONS_PICKER_USE_JSON'          => '加载完整表情包',
    'REACTIONS_PICKER_USE_JSON_EXPLAIN'  => '取消勾选将不加载外部 JSON 文件，仅显示 10 个常用表情。',
    'REACTIONS_SYNC_INTERVAL'            => '刷新间隔（秒）',
    'REACTIONS_SYNC_INTERVAL_EXPLAIN'    => '回应数据自动更新的时间间隔（以秒为单位）。',

    // --- Admin Log Messages ---
    'LOG_REACTIONS_IMPORT_START'         => '<strong>尝试导入回应数据</strong><br>• 正在搜索旧版回应扩展的数据。',
    'LOG_REACTIONS_IMPORT_EMPTY'         => '<strong>回应导入已跳过</strong><br>• 发现旧数据表，但内容为空。',
    'LOG_REACTIONS_IMPORT_SUCCESS'       => '<strong>回应导入完成</strong><br>• 已导入 %1$d 条回应（跳过 %2$d 条）。<br>• 影响了 %3$d 名用户和 %4$d 个帖子。',

]);
