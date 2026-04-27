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
	'ACP_HOTFORUMS_GRP' => '热门版块',
	'ACP_HOTFORUMS' => '热门版块',
	'HOTFORUMS_EXPLAIN' => '配置首页顶部“热门版块”区块。',
	'HOTFORUMS_FIELDSET_SETTINGS' => '设置',
	'HOTFORUMS_INDEX_LIMIT' => '首页显示数量',
	'HOTFORUMS_INDEX_LIMIT_EXPLAIN' => '首页顶部最多显示多少个浏览量最高的版块。',
	'HOTFORUMS_VIEWERSHIP_CACHE_SECONDS' => '浏览排序缓存秒数',
	'HOTFORUMS_VIEWERSHIP_CACHE_SECONDS_EXPLAIN' => '热门版块和热门话题分类菜单使用的版块浏览量排序缓存时间。设为 0 可禁用持久缓存。',
]);
