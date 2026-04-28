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
	'ALLOW_EVENTS'					=> 'Allow template events',
	'ALLOW_EVENTS_EXPLAIN'			=> 'Allow the use of template events in the announcements.<br />Turn this off if other template events are causing problems and/or undesirable results.',
	'ALLOW_GUESTS'					=> 'Allow guests to see announcements',
	'ALLOW_GUESTS_EXPLAIN'			=> 'Allow guests to see the announcements.',

	'ANNOUNCE_ON_INDEX'				=> 'Announcements on index',
	'ANNOUNCE_ON_INDEX_EXPLAIN' 	=> 'Manage the announcement options.',
	'ANNOUNCE_ON_INDEX_LOG'			=> '<strong>Announcements on index settings updated </strong>',
	'ANNOUNCE_ON_INDEX_MANAGE'		=> 'Manage announcements',
	'ANNOUNCE_ON_INDEX_OPTIONS'		=> 'Announcement options',
	'GLOBAL_ON_INDEX_LOG'			=> '<strong>Announcements on Index settings changed</strong>',
	'SHOW_ANNOUNCEMENTS'			=> 'Show announcements',
	'SHOW_ANNOUNCEMENTS_EXPLAIN'	=> 'Display all announcements on the index page.',
	'SHOW_GLOBALS'					=> 'Show global announcements',
	'SHOW_GLOBALS_EXPLAIN'			=> 'Display all global announcements on the index page.',
	//Donation
	'PAYPAL_IMAGE_URL'          => 'https://www.paypalobjects.com/webstatic/en_US/i/btn/png/silver-pill-paypal-26px.png',
	'PAYPAL_ALT'                => 'Donate using PayPal',
	'BUY_ME_A_BEER_URL'         => 'https://paypal.me/RMcGirr83',
	'BUY_ME_A_BEER'				=> 'Buy me a beer for creating this extension',
	'BUY_ME_A_COFFEE'			=> '<a href="https://www.buymeacoffee.com/rmcgirr83" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" style="height: 26px !important;width: auto !important;" ></a>',
	'BUY_ME_A_BEER_SHORT'		=> 'Make a donation for this extension',
	'BUY_ME_A_BEER_EXPLAIN'		=> 'This extension is completely free. It is a project that I spend my time on for the enjoyment and use of the phpBB community. If you enjoy using this extension, or if it has benefited your forum, please consider <a href="https://paypal.me/RMcGirr83" target="_blank" rel="noreferrer noopener">buying me a beer</a>. It would be greatly appreciated. <i class="fa fa-smile-o" style="color:green;font-size:1.5em;" aria-hidden="true"></i>',
));
