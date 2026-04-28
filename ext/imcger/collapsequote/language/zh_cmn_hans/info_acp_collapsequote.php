<?php
/**
 * Collapse Quote
 * An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Thorsten Ahlers
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
// ’ » “ ” …
//

$lang = array_merge($lang, [
	'ACP_COLLAPSEQUOTE_TITLE'               => '引用折叠 (Collapse Quote)',
        'ACP_COLLAPSEQUOTE_SETTINGS'            => '设置',
        'ACP_COLLAPSEQUOTE_SETTING_SAVED'       => '引用折叠设置已成功保存。',

        // Language pack author
        'COLLAPSEQUOTE_LANG_DESC'               => '简体中文',
        'COLLAPSEQUOTE_LANG_EXT_VER'            => '1.4.0',
        'COLLAPSEQUOTE_LANG_AUTHOR'             => 'AI助手',

        // Message text
        'COLLAPSEQUOTE_SETTING_SAVED'           => '设置已成功保存。',
        'COLLAPSEQUOTE_USER_SETTING_SAVED'      => '所有用户的设置已成功保存。',
        'COLLAPSEQUOTE_DEFAULT_SETTING_SAVED'   => '新用户和游客的默认设置已成功保存。',

        // Confirm Box
        'COLLAPSEQUOTE_USER_SET_CONFIRM'        => '此设置将使用您的默认值覆盖所有用户的个人设置。<br><strong>此过程不可逆！</strong>',

        // Extension description
        'COLLAPSEQUOTE_TITLE'                   => '引用折叠',
        'COLLAPSEQUOTE_TITLE_EXPLAIN'           => '对于非常长的引用内容，引用框的大小将被缩减。点击引用框下方的按钮即可查看完整内容。<br>在这里您可以设置引用框的大小以及折叠按钮的颜色。',

        // User settings
        'COLLAPSEQUOTE_SETTINGS_USER'           => '游客和新用户的设置',

        'COLLAPSEQUOTE_ACTIVE'                  => '启用引用折叠',
        'COLLAPSEQUOTE_ACTIVE_DESC'             => '引用内容将被缩减至指定的显示行数，点击鼠标即可显示全文。',

        'COLLAPSEQUOTE_VISIBLE_LINES'           => '显示行数',
        'COLLAPSEQUOTE_VISIBLE_LINES_DESC'      => '引用框在折叠状态下显示的行数。',

        'COLLAPSEQUOTE_TEXT_TOP'                => '文本对齐',
        'COLLAPSEQUOTE_TEXT_TOP_DESC'           => '选择在折叠状态下显示引用内容的哪些部分。',
        'COLLAPSEQUOTE_TOP'                     => '显示开头几行',
        'COLLAPSEQUOTE_BOTTOM'                  => '显示末尾几行',

        'COLLAPSEQUOTE_OVERWRITE_USERSET'       => '覆盖用户设置',
        'COLLAPSEQUOTE_OVERWRITE_USERSET_DEC'   => '勾选此项将覆盖所有用户的个人设置。如果不勾选，则仅设置游客和新用户的默认值。',

	// General settings
        'COLLAPSEQUOTE_SETTINGS_STYLE'                  => '样式设置',

        'COLLAPSEQUOTE_BUTTON_FG_COLOR'                 => '按钮前景色 (文字颜色)',
        'COLLAPSEQUOTE_BUTTON_FG_COLOR_DESC'            => '选择用于展开/折叠引用框的按钮字体颜色。如果留空，则使用系统默认颜色。',
        'COLLAPSEQUOTE_BUTTON_BG_COLOR'                 => '按钮背景色',
        'COLLAPSEQUOTE_BUTTON_BG_COLOR_DESC'            => '选择按钮的背景颜色。如果留空，则使用系统默认颜色。',

        'COLLAPSEQUOTE_BUTTON_FG_COLOR_HOVER'           => '按钮悬停前景色',
        'COLLAPSEQUOTE_BUTTON_FG_COLOR_HOVER_DESC'      => '当鼠标悬停在展开/折叠按钮上时显示的字体颜色。如果留空，则不发生颜色变化。',
        'COLLAPSEQUOTE_BUTTON_BG_COLOR_HOVER'           => '按钮悬停背景色',
        'COLLAPSEQUOTE_BUTTON_BG_COLOR_HOVER_DESC'      => '当鼠标悬停在按钮上时显示的背景颜色。如果留空，则不发生颜色变化。',

        // Messages requirement check
        'IMCGER_REQUIRE_PHPBB'   => '此扩展要求 phpBB 版本大于或等于 %1$s 且小于 %2$s。您当前的 phpBB 版本是 %3$s。',
        'IMCGER_REQUIRE_PHP'     => '此扩展要求 PHP 版本大于或等于 %1$s 且小于 %2$s。您当前的 PHP 版本是 %3$s。',
]);
