<?php
/**
 *
 * Top Stats extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 stoker - https://phpbb3bbcodes.com/
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
	'DECIMAL_TS'				=> '2',
	'DECIMAL_SEPARATOR_TS'		=> '.',
	'THOUSANDS_SEPARATOR_TS'	=> ',',
	
	'MOST_VIEWED'				=> 'Most viewed topics',
	'MOST_REPLIED'				=> 'Most replied topics',
	'RECENT_ACTIVE'				=> 'Recent active topics',
	'MOST_ACTIVE_USERS'			=> 'Most active users',
	'JOINED_US'					=> 'Joined us',
	'MOST_ACTIVE_FORUMS'		=> 'Most active forums',
	'PREVIOUS_SCROLL'			=> 'Previous',
	'NEXT_SCROLL'				=> 'Next',
	'START_SCROLL'				=> 'Start',
	'STOP_SCROLL'				=> 'Stop',
	'LAST_REGISTERED_USERS'		=> 'Last registered users',
	'LAST_VISITED_BOTS'			=> 'Last visited bots',
	'TOP_POSTERS_THIS_MONTH'	=> 'Top posters in',
	'TOP_POSTERS_LAST_MONTH'	=> 'Top posters in',
	'NO_DATA'					=> 'No data',
	'NO_TOP_POSTER'				=> 'No Top Posters this month',
	
	'TS_MONTH_JANUARY'			=> 'January',
	'TS_MONTH_FEBRUARY'			=> 'February',
	'TS_MONTH_MARCH'			=> 'March',
	'TS_MONTH_APRIL'			=> 'April',
	'TS_MONTH_MAY'				=> 'May',
	'TS_MONTH_JUNE'				=> 'June',
	'TS_MONTH_JULY'				=> 'July',
	'TS_MONTH_AUGUST'			=> 'August',
	'TS_MONTH_SEPTEMBER'		=> 'September',
	'TS_MONTH_OCTOBER'			=> 'October',
	'TS_MONTH_NOVEMBER'			=> 'November',
	'TS_MONTH_DECEMBER'			=> 'December',
	
	'TOP_STATS_PAGE_TITLE'		=> 'Top Stats',
	'TOP_STATS_COPY'			=> 'phpBB Top Stats',
	'TM_TOP_POSTERS'			=> 'This Month Top Posters',
	'LM_TOP_POSTERS'			=> 'Last Month Top Posters',
	'TS_TOP_POSTERS'			=> 'Top Posters',
	'TS_TOP_POSTERSFOR'			=> 'Top Posters for',
	'TS_TOP_COPY'				=> 'phpBB Top Posters',
	'TS_TOP_STATS'				=> 'Top Stats',
	'VIEWING_TOP_POSTERS'		=> 'Viewing Top Posters',
	'VIEWING_TOP_STATS'			=> 'Viewing Top Stats',
	'TOPPOSTERS_DISABLED'		=> 'The Top Poster page is currently disabled',
	'TOPSTATS_DISABLED'			=> 'The Top Stats page is currently disabled',
	'TS_TOP_POSTERS_FOR'		=> '%1$s Top Posters',
	
	'TS_REQUIRE_PHPBB'			=> 'This extension requires phpBB %1$s or higher. Your board is running %2$s.',
	'TS_REQUIRE_PHP'			=> 'This extension requires PHP %1$s or higher. Your server is running %2$s.',
	'TS_REQUIRE_REMOVE'			=> 'Please uninstall the previous Top Stats completely before installing Top Stats & Top Posters 2.0.0 and above',
]);
