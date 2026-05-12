<?php
/**
 *
 * This file is part of the phpBB Forum Software package.
 * @简体中文语言　David Yin <https://www.phpbbchinese.com/>
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * For full copyright and license information, please see
 * the docs/CREDITS.txt file.
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

$lang = array_merge($lang, array(
	'HELP_FAQ_BLOCK_ABOUT' => '关于本站',
	'HELP_FAQ_BLOCK_ACCOUNT_POSTING' => '帐号和发帖',
	'HELP_FAQ_BLOCK_DISCOVERY' => '话题发现和可见性',
	'HELP_FAQ_BLOCK_FEEDBACK' => '赞、踩和互动',
	'HELP_FAQ_BLOCK_REPUTATION' => '声望',

	'HELP_FAQ_ABOUT_SITE_QUESTION' => '自由未名空间站是什么？',
	'HELP_FAQ_ABOUT_SITE_ANSWER' => '自由未名空间站（自由买买提）是一个面向全球用户的中文论坛。本站希望保留早期买买提式的公开讨论空间：表达可以更自由，观点可以碰撞，信息可以被质疑。完整说明见 <a href="/app.php/about">关于本站</a>。',
	'HELP_FAQ_ABOUT_VALUES_QUESTION' => '本站鼓励什么样的讨论？',
	'HELP_FAQ_ABOUT_VALUES_ANSWER' => '本站鼓励表达、讨论和多样性。观点可以对立，立场可以不同，公开表达也意味着接受公开讨论。讨论本身比统一结论更重要。',
	'HELP_FAQ_ABOUT_MANAGEMENT_QUESTION' => '本站如何管理内容？',
	'HELP_FAQ_ABOUT_MANAGEMENT_ANSWER' => '本站采用最小化管理原则。站方主要维护网站运行，不主导讨论方向，也不裁判观点对错。正常情况下，内容由用户之间的讨论、赞、踩和声望信号共同影响其展示位置。',
	'HELP_FAQ_ABOUT_BOUNDARIES_QUESTION' => '本站的底线是什么？',
	'HELP_FAQ_ABOUT_BOUNDARIES_ANSWER' => '本站不是无规则空间。违反法律的内容、明显破坏版面秩序的行为（例如刷屏、恶意干扰）会被处理。除此之外，本站尽量减少人为干预。',

	'HELP_FAQ_ACCOUNT_READ_POST_QUESTION' => '不注册可以浏览吗？',
	'HELP_FAQ_ACCOUNT_READ_POST_ANSWER' => '公开版面的内容通常可以直接浏览。发帖、回复、赞、踩、查看个人记录、使用站内消息和修改个人设置等功能需要登录帐号。',
	'HELP_FAQ_ACCOUNT_POSTING_QUESTION' => '如何发主题或回复？',
	'HELP_FAQ_ACCOUNT_POSTING_ANSWER' => '进入相应版面后点击“发表主题”发新主题；进入主题后点击“回复”参与讨论。请尽量把内容发到合适版面，并给主题使用清楚的标题。',
	'HELP_FAQ_ACCOUNT_FORMATTING_QUESTION' => '发帖可以使用哪些格式？',
	'HELP_FAQ_ACCOUNT_FORMATTING_ANSWER' => '帖子支持常见的 BBCode、链接、图片和媒体嵌入。是否可以上传附件取决于当前版面和帐号权限。HTML 不会作为网页代码执行。',
	'HELP_FAQ_ACCOUNT_SETTINGS_QUESTION' => '如何修改帐号设置？',
	'HELP_FAQ_ACCOUNT_SETTINGS_ANSWER' => '登录后进入“用户控制面板”，可以修改密码、邮箱、头像、签名、语言、时区、通知和隐私相关设置。遇到登录问题时，可以先尝试重设密码或删除本站 cookie。',

	'HELP_FAQ_DISCOVERY_TOP_RECENT_QUESTION' => '热门话题和最近话题有什么区别？',
	'HELP_FAQ_DISCOVERY_TOP_RECENT_ANSWER' => '“热门话题”按时间衰减得分排序，综合考虑赞、踩、回复、浏览、举报惩罚和讨论质量等信号。“最近话题”按最近活动展示。两者都会遵守你的阅读权限和首页隐藏版面设置。',
	'HELP_FAQ_DISCOVERY_FOE_LIST_QUESTION' => '黑名单会影响话题列表吗？',
	'HELP_FAQ_DISCOVERY_FOE_LIST_ANSWER' => '登录后，黑名单用户发起的主题不会出现在首页的热门话题和最近话题列表中。这只影响你的个人列表；如果你仍有阅读权限，仍然可以直接进入版面或主题查看。',
	'HELP_FAQ_DISCOVERY_DISLIKED_POSTS_QUESTION' => '被很多人踩的帖子会怎样？',
	'HELP_FAQ_DISCOVERY_DISLIKED_POSTS_ANSWER' => '帖子可见性使用净踩计算：TopTopics 踩数减去 Post Love 赞数。净踩为 0 或 1 时文字保持正常；净踩大于 1 后，文字会随着净踩增加逐级变淡。达到折叠阈值后，该帖子会在主题页折叠，读者仍可手动展开。',
	'HELP_FAQ_DISCOVERY_MAIN_POST_QUESTION' => '如果被踩的是主题主贴，会怎样？',
	'HELP_FAQ_DISCOVERY_MAIN_POST_ANSWER' => '如果主题主贴的净踩达到折叠阈值，整个主题会从首页热门话题和最近话题列表中移除。回复达到阈值时只影响该回复本身，不会因为某个回复被折叠就移除整个主题。',
	'HELP_FAQ_DISCOVERY_PREFERENCES_QUESTION' => '我可以控制首页话题列表吗？',
	'HELP_FAQ_DISCOVERY_PREFERENCES_ANSWER' => '可以。用户控制面板中可以设置是否在版面页显示热门话题、哪些版面不出现在首页话题列表中，以及手机端是否显示回复数和浏览数。',

	'HELP_FAQ_FEEDBACK_LIKES_DISLIKES_QUESTION' => '赞和踩分别表示什么？',
	'HELP_FAQ_FEEDBACK_LIKES_DISLIKES_ANSWER' => '“赞”表示你认为帖子有价值，会帮助帖子和作者获得正向信号。“踩”表示你认为帖子不值得被突出展示，会降低帖子和作者的信号。同一篇帖子不能同时赞和踩；如果已经点了其中一个，需要先取消再点另一个。踩按钮显示原始踩数，但折叠和列表移除使用“踩数 - 赞数”。',
	'HELP_FAQ_FEEDBACK_REACTIONS_QUESTION' => '表情互动会影响踩的阈值吗？',
	'HELP_FAQ_FEEDBACK_REACTIONS_ANSWER' => '不会。表情互动和其他 reactions 不是 TopTopics 的赞/踩。它们可以作为普通互动信号参与热门话题排序，但对帖子变淡、折叠、以及主题从热门/最近列表移除没有影响。',
	'HELP_FAQ_FEEDBACK_CANNOT_DISLIKE_QUESTION' => '为什么我不能踩某个帖子？',
	'HELP_FAQ_FEEDBACK_CANNOT_DISLIKE_ANSWER' => '常见原因包括：未登录、正在尝试踩自己的帖子、发帖数不足、声望不足、短时间内踩太多、当天踩太多，或者你已经赞过这篇帖子。按钮提示或错误消息会显示具体原因。',
	'HELP_FAQ_FEEDBACK_RECORDS_QUESTION' => '在哪里查看我收到的赞和踩？',
	'HELP_FAQ_FEEDBACK_RECORDS_ANSWER' => '登录后可以在用户控制面板查看“收到的赞/踩”记录。该页面只显示你有权限阅读的版面中的记录。',

	'HELP_FAQ_REPUTATION_WHAT_QUESTION' => '什么是声望？',
	'HELP_FAQ_REPUTATION_WHAT_ANSWER' => '声望是本站根据你已经通过可见性规则的公开发言和社区反馈计算出的用户信号。它会显示在帖子侧栏和个人资料中，用来帮助读者判断帐号长期贡献情况。',
	'HELP_FAQ_REPUTATION_BUILD_QUESTION' => '声望如何变化？',
	'HELP_FAQ_REPUTATION_BUILD_ANSWER' => '有内容、有帮助的可见帖子会提高声望；收到赞会提高声望；收到踩和未处理举报会降低声望。系统会忽略引用、图片、链接和标记噪音，主要计算帖子中真正的文字内容质量。',
	'HELP_FAQ_REPUTATION_GATES_QUESTION' => '声望会限制哪些操作？',
	'HELP_FAQ_REPUTATION_GATES_ANSWER' => '声望会影响一些负向操作，例如踩帖子和举报帖子。这样可以降低新帐号或低质量帐号滥用这些功能的风险。达到要求后，相关按钮会自动可用。',
));
