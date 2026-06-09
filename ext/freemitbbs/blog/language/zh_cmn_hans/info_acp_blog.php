<?php

/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang, [
	'ACP_BLOG' => '未名博客',
	'ACP_BLOG_GRP' => '未名博客',
	'BLOG_ACP_EXPLAIN' => '配置首页博客展示。',
	'BLOG_ACP_INDEX_FIELDSET' => '论坛首页',
	'BLOG_INDEX_LATEST_LIMIT' => '最新博客文章条数',
	'BLOG_INDEX_LATEST_LIMIT_EXPLAIN' => '首页博客框最多显示多少篇博客文章。设为 0 则隐藏该框。默认 10。',
	'BLOG_INDEX_LATEST_DAYS' => '最新博客文章最大天数',
	'BLOG_INDEX_LATEST_DAYS_EXPLAIN' => '只显示多少天以内的博客文章。设为 0 则不限制时间。默认 0。',
]);
