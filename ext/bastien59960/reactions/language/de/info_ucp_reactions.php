<?php

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'UCP_REACTIONS_TITLE' => 'Reaction Preferences',
	'UCP_REACTIONS_SETTINGS' => 'Reaction Preferences',
	'UCP_REACTIONS_SETTING' => 'Reaction Preferences',
]);
