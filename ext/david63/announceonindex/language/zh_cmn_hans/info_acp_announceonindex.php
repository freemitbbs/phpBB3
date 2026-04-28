<?php
/**
*
* @package Announcements on index
* @copyright (c) 2015 david63
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
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
	'ALLOW_EVENTS'                  => '允许模板事件',
        'ALLOW_EVENTS_EXPLAIN'          => '允许在公告中使用模板事件。<br />如果其他模板事件引起问题或产生非预期结果，请关闭此项。',
        'ALLOW_GUESTS'                  => '允许访客查看公告',
        'ALLOW_GUESTS_EXPLAIN'          => '允许未登录的访客查看公告内容。',

        'ANNOUNCE_ON_INDEX'             => '首页公告',
        'ANNOUNCE_ON_INDEX_EXPLAIN'     => '管理公告选项。',
        'ANNOUNCE_ON_INDEX_LOG'         => '<strong>首页公告设置已更新</strong>',
        'ANNOUNCE_ON_INDEX_MANAGE'      => '管理公告',
        'ANNOUNCE_ON_INDEX_OPTIONS'     => '公告选项词',
        'GLOBAL_ON_INDEX_LOG'           => '<strong>首页全局公告设置已更改</strong>',
        'SHOW_ANNOUNCEMENTS'            => '显示公告',
        'SHOW_ANNOUNCEMENTS_EXPLAIN'    => '在首页上显示所有普通公告。',
        'SHOW_GLOBALS'                  => '显示全局公告',
        'SHOW_GLOBALS_EXPLAIN'          => '在首页上显示所有全局公告。',

        //捐赠相关
        'PAYPAL_IMAGE_URL'              => 'https://paypalobjects.com',
        'PAYPAL_ALT'                    => '使用 PayPal 捐赠',
        'BUY_ME_A_BEER_URL'             => 'https://paypal.me',
        'BUY_ME_A_BEER'                 => '请我喝杯啤酒（感谢开发此扩展）',
        'BUY_ME_A_COFFEE'               => '<a href="https://buymeacoffee.com" target="_blank"><img src="https://buymeacoffee.com" alt="请我喝杯咖啡" style="height: 26px !important;width: auto !important;" ></a>',
        'BUY_ME_A_BEER_SHORT'           => '为该扩展进行捐赠',
        'BUY_ME_A_BEER_EXPLAIN'         => '此扩展完全免费。这是我为了 phpBB 社区的乐趣和使用而投入时间开发的项目。如果您喜欢使用此扩展，或者它对您的论坛有所帮助，请考虑<a href="https://paypal.me" target="_blank" rel="noreferrer noopener">请我喝杯啤酒</a>。不胜感激。<i class="fa fa-smile-o" style="color:green;font-size:1.5em;" aria-hidden="true"></i>'
));
