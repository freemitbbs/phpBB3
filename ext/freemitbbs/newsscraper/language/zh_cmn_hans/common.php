<?php

/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang, [
	'NEWSSCRAPER_INDEX_TITLE' => '新闻摘要',
	'NEWSSCRAPER_INDEX_COLLAPSE_HIDE' => '隐藏新闻摘要',
	'NEWSSCRAPER_INDEX_COLLAPSE_SHOW' => '显示新闻摘要',
	'NEWSSCRAPER_DISCUSS_BUTTON' => '选版面讨论此新闻',
	'NEWSSCRAPER_DISCUSS_FORUM' => '选择版面',
	'NEWSSCRAPER_DISCUSS_PREFILLED' => '摘要内容已引用到编辑框。',
	'NEWSSCRAPER_ORIGINAL_NEWS_LINK' => '新闻链接：',
	'NEWSSCRAPER_VIEW_DISCUSSION' => '查看讨论',
	'LOG_NEWSSCRAPER_FAILED' => '<strong>新闻摘要生成失败</strong><br />来源：%1$s；URL：%2$s；错误：%3$s',
	'LOG_NEWSSCRAPER_SOURCE_FAILED' => '<strong>新闻源抓取失败</strong><br />来源：%1$s；错误：%2$s',
]);
