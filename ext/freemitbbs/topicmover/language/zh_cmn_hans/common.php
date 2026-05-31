<?php

/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang, [
	'LOG_TOPICMOVER_MOVED' => '<strong>主题自动移动已移动主题</strong><br />主题 ID：%1$s，从版面 ID %2$s 移动到版面 ID %3$s。原因：%4$s',
	'LOG_TOPICMOVER_FAILED' => '<strong>主题自动移动失败</strong><br />主题 ID：%1$s。错误：%2$s',
	'TOPICMOVER_UCP_NO_MOVE' => '不要自动移动我的主题',
	'TOPICMOVER_UCP_NO_MOVE_EXPLAIN' => '开启后，主题自动移动会保留您发起的主题在原版面。',
]);
