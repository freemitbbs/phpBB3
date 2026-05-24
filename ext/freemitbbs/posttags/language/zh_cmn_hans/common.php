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
	'POSTTAGS_LABEL' => '标签/tags',
	'POSTTAGS_PLACEHOLDER' => '输入标签后按空格',
	'POSTTAGS_REMOVE' => '删除标签',
	'POSTTAGS_SEARCH_TITLE' => '标签 #%1$s 下的帖子',
	'POSTTAGS_SEARCH_HEADING' => '标签搜索',
	'POSTTAGS_NO_RESULTS' => '没有可见帖子使用这个标签。',
	'POSTTAGS_RESULT_IN' => '位于',
]);
