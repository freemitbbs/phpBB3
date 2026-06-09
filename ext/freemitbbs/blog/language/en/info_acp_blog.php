<?php

/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang, [
	'ACP_BLOG' => 'Blogs',
	'ACP_BLOG_GRP' => 'Blogs',
	'BLOG_ACP_EXPLAIN' => 'Configure front-page blog presentation.',
	'BLOG_ACP_INDEX_FIELDSET' => 'Board index',
	'BLOG_INDEX_LATEST_LIMIT' => 'Latest blog posts count',
	'BLOG_INDEX_LATEST_LIMIT_EXPLAIN' => 'Maximum number of blog posts shown in the board index box. Set to 0 to hide the box. Default is 10.',
	'BLOG_INDEX_LATEST_DAYS' => 'Latest blog posts max age',
	'BLOG_INDEX_LATEST_DAYS_EXPLAIN' => 'Only show blog posts newer than this many days. Set to 0 for no age cutoff. Default is 0.',
]);
