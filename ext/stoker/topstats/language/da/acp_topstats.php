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
	'TOP_STATS'								=> 'Topstatistik',
	'TS_CONFIG'								=> 'Konfiguration',
	
	'ACP_TOPSTATSRA_BADGE'					=> 'Recent Active-Top Stats 2.0.9',
	'ACP_TOPSTATSSB_BADGE'					=> 'Stats Blocks-Top Stats 2.0.9',
	'ACP_TOPSTATSTP_BADGE'					=> 'Top Posters-Top Stats 2.0.9',
	'ACP_TOPSTATS_SETTINGS_EXPLAIN'			=> 'Hvis du kan lide denne udvidelse, så overvej venligst at følge',
	'ACP_TOPSTATS_DONATION'					=> 'Giv en donation',
	'ACP_TOPSTATS_MEMBER'					=> 'Bliv et aktivt medlem af mit fællesskab',
	'ACP_TOPSTATS_SUPPORT'					=> 'Support til udvidelsen eller feedback',
	'ACP_TOPSTATS_COPYRIGHT_VIOLATION'		=> 'Kontrol af copyright-beskyttelse mislykkedes!<br>Denne udvidelse kræver, at copyright-linket i bunden forbliver intakt.<br>Flere Top Stats-funktioner er blevet deaktiveret.<br>Gendan venligst copyright!',
	
	'TOPSTATS_SAVED'						=> 'Top Stats-indstillinger gemt',
	'TS_RECENT_SETTINGS'					=> 'Indstillinger for senest aktive emner',
	'TSRAT_NUMBER'							=> 'Senest aktive emner',
	'TSRAT_NUMBER_EXPLAIN'					=> 'Antal senest aktive emner der vises.<br>Sæt værdien til 0 (nul) for at deaktivere denne funktion. Grænsen er 50.',
	'TS_RECENT_LIMIT_RANGE'					=> 'Indtast venligst en værdi mellem 0 og %d for senest aktive emner!',
	'TS_STATS_LIMIT_RANGE'					=> 'Indtast venligst en værdi mellem 0 og %d for Top Stats-elementer!',
	'TS_TOPPOSTER_LIMIT_RANGE'				=> 'Indtast venligst en værdi mellem 0 og %d for Top Poster-elementer!',
	'TS_JSSCROLL'							=> 'jQuery-rulning',
	'TS_JSSCROLL_EXPLAIN'					=> 'Aktivér eller deaktivér brugen af jQuery-rulning for senest aktive emner.',
	'TS_JSSCROLL_SPEED'						=> 'jQuery-rullehastighed',
	'TS_JSSCROLL_SPEED_EXPLAIN'				=> 'Hastigheden på rulningen i millisekunder (standard er 400).',
	'TS_JSSCROLL_INTERVAL'					=> 'jQuery-rulleinterval',
	'TS_JSSCROLL_INTERVAL_EXPLAIN'			=> 'Tiden mellem rulninger i millisekunder (standard er 4000).',
	'TS_JSSCROLL_DIRECTION'					=> 'jQuery-rulleretning',
	'TS_JSSCROLL_DIRECTION_EXPLAIN'			=> 'Retningen på jQuery-rulningen.',
	'TS_JSSCROLL_DIRECTION_DOWN'			=> 'Ned',
	'TS_JSSCROLL_DIRECTION_UP'				=> 'Op',
	'TS_JSSCROLL_PAUSE'						=> 'Pause ved jQuery-rulning',
	'TS_JSSCROLL_PAUSE_EXPLAIN'				=> 'Når aktiveret pauses rulningen, når du holder musen over senest aktive emner.',
	'TS_JSSCROLL_NAVIGATION'				=> 'jQuery-rullenavigation',
	'TS_JSSCROLL_NAVIGATION_EXPLAIN'		=> 'Aktivér eller deaktivér jQuery-rullenavigation for senest aktive emner.',
	
	'TS_RECENT_CACHE_TIME'					=> 'Cache-varighed for seneste emner',
	'TS_RECENT_CACHE_TIME_EXPLAIN'			=> 'Hvor længe data for senest aktive emner skal caches.<br>Caching reducerer databasebelastning, men gør data en smule mindre opdaterede.<br>Vælg "Deaktiveret" for visning i realtid.',
	'TS_RECENT_CACHE_INVALID'				=> 'Ugyldig cache-tid valgt. Vælg venligst en værdi fra rullelisten.',
	'TS_CACHE_DISABLED'						=> 'Deaktiveret (ingen cache, realtid)',
	'TS_CACHE_1_MIN'						=> '1 minut',
	'TS_CACHE_2_MIN'						=> '2 minutter',
	'TS_CACHE_3_MIN'						=> '3 minutter',
	'TS_CACHE_5_MIN'						=> '5 minutter (anbefalet)',
	'TS_CACHE_10_MIN'						=> '10 minutter',
	'TS_CACHE_15_MIN'						=> '15 minutter',
	'TS_CACHE_30_MIN'						=> '30 minutter',
	
	'DISPLAY_TOP_RECENT_INDEX'				=> 'Aktivér senest aktive emner på indekset',
	'DISPLAY_TOP_RECENT_INDEX_EXPLAIN'		=> 'Aktivér eller deaktivér visning af senest aktive emner på forumindekset.',
	'DISPLAY_TOP_RECENT_PORTAL'				=> 'Aktivér senest aktive emner på portalen',
	'DISPLAY_TOP_RECENT_PORTAL_EXPLAIN'		=> 'Aktivér eller deaktivér visning af senest aktive emner på Simple Portal.',
	'TS_PORTAL_NOT_AVAILABLE'				=> 'Denne indstilling er kun tilgængelig, hvis <a href="https://phpbb3bbcodes.com/viewtopic.php?t=2719" title="Besøg Simple Portal-emnet hos PhpBB3 BBCodes" target="_blank" rel="noopener noreferrer">Simple Portal</a> er installeret og aktiveret.',
	'TS_TOPSTATS_SETTINGS'					=> 'Top Stats-indstillinger',
	'DISPLAY_TOP_STATS_INDEX'				=> 'Aktivér Top Stats på indekset',
	'DISPLAY_TOP_STATS_INDEX_EXPLAIN'		=> 'Aktivér eller deaktivér visning af Top Stats på forumindekset.',
	'DISPLAY_TOP_STATS_PORTAL'				=> 'Aktivér Top Stats på portalen',
	'DISPLAY_TOP_STATS_PORTAL_EXPLAIN'		=> 'Aktivér eller deaktivér visning af Top Stats på Simple Portal.',
	
	'TS_MOSTVIEWED_NUMBER'					=> 'Mest viste emner',
	'TS_MOSTVIEWED_NUMBER_EXPLAIN'			=> 'Antal mest viste emner der vises.<br>Sæt værdien til 0 (nul) for at deaktivere denne funktion. Grænsen er 50.<br>Mest viste emner caches i 24 timer.',
	'TS_MOSTREPLIED_NUMBER'					=> 'Mest besvarede emner',
	'TS_MOSTREPLIED_NUMBER_EXPLAIN'			=> 'Antal mest besvarede emner der vises.<br>Sæt værdien til 0 (nul) for at deaktivere denne funktion. Grænsen er 50.<br>Mest besvarede emner caches i 24 timer.',
	'TS_MOSTACTIVEUSER_NUMBER'				=> 'Mest aktive brugere',
	'TS_MOSTACTIVEUSER_NUMBER_EXPLAIN'		=> 'Antal mest aktive brugere der vises.<br>Sæt værdien til 0 (nul) for at deaktivere denne funktion. Grænsen er 50.<br>Mest aktive brugere caches i 24 timer.',
	'TS_MOSTACTIVEFORUM_NUMBER'				=> 'Mest aktive fora',
	'TS_MOSTACTIVEFORUM_NUMBER_EXPLAIN'		=> 'Antal mest aktive fora der vises.<br>Sæt værdien til 0 (nul) for at deaktivere denne funktion. Grænsen er 50.<br>Mest aktive fora caches i 24 timer.',
	'TS_LASTVISITEDBOT_NUMBER'				=> 'Senest besøgte bots',
	'TS_LASTVISITEDBOT_NUMBER_EXPLAIN'		=> 'Antal senest besøgte bots der vises.<br>Sæt værdien til 0 (nul) for at deaktivere denne funktion. Grænsen er 50.<br>Senest besøgte bots caches i 5 minutter.',
	'TS_LASTREGISTEREDUSER_NUMBER'			=> 'Senest registrerede brugere',
	'TS_LASTREGISTEREDUSER_NUMBER_EXPLAIN'	=> 'Antal senest registrerede brugere der vises.<br>Sæt værdien til 0 (nul) for at deaktivere denne funktion. Grænsen er 50.<br>Senest registrerede brugere caches i 5 minutter.',
	
	'TS_TOPSTATS_TP_EXCLUDE'				=> 'Udeluk Top Posters',
	'TS_THISMONTH_TOP_NUMBER'				=> 'Top Posters denne måned',
	'TS_THISMONTH_TOP_NUMBER_EXPLAIN'		=> 'Antal Top Posters denne måned der vises.<br>Sæt værdien til 0 (nul) for at deaktivere denne funktion. Grænsen er 50.<br>Cache for Top Posters denne måned håndteres på Top Poster-siden.',
	'TS_LASTMONTH_TOP_NUMBER'				=> 'Top Posters sidste måned',
	'TS_LASTMONTH_TOP_NUMBER_EXPLAIN'		=> 'Antal Top Posters sidste måned der vises.<br>Sæt værdien til 0 (nul) for at deaktivere denne funktion. Grænsen er 50.<br>Top Posters sidste måned caches indtil næste måned.',
	'TS_EXCLUDED_USERS' 					=> 'Udeluk bruger-ID’er',
	'TS_EXCLUDED_USERS_EXPLAIN' 			=> 'Kommasepareret liste over bruger-ID’er, der skal udelukkes fra Top Poster-statistikken. Eksempel: 23,67,890<br>(Disse udelukkes KUN fra Top Posters denne måned og Top Posters sidste måned)<br>Maksimal længde er 240 tegn.',
	'SUBMIT_EXCLUDED_USERS' 				=> 'Gem udelukkede brugere',
	'EXCLUDED_USERS_TOO_LONG'				=> 'Listen over udelukkede brugere er for lang. Hold den venligst under %d tegn.',
	'INVALID_EXCLUDED_USERS'				=> 'Kun cifre og kommaer er tilladt i feltet for udelukkede brugere.',
	'EXCLUDED_USER_NOT_EXIST'				=> 'Bruger-ID %d findes ikke.',
	
	'TS_TOPSTATS_EXCLUDE_FORUMS'			=> 'Udeluk fora',
	'TS_EXCLUDED_FORUMS'					=> 'Udeluk forum-ID’er',
	'TS_EXCLUDED_FORUMS_EXPLAIN'			=> 'Kommasepareret liste over forum-ID’er, der skal udelukkes fra Top Poster-statistikken. Indlæg fra disse fora tæller ikke med i rangeringen. Eksempel: 5,12,23<br>(Disse udelukkes KUN fra Top Posters denne måned og Top Posters sidste måned)<br>Maksimal længde er 240 tegn.',
	'SUBMIT_EXCLUDED_FORUMS'				=> 'Gem udelukkede fora',
	'EXCLUDED_FORUMS_TOO_LONG'				=> 'Listen over udelukkede fora er for lang. Hold den venligst under %d tegn.',
	'INVALID_EXCLUDED_FORUMS'				=> 'Kun cifre og kommaer er tilladt i feltet for udelukkede fora.',
	'EXCLUDED_FORUM_NOT_EXIST'				=> 'Forum-ID %d findes ikke.',
	
	'TS_TOPPOSTER_CACHE_TIME'				=> 'Cache-varighed for Top Posters',
	'TS_TOPPOSTER_CACHE_TIME_EXPLAIN'		=> 'Hvor længe data for Top Posters i den aktuelle måned skal caches.<br><strong>Små fora (under 50k indlæg):</strong> Brug 1–2 timer for næsten realtidsrangering.<br><strong>Mellemstore fora (50–200k indlæg):</strong> Brug 4–8 timer for balance mellem aktualitet og ydeevne.<br><strong>Store fora (200k+ indlæg):</strong> Brug "Resten af dagen" for optimal ydeevne (opdateres ved midnat).<br>Vælg "Deaktiveret" for visning i realtid (anbefales ikke til store fora).<br>Data for forrige måned caches i et år.',
	'TS_TOPPOSTER_CACHE_INVALID'			=> 'Ugyldig cache-varighed valgt. Vælg venligst en værdi fra rullelisten.',
	'TS_TP_CACHE_DISABLED'					=> 'Deaktiveret (ingen cache, realtid)',
	'TS_TP_CACHE_1_HOUR'					=> '1 time',
	'TS_TP_CACHE_2_HOURS'					=> '2 timer',
	'TS_TP_CACHE_3_HOURS'					=> '3 timer',
	'TS_TP_CACHE_4_HOURS'					=> '4 timer',
	'TS_TP_CACHE_8_HOURS'					=> '8 timer (anbefalet til mellemstore fora)',
	'TS_TP_CACHE_REST_OF_DAY'				=> 'Resten af dagen (anbefalet til store fora)',
	
	'TS_INDEX'								=> 'Forumindeks',
	'TS_PORTAL'								=> 'Simple Portal',
	'TS_CUSTOM'								=> 'Brugerdefineret side',
	'TS_SUBMIT_CHANGES'						=> 'Gem ændringer',
	
	'DISPLAY_TOP_RECENT_CUSTOM'				=> 'Aktivér senest aktive emner på brugerdefineret side',
	'DISPLAY_TOP_RECENT_CUSTOM_EXPLAIN'		=> 'Aktivér eller deaktivér visning af senest aktive emner på en brugerdefineret side.',
	'DISPLAY_TOP_STATS_CUSTOM'				=> 'Aktivér Top Stats på brugerdefineret side',
	'DISPLAY_TOP_STATS_CUSTOM_EXPLAIN'		=> 'Aktivér eller deaktivér visning af Top Stats på en brugerdefineret side.',
	
	'ACP_TS_TOPPOSTER'						=> 'Top Poster-side',
	'ACP_TS_TOPPOSTER_EXPLAIN'				=> 'Indstillingerne for Top Posters gælder kun den brugerdefinerede side.<br>Dog deles udelukkede bruger-ID’er.',
	'DISPLAY_TOP_STATS_TOPPOSTER'			=> 'Aktivér Top Posters-side',
	'DISPLAY_TOP_STATS_TOPPOSTER_EXPLAIN'	=> 'Vis den brugerdefinerede Top Posters-side for brugerne.',
));
