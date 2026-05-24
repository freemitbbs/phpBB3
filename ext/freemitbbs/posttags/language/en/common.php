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
	'POSTTAGS_LABEL' => 'Tags',
	'POSTTAGS_PLACEHOLDER' => 'Type a tag and press space',
	'POSTTAGS_REMOVE' => 'Remove tag',
	'POSTTAGS_SEARCH_TITLE' => 'Posts tagged #%1$s',
	'POSTTAGS_SEARCH_HEADING' => 'Tag search',
	'POSTTAGS_NO_RESULTS' => 'No visible posts are tagged with this tag.',
	'POSTTAGS_RESULT_IN' => 'in',
]);
