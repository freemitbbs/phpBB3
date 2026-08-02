<?php

/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang, [
	'LOG_TOPICMOVER_AUTHOR_MOVED' => '<strong>主题作者移动了主题</strong><br />从“%1$s”移动到“%2$s”（版面 ID %3$d 至 %4$d）',
	'TOPICMOVER_AUTHOR_MOVE_BUTTON' => '移动主题',
	'TOPICMOVER_AUTHOR_MOVE_TITLE' => '移动自己的主题',
	'TOPICMOVER_AUTHOR_MOVE_EXPLAIN' => '您是该主题的作者，可以把主题移动到您有发帖权限的其它版面。',
	'TOPICMOVER_AUTHOR_MOVE_CURRENT_FORUM' => '当前版面',
	'TOPICMOVER_AUTHOR_MOVE_DESTINATION' => '目标版面',
	'TOPICMOVER_AUTHOR_MOVE_CHOOSE_FORUM' => '请选择版面',
	'TOPICMOVER_AUTHOR_MOVE_NO_DESTINATIONS' => '目前没有可用的目标版面。',
	'TOPICMOVER_AUTHOR_MOVE_WARNING' => '整个主题及其所有回复会立即移动，当前版面不会保留跳转链接。',
	'TOPICMOVER_AUTHOR_MOVE_SUBMIT' => '移动主题',
	'TOPICMOVER_AUTHOR_MOVE_INVALID_DESTINATION' => '请选择一个可用的目标版面。',
	'TOPICMOVER_AUTHOR_MOVE_FAILED' => '主题移动失败，请重试。',
]);
