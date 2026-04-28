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
	'MOT_DIM_CHECK_RESULT'		=> 'Test DIM settings',
	'MOT_DIM_NO_ITEMS'			=> 'No items',
	'MOT_DIM_TOTAL_USERS'		=> [
		0	=> 'No members',
		1	=> '%1$d member total',
		2	=> '%1$d members total',
	],
	'MOT_DIM_REGISTERED'		=> 'Registered',
	'MOT_DIM_NOT_ACTIVATED'		=> 'Not activated',
	'MOT_DIM_SLEEPER'			=> 'Never logged in',
	'MOT_DIM_ZEROPOSTER'		=> 'Zeroposter',
	'MOT_DIM_LOG_INACTIVE_DEL'		=> [
		1		=> '<strong>mot/dim deleted %1$d user never activated</strong><br>» %2$s',
		2		=> '<strong>mot/dim deleted %1$d users never activated</strong><br>» %2$s',
	],
	'MOT_DIM_LOG_SLEEPER_DEL'		=> [
		1		=> '<strong>mot/dim deleted %1$d sleeper</strong><br>» %2$s',
		2		=> '<strong>mot/dim deleted %1$d sleepers</strong><br>» %2$s',
	],
	'MOT_DIM_LOG_ZEROPOSTER_DEL'	=> [
		1		=> '<strong>mot/dim deleted %1$d zeroposter</strong><br>» %2$s',
		2		=> '<strong>mot/dim deleted %1$d zeroposters</strong><br>» %2$s',
	],
]);
