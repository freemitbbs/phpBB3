<?php
/**
 * File: language/en/common.php — bastien59960/reactions
 * @author Bastien (bastien59960)
 * @github https://github.com/bastien59960/reactions
 *
 * Role:
 * This file contains the general English language strings for the user
 * interface (UI), error messages, tooltips, and extension options.
 * It is loaded on most pages where the extension is active.
 *
 * @copyright (c) 2025 Bastien59960
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined('IN_PHPBB')) {
    exit;
}

$lang = array_merge($lang ?? [], [
    // =============================================================================
    // USER INTERFACE MESSAGES
    // =============================================================================
    'REACTION_ADD'              => '添加表态',
    'REACTION_REMOVE'           => '移除你的表态',
    'REACTION_MORE'             => '更多表态',
    'REACTION_LOADING'          => '加载中...',
    'REACTION_ERROR'            => '操作失败',
    'REACTION_SUCCESS_ADD'      => '表态成功',
    'REACTION_SUCCESS_REMOVE'   => '表态已移除',

    // =============================================================================
    // ERROR AND VALIDATION MESSAGES
    // =============================================================================
    'REACTION_NOT_AUTHORIZED'   => '你没有权限进行表态',
    'REACTION_OWN_POST'         => '不能对自己的帖子表态',
    'REACTION_INVALID_POST'     => '无效帖子',
    'REACTION_INVALID_EMOJI'    => '无效表情',
    'REACTION_ALREADY_ADDED'    => '你已经使用过该表情了',
    'REACTION_ALREADY_EXISTS'   => '你已经使用过该表情了', // Compatibility
    'REACTION_NOT_FOUND'        => '未找到相关表态',

    // =============================================================================
    // COUNTERS AND DISPLAY
    // =============================================================================
    'REACTION_COUNT_SINGULAR'   => '%d 个表态',
    'REACTION_COUNT_PLURAL'     => '%d 个表态',
    'REACTIONS_TITLE'           => '表态记录',
    'NO_REACTIONS'              => '暂无表态',
    'REACTIONS_BY_USERS'        => '用户的表态',
    'REACTION_BY_USER'          => '来自 %s 的表态',
    'REACTIONS_SEPARATOR'       => '、',
    'REACTION_AND'              => ' 和 ',

    // =============================================================================
    // EMOJIS AND INTERFACE
    // =============================================================================
    'REACTIONS_COMMON_EMOJIS'   => '常用表情',
    'REACTIONS_LOGIN_REQUIRED'  => '您必须登录后才能进行表态',
    'REACTIONS_JSON_ERROR'      => '加载表情出错',
    'REACTIONS_FALLBACK_INFO'   => '无法访问 JSON 文件。仅提供常用表情。',

    // =============================================================================
    // TOOLTIPS AND CONTEXTUAL HELP
    // =============================================================================
    'REACTIONS_ADD_TOOLTIP'     => '添加表态',
    'REACTIONS_MORE_TOOLTIP'    => '更多表情',
    'REACTIONS_COUNT_TOOLTIP'   => '%d 个表态',
    'REACTIONS_BUTTON_TEXT'     => '表个态',
    'REACTIONS_COUNT_TITLE'     => '%d 个表态',
    'REACTIONS_COUNT_TITLE_PLURAL' => '%d 个表态',

    // =============================================================================
    // TECHNICAL AND DEBUG MESSAGES
    // =============================================================================
    'REACTIONS_DEBUG_ENABLED'   => '表态调试模式已启用',
    'REACTIONS_CSRF_ERROR'      => '无效的 CSRF 令牌',
    'REACTIONS_SERVER_ERROR'    => '表态时发生服务器错误',

    // =============================================================================
    // LIMITS AND RESTRICTIONS
    // =============================================================================
    'REACTIONS_LIMIT_POST'      => '每帖最多允许 %d 种表态类型',
    'REACTIONS_LIMIT_USER'      => '每人每帖最多允许 %d 种表态类型',
    'REACTION_LIMIT_POST'       => '已达到该帖子的表态类型上限',
    'REACTION_LIMIT_USER'       => '已达到个人表态上限',
    'REACTIONS_LIMIT_REACHED'   => '已达到表态上限',

    'NO_SUBJECT'                => '（无主题）',

    // =============================================================================
    // CRON TASKS (ACP & CLI)
    // =============================================================================
    // Keys for the command line (CLI) - CRUCIAL for `cron:list`
    'TASK_BASTIEN59960_REACTIONS_NOTIFICATION'   => '表态功能：发送邮件摘要',
    'TASK_BASTIEN59960_REACTIONS_TEST'           => '表态功能：记录测试任务',

    // Keys for the ACP display
    'BASTIEN59960_REACTIONS_TEST'              => '表态功能：系统测试',
    'BASTIEN59960_REACTIONS_TEST_EXPLAIN'      => '定期测试以验证表态扩展（Reactions）的计划任务系统是否正常运行。',
    'BASTIEN59960_REACTIONS_NOTIFICATION'          => '表态功能：发送邮件摘要',
    'BASTIEN59960_REACTIONS_NOTIFICATION_EXPLAIN' => '整合新的表态记录并定期向用户发送邮件摘要。',
    'LOG_REACTIONS_CRON_TEST_RUN'                   => '<strong>表态计划任务测试运行</strong><br>» 表态扩展的测试任务已成功运行。',

    // =============================================================================
    // NOTIFICATION KEYS (from reactions.php)
    // =============================================================================
    'NOTIFICATION_GROUP_REACTIONS' => '表态通知',
    'NOTIFICATION_TYPE_REACTION'       => '<strong>%1$s</strong> 对你的帖子表达了 %2$s。',
    'NOTIFICATION_TYPE_REACTION_TITLE' => '帖子的即时表态通知',
    'NOTIFICATION_TYPE_REACTION_DESC'  => '当有用户对你的帖子进行表态时，在论坛通知铃铛处接收即时提醒。',
    'NOTIFICATION_REACTION_EMAIL_DIGEST_TITLE' => '表态邮件定期摘要',
    'NOTIFICATION_REACTION_EMAIL_DIGEST_DESC'  => '定期接收有关你帖子新表态情况的邮件汇总。',

]);
