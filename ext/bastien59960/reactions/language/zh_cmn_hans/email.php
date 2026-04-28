<?php
/**
 * Fichier : email.php
 * Chemin : bastien59960/reactions/language/en/email.php
 * Auteur : Bastien (bastien59960)
 * GitHub : https://github.com/bastien59960/reactions
 *
 * Rôle :
 * Contient les chaînes de langue anglaises pour les e-mails envoyés par
 * l'extension. Ce fichier est utilisé par la tâche cron pour construire
 * l'e-mail de résumé des réactions.
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
    // =========================================================================
    // EMAIL SUBJECT
    // =========================================================================
    'REACTIONS_DIGEST_SUBJECT' => '您的帖子有了新的表态',

    // =========================================================================
    // HEADER AND INTRODUCTION
    // =========================================================================
    'REACTIONS_DIGEST_HELLO' => '%1$s 您好，',
    'REACTIONS_DIGEST_INTRO' => '以下是您在“%1$s”论坛中帖子收到新表态的汇总摘要：',

    // =========================================================================
    // POSTS CONTENT
    // =========================================================================
    'REACTIONS_DIGEST_POST_TITLE' => '帖子“%1$s”收到的表态',

    // =========================================================================
    // REACTION LABELS
    // =========================================================================
    'REACTIONS_DIGEST_REACTION_FROM' => '来自',
    'REACTIONS_DIGEST_ON_DATE'       => '于',
    'REACTIONS_DIGEST_VIEW_POST'     => '查看帖子',

    // =========================================================================
    // FOOTER AND SIGNATURE
    // =========================================================================
    'REACTIONS_DIGEST_SIGNATURE' => "祝好，\n%s 团队", // %s is replaced by the forum name
    'REACTIONS_DIGEST_FOOTER'           => '您收到这封邮件是因为您选择了接收表态汇总。',
    'REACTIONS_DIGEST_UNSUBSCRIBE'      => '如需取消表态邮件通知：',
    'REACTIONS_DIGEST_UNSUBSCRIBE_LINK' => '退订',
));
