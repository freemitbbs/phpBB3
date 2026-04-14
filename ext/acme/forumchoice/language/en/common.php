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
	'FAVORITE_FORUMS'	=> 'My Favorite Forums',
	'ADD_FAVORITE'		=> 'Add to favorites',
	'REMOVE_FAVORITE'	=> 'Remove from favorites',
]);
