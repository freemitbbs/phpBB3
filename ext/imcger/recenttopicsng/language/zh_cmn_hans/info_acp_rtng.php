<?php
/**
 *
 * Recent Topics NG. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, IMC, https://github.com/IMC-GER / LukeWCS, https://github.com/LukeWCS
 * @copyright (c) 2017, Sajaki, https://www.avathar.be
 * @copyright (c) 2015, PayBas
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 * Based on the original NV Recent Topics by Joas Schilling (nickvergessen)
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
	$lang = [];
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
// ‚ ‘ ’ « » “ ” … „ “
//
$lang = array_merge($lang, [
	// Language pack author
	'RTNG_LANG_DESC'				=> '简体中文',
	'RTNG_LANG_VER' 				=> '1.1.0',
	'RTNG_LANG_AUTHOR' 				=> 'IMC-GER / LukeWCS',

	// ACP forums (版块管理)
        'RTNG_FORUMS'                       => '在“近期主题”中显示',
        'RTNG_FORUMS_EXPLAIN'               => '勾选此项后，该版块的主题将显示在“近期主题”列表中。',

        // ACP nav (后台导航)
        'RTNG_NAME'                         => '近期主题',
        'RTNG_CONFIG'                       => '配置',

        // ACP module (后台模块说明)
        'RTNG_EXPLAIN'                      => '在此页面您可以更改 <strong>“%s”</strong> 扩展的特定设置。<br><br>可以通过在后台编辑相应的版块来包含或排除特定论坛。<br>另外请务必检查您的用户权限，该权限允许用户自行更改下方部分设置。',

        // ACP load (加载选项)
        'RTNG_LOAD_OPTIONS'                 => '“近期主题 NG”加载选项',
        'RTNG_LOAD_FIRST_UNRD_POST'         => '允许访问第一个未读帖子',
        'RTNG_LOAD_FIRST_UNRD_POST_EXPLAIN' => '如果启用此选项，系统将读取第一个未读帖子的数据并供后续处理使用。<br>必须同时启用“<u><a href="#load_db_lastread">开启服务端主题标记</a></u>”。',

	// Global settings (全局设置)
        'RTNG_GLOBAL_SETTINGS'          => '全局设置',
        'RTNG_INDEX_DISPLAY_EXP'        => '在首页显示',
        'RTNG_ALL_TOPICS'               => '显示所有近期主题页面',
        'RTNG_ALL_TOPICS_EXP'           => '此功能将覆盖“显示页数...”选项，无论设置多少页，都会显示所有页面。',
        'RTNG_MIN_TOPIC_LEVEL'          => '显示的最低主题级别',
        'RTNG_MIN_TOPIC_LEVEL_EXP'      => '确定要显示的最低主题类型。系统将仅显示该级别及以上级别的主题（如：置顶、公告）。',
        'RTNG_ANTI_TOPICS'              => '排除的主题 ID',
        'RTNG_ANTI_TOPICS_EXP'          => '输入要排除的主题 ID，用逗号分隔（例如 7,9），设为 0 则显示所有主题。（ID 见 URL 中的 <code>viewtopic.php?t=12345</code>）。',
        'RTNG_EXCLUDED_FORUMS'           => '排除的版块',
        'RTNG_EXCLUDED_FORUMS_EXP'       => '输入要排除的版块 ID，使用逗号分隔。留空即可清空这个列表。其主题将不会计入或显示在“近期主题”中。此设置会与单个版块的显示设置和排除的主题 ID 一起生效，所以如果某个版块在版块设置里关闭了“在近期主题中显示”，即使这里留空，它仍然会继续被排除。',
        'RTNG_PARENTS'                  => '显示上级版块',
        'RTNG_PARENTS_EXP'              => '在近期主题的主题行中显示其所属的上级版块名称。',
        'RTNG_SIMPLE_LINK'              => '链接到简化页面',
        'RTNG_SIMPLE_TOPICS_QTY'        => '简化页面每页显示的主题数量',
        'RTNG_SIMPLE_TOPICS_QTY_EXP'    => '用于指定简化页面列表中每页显示的最大主题数量。',
        'RTNG_SIMPLE_PAGE_QTY'          => '简化页面显示的总页数',
        'RTNG_SIMPLE_PAGE_QTY_EXP'      => '用于指定简化页面列表中允许显示的最大总页数。',

	// User Overridable settings (用户可覆盖设置)
        'RTNG_OVERRIDABLE'              => '用户控制面板 (UCP) 可覆盖设置',
        'RTNG_OVERRIDABLE_EXPLAIN'      => '为了让用户能在个人控制面板中修改这些设置，必须在权限管理中为他们分配相应的用户权限。如果他们没有该权限，系统将应用默认设置。这些数值也适用于新用户和游客。<br><br>若要在主题标题中显示第一个未读帖子的标题、作者和日期，必须先在 <u><a href="%s">服务器加载</a></u> 设置中启用该选项。',
        'RTNG_ENABLE'                   => '显示近期主题',
        'RTNG_LOCATION'                 => '显示位置',
        'RTNG_LOCATION_EXP'             => '选择近期主题的显示位置。',
        'RTNG_TOP'                      => '顶部显示',
        'RTNG_BOTTOM'                   => '底部显示',
        'RTNG_SIDE'                     => '侧边栏显示',
        'RTNG_SEPARATE'                 => '仅在独立页面显示',
        'RTNG_SORT_START_TIME'          => '按主题发布时间排序',
        'RTNG_SORT_START_TIME_EXP'      => '启用后，将按主题的发布时间排序，而不是最后回复时间。',
        'RTNG_UNREAD_ONLY'              => '仅显示未读主题',
        'RTNG_UNREAD_ONLY_EXP'          => '启用后，仅显示未读主题（无论是否为“近期”）。此功能使用的过滤设置（排除版块/主题等）与普通模式一致。<br>注意：此功能仅对登录用户有效；游客将看到普通列表。',
        'RTNG_DISP_LAST_POST'           => '主题标题链接',
        'RTNG_DISP_LAST_POST_EXP'       => '此选项允许您指定点击主题标题时是跳转到第一个帖子还是最后一个帖子。相应帖子的标题也将被用作主题标题。',
        'RTNG_FIRST_POST'               => '跳转到第一个帖子',
        'RTNG_LAST_POST'                => '跳转到最后一个帖子',
        'RTNG_DISP_FIRST_UNRD_POST'     => '主题标题链接至未读帖子',
        'RTNG_DISP_FIRST_UNRD_POST_EXP' => '激活此选项后，点击主题标题将跳转到该主题中的第一个未读帖子（如果有）。相应帖子的标题也将被用作主题标题。如果该主题中没有未读帖子，或者此选项已关闭，则应用“主题标题链接”的设置。',
        'RTNG_INDEX_TOPICS_QTY'         => '论坛首页每页显示的主题数量',
        'RTNG_INDEX_TOPICS_QTY_EXP'     => '指定论坛首页列表中每页显示的主题最大数量。',
        'RTNG_INDEX_PAGE_QTY'           => '论坛首页显示的总页数',
        'RTNG_INDEX_PAGE_QTY_EXP'       => '指定论坛首页列表中允许显示的最大总页数。',
        'RTNG_SEPARATE_TOPICS_QTY'      => '独立页面显示时每页的主题数量',
        'RTNG_SEPARATE_TOPICS_QTY_EXP'  => '在此指定独立页面模式下，列表中每页显示的主题最大数量。',
        'RTNG_SEPARATE_PAGE_QTY'        => '独立页面显示的总页数',
        'RTNG_SEPARATE_PAGE_QTY_EXP'    => '在此指定独立页面模式下，列表中允许显示的最大总页数。',
        'RTNG_RESET_DEFAULT'            => '覆盖用户设置',
        'RTNG_RESET_DEFAULT_EXP'        => '启用此选项后，所有用户的个人设置将被覆盖。若不启用，则仅为新用户和游客设置默认值。',
        'RTNG_RESET_ASK_BEFORE_EXP'     => '此设置将用您的默认值覆盖所有用户的个人设置。<br><strong>此操作不可逆！</strong>',
]);
