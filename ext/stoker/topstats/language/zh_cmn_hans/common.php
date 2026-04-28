<?php
/**
*
* @package phpBB Extension - Top Stats
* @copyright (c) 2024 Stoker - https://www.phpbb3bbcodes.com
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
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
	'DECIMAL_TS'				=> '2',
	'DECIMAL_SEPARATOR_TS'		=> '.',
	'THOUSANDS_SEPARATOR_TS'	=> ',',
	
        'MOST_VIEWED'                           => '最多查看主题',
        'MOST_REPLIED'                          => '最多回复主题',
        'RECENT_ACTIVE'                         => '最近活跃主题',
        'MOST_ACTIVE_USERS'                     => '最活跃用户',
        'JOINED_US'                             => '加入我们',
        'MOST_ACTIVE_FORUMS'                    => '最活跃版块',
        'PREVIOUS_SCROLL'                       => '上一个',
        'NEXT_SCROLL'                           => '下一个',
        'START_SCROLL'                          => '开始滚动',
        'STOP_SCROLL'                           => '停止滚动',
        'LAST_REGISTERED_USERS'                 => '最新注册用户',
        'LAST_VISITED_BOTS'                     => '最近访问爬虫',
        'TOP_POSTERS_THIS_MONTH'                => '本月活跃排行：',
        'TOP_POSTERS_LAST_MONTH'                => '上月活跃排行：',
        'NO_DATA'                               => '暂无数据',
        'NO_TOP_POSTER'                         => '本月暂无活跃用户',

        'TS_MONTH_JANUARY'                      => '一月',
        'TS_MONTH_FEBRUARY'                     => '二月',
        'TS_MONTH_MARCH'                        => '三月',
        'TS_MONTH_APRIL'                        => '四月',
        'TS_MONTH_MAY'                          => '五月',
        'TS_MONTH_JUNE'                         => '六月',
        'TS_MONTH_JULY'                         => '七月',
        'TS_MONTH_AUGUST'                       => '八月',
        'TS_MONTH_SEPTEMBER'                    => '九月',
        'TS_MONTH_OCTOBER'                      => '十月',
        'TS_MONTH_NOVEMBER'                     => '十一月',
        'TS_MONTH_DECEMBER'                     => '十二月',

        'TOP_STATS_PAGE_TITLE'          => '热门统计页面',
        'TOP_STATS_COPY'                => 'phpBB 热门统计',
        'TM_TOP_POSTERS'                => '本月活跃排行',
        'LM_TOP_POSTERS'                => '上月活跃排行',
        'TS_TOP_POSTERS'                => '活跃用户排行',
        'TS_TOP_POSTERSFOR'             => '活跃用户排行：',
        'TS_TOP_COPY'                   => 'phpBB 活跃用户',
        'TS_TOP_STATS'                  => '热门统计',
        'VIEWING_TOP_POSTERS'           => '正在查看活跃用户排行',
        'VIEWING_TOP_STATS'             => '正在查看热门统计',
        'TOPPOSTERS_DISABLED'           => '活跃用户页面目前已禁用',
        'TOPSTATS_DISABLED'             => '热门统计页面目前已禁用',
        'TS_TOP_POSTERS_FOR'            => '%1$s 活跃用户排行',

        'TS_REQUIRE_PHPBB'              => '此扩展需要 phpBB %1$s 或更高版本。您当前的版本为 %2$s。',
        'TS_REQUIRE_PHP'                => '此扩展需要 PHP %1$s 或更高版本。您当前的服务器版本为 %2$s。',
        'TS_REQUIRE_REMOVE'             => '在安装 Top Stats & Top Posters 2.0.0 或更高版本之前，请完整卸载之前的旧版 Top Stats。',
	
));
