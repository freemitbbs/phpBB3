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
	'FAVORITE_FORUMS'	=> '我收藏的版块',
	'ADD_FAVORITE'		=> '添加到收藏',
	'REMOVE_FAVORITE'	=> '从收藏中移除',
]);
