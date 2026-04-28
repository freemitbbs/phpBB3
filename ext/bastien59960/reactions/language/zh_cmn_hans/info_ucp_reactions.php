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
	'UCP_REACTIONS_TITLE'    => '表态偏好设置',
    	'UCP_REACTIONS_SETTINGS' => '表态偏好设置',
    	'UCP_REACTIONS_SETTING'  => '表态偏好设置',
]);
