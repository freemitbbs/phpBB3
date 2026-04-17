<?php
/**
*
* Precise Similar Topics [Simplified Chinese]
*
* @copyright (c) 2013 Matt Friedman
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ » “ ” …
//

$lang = array_merge($lang, array(
	'PST_TITLE_ACP'			=> '精准相似主题',
	'PST_EXPLAIN'			=> '精准相似主题会显示一组相似（相关）主题。它既可以显示在当前主题讨论页底部，也可以在用户撰写新主题标题时动态显示。',
	'PST_LEGEND1'			=> '常规设置',
	'PST_ENABLE'			=> '显示相似主题',
	'PST_ENABLE_EXPLAIN'	=> '在主题讨论页面中显示相似主题。',
	'PST_LEGEND2'			=> '加载设置',
	'PST_LIMIT'				=> '显示的相似主题数量',
	'PST_LIMIT_EXPLAIN'		=> '在这里设置要显示多少个相似主题。默认值为 5 个主题。',
	'PST_TIME'				=> '搜索时间范围',
	'PST_TIME_EXPLAIN'		=> '此选项可将相似主题结果限制为较新的主题，以避免把老帖重新顶起。例如，若设为“30 天”，系统只会显示最近 30 天内的相似主题。默认值为 1 年。如需基本禁用此功能，可设为 99 年。',
	'PST_YEARS'				=> '年',
	'PST_MONTHS'			=> '月',
	'PST_WEEKS'				=> '周',
	'PST_DAYS'				=> '天',
	'PST_CACHE'				=> '相似主题缓存时长',
	'PST_CACHE_EXPLAIN'		=> '相似主题缓存将在此时间后过期，单位为秒。如果要禁用相似主题缓存，请设为 0。',
	'PST_DYNAMIC'			=> '显示动态相似主题',
	'PST_DYNAMIC_EXPLAIN'	=> '在创建新主题时，随着用户输入标题动态显示相似主题。',
	'PST_SENSE'				=> '搜索敏感度',
	'PST_SENSE_EXPLAIN'		=> '对于 MySQL 或 Postgres 数据库，可将搜索敏感度设置为 1 到 10 之间的值。如果没有显示任何相似主题，请使用更低的数值。推荐设置：%d',
	'PST_LEGEND3'			=> '版面设置',
	'PST_NOSHOW_LIST'		=> '不显示于',
	'PST_NOSHOW_TITLE'		=> '不要在以下版面显示相似主题',
	'PST_IGNORE_SEARCH'		=> '不搜索于',
	'PST_IGNORE_TITLE'		=> '不要在以下版面搜索相似主题',
	'PST_STANDARD'			=> '标准',
	'PST_ADVANCED'			=> '高级',
	'PST_ADVANCED_TITLE'	=> '点击为以下版面设置高级相似主题选项',
	'PST_ADVANCED_EXP'		=> '在这里可以选择从哪些特定版面提取相似主题。只有在这里选中的版面中找到的相似主题，才会显示在 <strong>%s</strong> 中。<br><br>如果希望此版面显示来自所有可搜索版面的相似主题，请不要选择任何版面。<br><br>按住 <samp>CTRL</samp>（Mac 上为 <samp>&#8984;CMD</samp>）并点击，可一次选择多个版面。',
	'PST_ADVANCED_FORUM'	=> '高级版面设置',
	'PST_DESELECT_ALL'		=> '全部取消选择',
	'PST_LEGEND4'			=> '可选设置',
	'PST_WORDS'				=> '要忽略的特殊词',
	'PST_WORDS_EXPLAIN'		=> '添加论坛中特有、在查找相似主题时应被忽略的词语。（注意：当前语言中被视为常见词的内容，默认已经被忽略。）每个词之间请用空格分隔。不区分大小写。',
	'PST_SAVED'				=> '精准相似主题设置已更新',
	'PST_FORUM_INFO'		=> '“不显示于”：不会在所选版面中显示相似主题。<br>“不搜索于”：不会在所选版面中搜索相似主题。',
	'PST_NO_COMPAT'			=> '精准相似主题与您的论坛不兼容。',
	'PST_ERR_CONFIG'		=> '在版面列表中勾选的版面过多。请缩小选择范围后重试。',
));
