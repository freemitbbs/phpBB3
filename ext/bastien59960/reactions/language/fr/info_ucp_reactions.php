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
	'UCP_REACTIONS_TITLE' => 'Préférences des réactions',
	'UCP_REACTIONS_SETTINGS' => 'Préférences des réactions',
	'UCP_REACTIONS_SETTING' => 'Préférences des réactions',
]);
