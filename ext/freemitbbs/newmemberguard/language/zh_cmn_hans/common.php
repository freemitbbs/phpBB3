<?php

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'NEWMEMBERGUARD_NEW_MEMBER' => '新会员帖子',
	'NEWMEMBERGUARD_OFFTOPIC_WARNING' => '这篇帖子与它当前所在的主题几乎没有关联，可能被发到了错误的主题。',
	'NEWMEMBERGUARD_SUGGESTED' => '移动到建议主题',
	'NEWMEMBERGUARD_OR_TOPIC_ID' => '或输入主题 ID',
	'NEWMEMBERGUARD_MOVE' => '移动帖子',
	'NEWMEMBERGUARD_TOPIC_LABEL' => '%1$s — %2$s',
	'NEWMEMBERGUARD_INVALID_TARGET' => '选择的目标主题无效。',
]);
