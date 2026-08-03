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
	'NEWSSCRAPER_INDEX_COLLAPSE_HIDE' => 'Hide news digest',
	'NEWSSCRAPER_INDEX_COLLAPSE_SHOW' => 'Show news digest',
	'NEWSSCRAPER_UCP_SHOW_INDEX_DIGEST' => 'Show news digest on the front page',
	'NEWSSCRAPER_UCP_SHOW_INDEX_DIGEST_EXPLAIN' => 'Display the news digest block on the board index.',
	'NEWSSCRAPER_DISCUSS_BUTTON' => '选版面讨论此新闻',
	'NEWSSCRAPER_DISCUSS_FORUM' => '选择版面',
	'NEWSSCRAPER_DISCUSS_PREFILLED' => 'The article digest has been quoted into the editor.',
	'NEWSSCRAPER_ORIGINAL_NEWS_LINK' => '新闻链接：',
	'NEWSSCRAPER_VIEW_DISCUSSION' => '查看讨论',
	'LOG_NEWSSCRAPER_FAILED' => '<strong>News scraper failed to digest article</strong><br />Source: %1$s; URL: %2$s; Error: %3$s',
	'LOG_NEWSSCRAPER_SOURCE_FAILED' => '<strong>News scraper failed to fetch source</strong><br />Source: %1$s; Error: %2$s',
]);
