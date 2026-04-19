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
	$lang = [];
}

$lang = array_merge($lang, [
	'POSTLOVE_CONTROL' => '帖子点赞',
	'POSTLOVE_SHOW_LIKES' => '显示用户点赞过的帖子数',
	'POSTLOVE_SHOW_LIKES_EXPLAIN' => '在每个帖子的用户信息区域显示该用户点赞过的帖子总数。',
	'POSTLOVE_SHOW_LIKED' => '显示用户收到的获赞数',
	'POSTLOVE_SHOW_LIKED_EXPLAIN' => '在每个帖子的用户信息区域显示该用户发布的所有帖子收到的获赞总数。',

	// Version 1.1 langs
	'ACP_POSTLOVE_GRP' => '帖子点赞 (Post Love)',
	'ACP_POSTLOVE' => '帖子点赞',
	'POSTLOVE_EXPLAIN' => '您可以从这里修改 Post Love 的相关设置',
	'CONFIRM_MESSAGE' => '设置已保存！<br><br><a href="%1$s">返回</a>',

	'POSTLOVE_AUTHOR_LIKE' => '允许用户给自己的帖子点赞',
	'POSTLOVE_AUTHOR_LIKE_EXPLAIN' => '如果启用，用户可以给自己的帖子点赞。如果禁用，用户自己的帖子上将隐藏点赞按钮。',

	'POSTLOVE_CLEAN_LOVES' => '清理帖子点赞数据',
	'POSTLOVE_CLEAN_LOVES_EXPLAIN' => '如果您在自动清理功能完善前就安装了 Post Love，请点击“清理”按钮以清除冗余的重复数据。',
	'CLEAN' => '清理',

	// Version 2.0
	'POSTLOVE_FIELDSET_BEHAVIOUR' => '点赞行为设置',
	'POSTLOVE_FIELDSET_SUMMARY' => '最多获赞帖子统计',
	'POSTLOVE_SUMMARY_PERMISSION_NOTICE' => '此统计列表的可见性由“可以查看最多获赞帖子统计”用户权限控制。请前往 ACP &raquo; 权限进行配置。',
	'POSTLOVE_SUMMARY_POSITION' => '首页统计列表位置',
	'POSTLOVE_SUMMARY_POSITION_EXPLAIN' => '选择“最多获赞帖子统计”显示在版块列表的上方还是下方。',
	'POSTLOVE_SUMMARY_ABOVE' => '版块列表上方',
	'POSTLOVE_SUMMARY_BELOW' => '版块列表下方',
	'POSTLOVE_MOST_LIKED_PAGE_LENGTH' => '独立页面列表长度',
	'POSTLOVE_MOST_LIKED_PAGE_LENGTH_EXPLAIN' => '独立“最多获赞帖子”页面中每个分组显示的帖子数量。',
	'POSTLOVE_SUMMARY_PERIOD' => '统计周期',
	'POSTLOVE_HOWMANY_MOST_LIKED_DAY' => '显示多少条今日获赞帖子',
	'POSTLOVE_HOWMANY_MOST_LIKED_WEEK' => '显示多少条本周获赞帖子',
	'POSTLOVE_HOWMANY_MOST_LIKED_MONTH' => '显示多少条本月获赞帖子',
	'POSTLOVE_HOWMANY_MOST_LIKED_YEAR' => '显示多少条本年度获赞帖子',
	'POSTLOVE_HOWMANY_MOST_LIKED_EVER' => '显示多少条历史总计获赞帖子',
	'POSTLOVE_FORUM' => '在版块页面显示的数量',
	'POSTLOVE_INDEX' => '在首页显示的数量',
	'POSTLOVE_SHOW_BUTTON' => '在帖子操作栏显示点赞数？',
	'POSTLOVE_SHOW_BUTTON_EXPLAIN' => '如果启用，点赞数和操作链接将以按钮形式显示在操作栏（紧邻回复、引用等）。如果禁用，它们将显示在帖子内容下方。',

	'POSTLOVE_IMPORT_THANKS' => '可导入的“感谢”记录',
	'POSTLOVE_IMPORT_THANKS_EXPLAIN' => '可以从“Thanks for Posts”扩展导入感谢记录，此操作不会更改该扩展的原始数据。',
	'POSTLOVE_IMPORT_NO_THANKS_EXPLAIN' => '可以从“Thanks for Posts”扩展导入记录，但未发现合适的记录。',
	'IMPORT' => '导入',
]);
