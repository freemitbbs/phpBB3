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
	'NEWMEMBERGUARD_NEW_MEMBER' => 'New member post',
	'NEWMEMBERGUARD_OFFTOPIC_WARNING' => 'This post has almost nothing in common with the topic it is currently in. It may have been filed into the wrong topic.',
	'NEWMEMBERGUARD_SUGGESTED' => 'Move to suggested topic',
	'NEWMEMBERGUARD_OR_TOPIC_ID' => 'or enter a topic ID',
	'NEWMEMBERGUARD_MOVE' => 'Move post',
	'NEWMEMBERGUARD_TOPIC_LABEL' => '%1$s — %2$s',
	'NEWMEMBERGUARD_INVALID_TARGET' => 'The selected target topic is invalid.',
]);
