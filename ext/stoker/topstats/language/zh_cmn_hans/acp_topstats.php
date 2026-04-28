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
        'TOP_STATS'                                     => '热门统计',
        'TS_CONFIG'                                     => '配置选项',

        'ACP_TOPSTATSRA_BADGE'                          => '最近活跃 - 热门统计 2.0.3',
        'ACP_TOPSTATSSB_BADGE'                          => '统计区块 - 热门统计 2.0.3',
        'ACP_TOPSTATSTP_BADGE'                          => '活跃用户 - 热门统计 2.0.3',
        'ACP_TOPSTATS_SETTINGS_EXPLAIN'                 => '如果您喜欢这个扩展，请考虑关注我们',
        'ACP_TOPSTATS_DONATION'                         => '进行捐赠',
        'ACP_TOPSTATS_MEMBER'                           => '成为我社区的活跃成员',
        'ACP_TOPSTATS_SUPPORT'                          => '扩展支持或反馈',

        'TOPSTATS_SAVED'                                => '热门统计设置已保存',
        'TS_RECENT_SETTINGS'                            => '最近活跃主题设置',
        'TSRAT_NUMBER'                                  => '最近活跃主题数量',
        'TSRAT_NUMBER_EXPLAIN'                          => '显示最近活跃主题的数量。<br>将值设置为 0 即可禁用此功能。上限为 50。',
        'TS_RECENT_LIMIT_RANGE'                         => '最近活跃主题请设置在 0 到 %d 之间的数值！',
        'TS_STATS_LIMIT_RANGE'                          => '热门统计项目请设置在 0 到 %d 之间的数值！',
        'TS_TOPPOSTER_LIMIT_RANGE'                      => '活跃用户项目请设置在 0 到 %d 之间的数值！',
        'TS_JSSCROLL'                                   => 'Jquery 滚动',
        'TS_JSSCROLL_EXPLAIN'                           => '启用或禁用最近活跃主题的 Jquery 滚动效果。',
        'TS_JSSCROLL_SPEED'                             => 'Jquery 滚动速度',
        'TS_JSSCROLL_SPEED_EXPLAIN'                     => '滚动的速度（以毫秒为单位，默认为 400）。',
        'TS_JSSCROLL_INTERVAL'                          => 'Jquery 滚动间隔',
        'TS_JSSCROLL_INTERVAL_EXPLAIN'                  => '两次滚动之间的时间（以毫秒为单位，默认为 4000）。',
        'TS_JSSCROLL_DIRECTION'                         => 'Jquery 滚动方向',
        'TS_JSSCROLL_DIRECTION_EXPLAIN'                 => 'Jquery 滚动的移动方向。',
        'TS_JSSCROLL_DIRECTION_DOWN'                    => '向下',
        'TS_JSSCROLL_DIRECTION_UP'                      => '向上',
        'TS_JSSCROLL_PAUSE'                             => 'Jquery 滚动暂停',
        'TS_JSSCROLL_PAUSE_EXPLAIN'                     => '启用后，鼠标悬停在最近活跃主题上时将暂停滚动。',
        'TS_JSSCROLL_NAVIGATION'                        => 'Jquery 滚动导航',
        'TS_JSSCROLL_NAVIGATION_EXPLAIN'                => '启用或禁用最近活跃主题的滚动导航按钮。',

	'TS_RECENT_CACHE_TIME'                          => '最近主题缓存时长',
        'TS_RECENT_CACHE_TIME_EXPLAIN'                  => '缓存最近活跃主题数据的时间。<br>使用缓存可以减轻数据库负担，但数据更新会略有延迟。<br>选择“已禁用”以实时显示。',
        'TS_RECENT_CACHE_INVALID'                       => '选择的缓存时间无效。请从下拉菜单中选择一个值。',
        'TS_CACHE_DISABLED'                             => '已禁用（无缓存，实时更新）',
        'TS_CACHE_1_MIN'                                => '1 分钟',
        'TS_CACHE_2_MIN'                                => '2 分钟',
        'TS_CACHE_3_MIN'                                => '3 分钟',
        'TS_CACHE_5_MIN'                                => '5 分钟（推荐）',
        'TS_CACHE_10_MIN'                               => '10 分钟',
        'TS_CACHE_15_MIN'                               => '15 分钟',
        'TS_CACHE_30_MIN'                               => '30 分钟',

	'DISPLAY_TOP_RECENT_INDEX'              => '在首页启用最近活跃主题',
        'DISPLAY_TOP_RECENT_INDEX_EXPLAIN'      => '启用或禁用在论坛首页显示最近活跃主题部分。',
        'DISPLAY_TOP_RECENT_PORTAL'             => '在传送门启用最近活跃主题',
        'DISPLAY_TOP_RECENT_PORTAL_EXPLAIN'     => '启用或禁用在 Simple Portal 页面显示最近活跃主题部分。',
        'TS_PORTAL_NOT_AVAILABLE'               => '此选项仅在安装并启用了 <a href="https://phpbb3bbcodes.com/viewtopic.php?t=2719" title="访问 PhpBB3 BBCodes 的 Simple Portal 主题" target="_blank" rel="noopener noreferrer">Simple Portal</a> 时可用。',
        'TS_TOPSTATS_SETTINGS'                  => '热门统计设置',
        'DISPLAY_TOP_STATS_INDEX'               => '在首页启用热门统计',
        'DISPLAY_TOP_STATS_INDEX_EXPLAIN'       => '启用或禁用在论坛首页显示热门统计部分。',
        'DISPLAY_TOP_STATS_PORTAL'              => '在传送门启用热门统计',
        'DISPLAY_TOP_STATS_PORTAL_EXPLAIN'      => '启用或禁用在 Simple Portal 页面显示热门统计部分。',

        'TS_MOSTVIEWED_NUMBER'                  => '最多查看主题',
        'TS_MOSTVIEWED_NUMBER_EXPLAIN'          => '显示最多查看主题的数量。<br>将值设置为 0 即可禁用此功能。上限为 50。<br>最多查看主题数据将缓存 24 小时。',
        'TS_MOSTREPLIED_NUMBER'                 => '最多回复主题',
        'TS_MOSTREPLIED_NUMBER_EXPLAIN'         => '显示最多回复主题的数量。<br>将值设置为 0 即可禁用此功能。上限为 50。<br>最多回复主题数据将缓存 24 小时。',
        'TS_MOSTACTIVEUSER_NUMBER'              => '最活跃用户',
        'TS_MOSTACTIVEUSER_NUMBER_EXPLAIN'      => '显示最活跃用户的数量。<br>将值设置为 0 即可禁用此功能。上限为 50。<br>最活跃用户数据将缓存 24 小时。',
        'TS_MOSTACTIVEFORUM_NUMBER'             => '最活跃版块',
        'TS_MOSTACTIVEFORUM_NUMBER_EXPLAIN'     => '显示最活跃版块的数量。<br>将值设置为 0 即可禁用此功能。上限为 50。<br>最活跃版块数据将缓存 24 小时。',
        'TS_LASTVISITEDBOT_NUMBER'              => '最近访问爬虫',
        'TS_LASTVISITEDBOT_NUMBER_EXPLAIN'      => '显示最近访问爬虫（机器人）的数量。<br>将值设置为 0 即可禁用此功能。上限为 50。<br>最近访问爬虫数据将缓存 5 分钟。',
        'TS_LASTREGISTEREDUSER_NUMBER'          => '最新注册用户',
        'TS_LASTREGISTEREDUSER_NUMBER_EXPLAIN'  => '显示最新注册用户的数量。<br>将值设置为 0 即可禁用此功能。上限为 50。<br>最新注册用户数据将缓存 5 分钟。',

	'TS_TOPSTATS_TP_EXCLUDE'                => '排除活跃用户',
        'TS_THISMONTH_TOP_NUMBER'               => '本月活跃用户',
        'TS_THISMONTH_TOP_NUMBER_EXPLAIN'       => '显示本月活跃用户的数量。<br>将值设置为 0 即可禁用此功能。上限为 50。<br>本月活跃用户的缓存由“活跃用户”页面处理。',
        'TS_LASTMONTH_TOP_NUMBER'               => '上月活跃用户',
        'TS_LASTMONTH_TOP_NUMBER_EXPLAIN'       => '显示上月活跃用户的数量。<br>将值设置为 0 即可禁用此功能。上限为 50。<br>上月活跃用户数据将缓存至下个月。',
        'TS_EXCLUDED_USERS'                     => '排除用户 ID',
        'TS_EXCLUDED_USERS_EXPLAIN'             => '输入要从活跃用户统计中排除的用户 ID，以逗号分隔。例如：23,67,890<br>（这些 ID 仅会从“本月活跃用户”和“上月活跃用户”中排除）<br>字符限制为 240 个。',
        'SUBMIT_EXCLUDED_USERS'                 => '保存排除的用户',
        'EXCLUDED_USERS_TOO_LONG'               => '排除用户列表过长。请保持在 %d 个字符以内。',
        'INVALID_EXCLUDED_USERS'                => '排除用户字段仅允许输入数字和逗号。',
        'EXCLUDED_USER_NOT_EXIST'               => '用户 ID %d 不存在。',

        'TS_TOPSTATS_EXCLUDE_FORUMS'            => '排除版块',
        'TS_EXCLUDED_FORUMS'                    => '排除版块 ID',
        'TS_EXCLUDED_FORUMS_EXPLAIN'            => '输入要从活跃用户统计中排除的版块 ID，以逗号分隔。来自这些版块的帖子将不计入排名。例如：5,12,23<br>（这些版块仅会从“本月活跃用户”和“上月活跃用户”中排除）<br>字符限制为 240 个。',
        'SUBMIT_EXCLUDED_FORUMS'                => '保存排除的版块',
        'EXCLUDED_FORUMS_TOO_LONG'              => '排除版块列表过长。请保持在 %d 个字符以内。',
        'INVALID_EXCLUDED_FORUMS'               => '排除版块字段仅允许输入数字和逗号。',
        'EXCLUDED_FORUM_NOT_EXIST'              => '版块 ID %d 不存在。',

	'TS_TOPSTATS_EXCLUDE_FORUMS'            => '排除版块',
        'TS_EXCLUDED_FORUMS'                    => '排除版块 ID',
        'TS_EXCLUDED_FORUMS_EXPLAIN'            => '输入要从活跃用户统计中排除的版块 ID，以逗号分隔。来自这些版块的帖子将不计入排名。例如：5,12,23<br>（注意：这些设置仅适用于“本月活跃用户”和“上月活跃用户”）<br>字符限制为 240 个。',
        'SUBMIT_EXCLUDED_FORUMS'                => '保存排除的版块',
        'EXCLUDED_FORUMS_TOO_LONG'              => '排除版块列表过长。请保持在 %d 个字符以内。',
        'INVALID_EXCLUDED_FORUMS'               => '排除版块字段仅允许输入数字和逗号。',
        'EXCLUDED_FORUM_NOT_EXIST'              => '版块 ID %d 不存在。',

        'TS_TOPPOSTER_CACHE_TIME'               => '活跃用户缓存时长',
        'TS_TOPPOSTER_CACHE_TIME_EXPLAIN'       => '设置本月活跃用户数据的缓存时间。<br><strong>小型社区（帖子少于 5 万）：</strong> 建议使用 1-2 小时以获得准实时排名。<br><strong>中型社区（帖子 5-20 万）：</strong> 建议使用 4-8 小时以平衡更新频率与性能。<br><strong>大型社区（帖子 20 万以上）：</strong> 建议使用“当天剩余时间”以获得最佳性能（将在午夜刷新）。<br>选择“已禁用”将实时显示（不推荐大型社区使用）。<br>上月活跃用户数据将固定缓存一年。',
        'TS_TOPPOSTER_CACHE_INVALID'            => '选择的缓存时长无效。请从下拉菜单中选择一个值。',
        'TS_TP_CACHE_DISABLED'                  => '已禁用（无缓存，实时更新）',
        'TS_TP_CACHE_1_HOUR'                    => '1 小时',
        'TS_TP_CACHE_2_HOURS'                   => '2 小时',
        'TS_TP_CACHE_3_HOURS'                   => '3 小时',
        'TS_TP_CACHE_4_HOURS'                   => '4 小时',
        'TS_TP_CACHE_8_HOURS'                   => '8 小时（推荐中型社区使用）',
        'TS_TP_CACHE_REST_OF_DAY'               => '当天剩余时间（推荐大型社区使用）',

        'TS_INDEX'                              => '论坛首页',
        'TS_PORTAL'                             => 'Simple Portal 传送门',
        'TS_CUSTOM'                             => '自定义页面',
        'TS_SUBMIT_CHANGES'                     => '提交更改',

        'DISPLAY_TOP_RECENT_CUSTOM'             => '在自定义页面启用最近活跃',
        'DISPLAY_TOP_RECENT_CUSTOM_EXPLAIN'     => '启用或禁用在自定义页面显示最近活跃部分。',
        'DISPLAY_TOP_STATS_CUSTOM'              => '在自定义页面启用热门统计',
        'DISPLAY_TOP_STATS_CUSTOM_EXPLAIN'      => '启用或禁用在自定义页面显示热门统计部分。',

        'ACP_TS_TOPPOSTER'                      => '活跃用户页面',
        'ACP_TS_TOPPOSTER_EXPLAIN'              => '此处的活跃用户设置仅影响自定义页面，但排除的用户 ID 是通用的。',
        'DISPLAY_TOP_STATS_TOPPOSTER'           => '启用活跃用户页面',
        'DISPLAY_TOP_STATS_TOPPOSTER_EXPLAIN'   => '向用户开放活跃用户自定义页面的访问权限。',

));
