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
	'ACP_HOTFORUMS_GRP' => 'Hot Forums',
	'ACP_HOTFORUMS' => 'Hot Forums',
	'HOTFORUMS_EXPLAIN' => 'Configure the index page block for most-viewed forums.',
	'HOTFORUMS_FIELDSET_SETTINGS' => 'Settings',
	'HOTFORUMS_INDEX_LIMIT' => 'Index list size',
	'HOTFORUMS_INDEX_LIMIT_EXPLAIN' => 'Maximum number of hot forums to display on top of the index page.',
	'HOTFORUMS_VIEWERSHIP_CACHE_SECONDS' => 'Viewership ordering cache seconds',
	'HOTFORUMS_VIEWERSHIP_CACHE_SECONDS_EXPLAIN' => 'How long to cache forum view totals used by Hot Forums and TopTopics category menus. Set to 0 to disable the persistent cache.',
]);
