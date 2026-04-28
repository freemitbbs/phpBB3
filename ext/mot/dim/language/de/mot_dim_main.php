<?php
/**
*
* @package MoT DIM v1.2.0
* @copyright (c) 2024 - 2025 Mike-on-Tour
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
* DO NOT CHANGE
*/
if ( !defined('IN_PHPBB') )
{
	exit;
}
if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'MOT_DIM_CHECK_RESULT'			=> 'DIM-Einstellungen testen',
	'MOT_DIM_NO_ITEMS'				=> 'Keine Einträge',
	'MOT_DIM_TOTAL_USERS'			=> [
		0	=> 'keine Mitglieder',
		1	=> '%1$d Mitglied insgesamt',
		2	=> '%1$d Mitglieder insgesamt',
	],
	'MOT_DIM_REGISTERED'			=> 'Registriert',
	'MOT_DIM_NOT_ACTIVATED'			=> 'Nicht aktiviert',
	'MOT_DIM_SLEEPER'				=> 'Nie eingeloggt',
	'MOT_DIM_ZEROPOSTER'			=> 'Nullposter',
	'MOT_DIM_LOG_INACTIVE_DEL'		=> [
		1		=> '<strong>mot/dim hat %1$d nie aktivierten Benutzer gelöscht</strong><br>» %2$s',
		2		=> '<strong>mot/dim hat %1$d nie aktivierte Benutzer gelöscht</strong><br>» %2$s',
	],
	'MOT_DIM_LOG_SLEEPER_DEL'		=> [
		1		=> '<strong>mot/dim hat %1$d Schläfer gelöscht</strong><br>» %2$s',
		2		=> '<strong>mot/dim hat %1$d Schläfer gelöscht</strong><br>» %2$s',
	],
	'MOT_DIM_LOG_ZEROPOSTER_DEL'	=> [
		1		=> '<strong>mot/dim hat %1$d Nullposter gelöscht</strong><br>» %2$s',
		2		=> '<strong>mot/dim hat %1$d Nullposter gelöscht</strong><br>» %2$s',
	],
]);
