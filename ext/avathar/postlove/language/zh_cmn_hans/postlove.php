<?php
/**
 * Post Love extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2015 Stanislav Atanasov
 * @copyright (c) 2026 Avathar.be
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined('IN_PHPBB'))
{
	exit;
}
if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'POSTLOVE_USER_LIKES'   => '赞',
	'POSTLOVE_USER_LIKED'   => '被赞了',

	'NOTIFICATION_POSTLOVE_ADD'     => '%s <b>点赞了</b>你的贴:',
	'NOTIFICATION_TYPE_POST_LOVE'   => '被点赞的贴.',

	// Ver 1.1
	'LIKE_LINE'     => '%1$s - %2$s<b>赞了</b>%3$s\的贴: "%4$s" ("%5$s")',
	'POSTLOVE_LIST' => '赞',
	'POSTLOVE_LIST_VIEW'    => '显示所有点赞记录列表',

	// Ver 2.0
	'CLICK_TO_LIKE'         => '点赞',
	'CLICK_TO_UNLIKE'       => '取消点赞',
	'LOGIN_TO_LIKE_POST'    => '请登录后点赞',
	'CANT_LIKE_OWN_POST'    => '抱歉，你不能给自己的帖子点赞',
	'POSTLOVE_REMOVE_DISLIKE_FIRST' => '请先取消踩',
	'POST_OF_THE_DAY'       => '今日最赞贴',
	'POSTLOVE_MOST_LIKED_PAGE' => '最多获赞帖子',
	'POSTLOVE_MOST_LIKED_PAGE_EXPLAIN' => '按时间范围显示获赞最多的帖子。',
	'POSTLOVE_MOST_LIKED_TOTAL' => '累计获赞',
	'POSTLOVE_MOST_LIKED_THIS_YEAR' => '本年获赞',
	'POSTLOVE_MOST_LIKED_THIS_MONTH' => '本月获赞',
	'POSTLOVE_MOST_LIKED_THIS_WEEK' => '本周获赞',
	'POSTLOVE_MOST_LIKED_EMPTY' => '这个时间范围内还没有获赞帖子。',
	'POST_LIKES'            => '点赞数',
	'POSTED_AT'             => '发布于',
	'LIKED_BY'              => '点赞者',
	'POSTED_BY'             => '作者',
	'LIKES_TODAY'           => array(
		0       => '今日 %d 次',
	),
	'LIKES_THIS_WEEK'       => array(
		0       => '本周 %d 次',
	),
	'LIKES_THIS_MONTH'       => array(
		0       => '本月 %d 次',
	),
	'LIKES_THIS_YEAR'       => array(
		0       => '本年 %d 次',
	),
	'LIKES_EVER'       => array(
		0       => '累计 %d 次',
	),
	'POSTLOVE_SHOW_SUMMARY' => '显示最受欢迎帖子版块',
	'POSTLOVE_SHOW_SUMMARY_EXPLAIN' => '允许在首页和版面页面显示最受欢迎帖子摘要。',
	'POSTLOVE_SHOW_FORUM_SUMMARY' => '在版面页面显示最受欢迎帖子版块',
	'POSTLOVE_SHOW_FORUM_SUMMARY_EXPLAIN' => '允许在单个版面页面显示最受欢迎帖子摘要。',
	'POSTLOVE_HIDE'                 => '隐藏点赞图标与统计',
	'ACL_U_POSTLOVE'                => '点赞功能：可以点赞帖子',
	'ACL_U_POSTLOVE_SUMMARY'        => '点赞功能：可以查看最受欢迎帖子统计',
));
