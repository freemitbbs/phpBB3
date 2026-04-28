<?php
/**
 * Fichier : reactions.php
 * Chemin : bastien59960/reactions/language/en/reactions.php
 * Auteur : Bastien (bastien59960)
 * GitHub : https://github.com/bastien59960/reactions
 *
 * Rôle :
 * Ce fichier centralise toutes les chaînes de langue anglaises pour les
 * notifications de l'extension Reactions (notifications "cloche" et
 * descriptions dans l'UCP).
 *
 * @copyright (c) 2025 Bastien59960
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined('IN_PHPBB')) {
    exit;
}

$lang = array_merge($lang ?? [], [
    // --- Notification Group (UCP) ---
    'NOTIFICATION_GROUP_REACTIONS' => '表态提醒',

    // --- Instant Notification (bell & UCP) ---
    'NOTIFICATION_TYPE_REACTION'       => '<strong>%1$s</strong> 对你的帖子表达了 %2$s。', // Text displayed in the bell
    'NOTIFICATION_TYPE_REACTION_TITLE' => '帖子的即时表态通知', // Title in the UCP
    'NOTIFICATION_TYPE_REACTION_DESC'  => '当有用户对你的帖子进行表态时，在论坛通知铃铛处接收即时提醒。',

    // --- Email Summary (UCP) ---
    'NOTIFICATION_REACTION_EMAIL_DIGEST_TITLE' => '表态邮件定期摘要',
    'NOTIFICATION_REACTION_EMAIL_DIGEST_DESC'  => '定期接收有关你帖子新表态情况的邮件汇总。',

]);
