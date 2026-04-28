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
	'DECIMAL_TS'				=> '2',
	'DECIMAL_SEPARATOR_TS'		=> ',',
	'THOUSANDS_SEPARATOR_TS'	=> '.',
	
	'MOST_VIEWED'				=> 'Mest sete emner',
	'MOST_REPLIED'				=> 'Mest besvarede emner',
	'RECENT_ACTIVE'				=> 'Seneste aktive emner',
	'MOST_ACTIVE_USERS'			=> 'Mest aktive brugere',
	'JOINED_US'					=> 'Du blev tilmeldt',
	'MOST_ACTIVE_FORUMS'		=> 'Mest aktive fora',
	'PREVIOUS_SCROLL'			=> 'Forrige',
	'NEXT_SCROLL'				=> 'Næste',
	'START_SCROLL'				=> 'Start',
	'STOP_SCROLL'				=> 'Stop',
	'LAST_REGISTERED_USERS'		=> 'Senest registrede brugere',
	'LAST_VISITED_BOTS'			=> 'Senest besøgende botter',
	'TOP_POSTERS_THIS_MONTH'	=> 'Top posters i',
	'TOP_POSTERS_LAST_MONTH'	=> 'Top posters i',
	'NO_DATA'					=> 'No data',
	'NO_TOP_POSTER'				=> 'Ingen Top Posters denne måned',
	
	'TS_MONTH_JANUARY'			=> 'Januar',
	'TS_MONTH_FEBRUARY'			=> 'Februar',
	'TS_MONTH_MARCH'			=> 'Marts',
	'TS_MONTH_APRIL'			=> 'April',
	'TS_MONTH_MAY'				=> 'Maj',
	'TS_MONTH_JUNE'				=> 'Juni',
	'TS_MONTH_JULY'				=> 'Juli',
	'TS_MONTH_AUGUST'			=> 'August',
	'TS_MONTH_SEPTEMBER'		=> 'September',
	'TS_MONTH_OCTOBER'			=> 'Oktober',
	'TS_MONTH_NOVEMBER'			=> 'November',
	'TS_MONTH_DECEMBER'			=> 'December',
	
	'TOP_STATS_PAGE_TITLE'		=> 'Top Stats',
	'TOP_STATS_COPY'			=> 'phpBB Top Stats',
	'TM_TOP_POSTERS'			=> 'Denne måneds Top Posters',
	'LM_TOP_POSTERS'			=> 'Sidste måneds Top Posters',
	'TS_TOP_POSTERS'			=> 'Top Posters',
	'TS_TOP_POSTERSFOR'			=> 'Top Posters for',
	'TS_TOP_COPY'				=> 'phpBB Top Posters',
	'TS_TOP_STATS'				=> 'Top Stats',
    'VIEWING_TOP_POSTERS'		=> 'Læser Top Posters',
    'VIEWING_TOP_STATS'			=> 'Læser Top Stats',
	'TOPPOSTERS_DISABLED'		=> 'Top Poster siden er i øjeblikket deaktiveret',
	'TOPSTATS_DISABLED'			=> 'Top Stats siden er i øjeblikket deaktiveret',
	'TS_TOP_POSTERS_FOR'		=> '%1$s Top Posters',
	
	'TS_REQUIRE_PHPBB'			=> 'Denne udvidelse kræver phpBB %1$s eller højere. Dit forum kører %2$s.',
    'TS_REQUIRE_PHP'			=> 'Denne udvidelse kræver PHP %1$s eller højere. Din server kører %2$s.',
	'TS_REQUIRE_REMOVE'			=> 'Afinstaller venligst den tidligere Top Stats fuldstændigt, før du installerer Top Stats & Top Posters 2.0.0 og nyere.',
));
