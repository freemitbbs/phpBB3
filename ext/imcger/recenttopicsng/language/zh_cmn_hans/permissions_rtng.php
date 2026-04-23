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
	'ACL_CAT_RTNG'                          => '近期主题',
        'ACL_U_RTNG_VIEW'                       => '可以查看近期主题列表。',
        'ACL_U_RTNG_ENABLE'                     => '可以启用或禁用近期主题显示。',
        'ACL_U_RTNG_LOCATION'                   => '可以选择近期主题板块的显示位置。',
        'ACL_U_RTNG_SORT_START_TIME'            => '可以更改主题排序方式。',
        'ACL_U_RTNG_UNREAD_ONLY'                => '可以更改设置仅显示未读主题。',
        'ACL_U_RTNG_DISP_LAST_POST'             => '可以设置在主题标题中显示最后发表的帖子。',
        'ACL_U_RTNG_DISP_FIRST_UNRD_POST'       => '可以设置在主题标题中显示第一个未读帖子。',
        'ACL_U_RTNG_INDEX_TOPICS_QTY'           => '可以更改论坛首页每页显示的主题数量。',
        'ACL_U_RTNG_INDEX_PAGE_QTY'             => '可以更改论坛首页显示的总页数。',
        'ACL_U_RTNG_SEPARATE_TOPICS_QTY'        => '可以更改独立页面每页显示的主题数量。',
        'ACL_U_RTNG_SEPARATE_PAGE_QTY'          => '可以更改独立页面显示的总页数。',

]);
