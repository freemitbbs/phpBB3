<?php
/**
 * File: ucp_reactions.php
 * Path: bastien59960/reactions/language/en/ucp_reactions.php
 * Author: Bastien (bastien59960)
 * GitHub: https://github.com/bastien59960/reactions/blob/main/language/en/ucp_reactions.php
 *
 * Role:
 * This file contains the English language strings for the reaction preferences
 * page in the User Control Panel (UCP).
 *
 * @copyright (c) 2025 Bastien59960
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined('IN_PHPBB'))
{
    exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = array();
}

$lang = array_merge($lang, array(
    'UCP_REACTIONS_SETTINGS'        => '表态提醒设置',
    'UCP_REACTIONS_TITLE'           => '表态提醒设置',
    'UCP_REACTIONS_EXPLAIN'         => '选择当有成员对您的帖子进行表态时，您希望接收通知的方式。',
    'UCP_REACTIONS_NOTIFY'          => '接收新表态提醒（站内通知）',
    'UCP_REACTIONS_NOTIFY_EXPLAIN'  => '当有用户对您的帖子进行表态时，在论坛内接收即时通知。',
    'UCP_REACTIONS_CRON_EMAIL'      => '接收新表态提醒（电子邮件）',
    'UCP_REACTIONS_CRON_EMAIL_EXPLAIN' => '定期接收有关您帖子新表态情况的邮件汇总摘要。',
    'UCP_REACTIONS_SAVED'           => '您的表态提醒设置已保存。',
    'UCP_REACTIONS_CONTROLLER_NOT_FOUND' => '找不到 UCP 表态控制器。该扩展程序可能未正确安装。',

));
