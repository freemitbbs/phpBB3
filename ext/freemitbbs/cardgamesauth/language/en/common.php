<?php

if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang, [
	'ACL_U_CARDGAMES_PLAY' => 'Can play card games',
	'CARDGAMES_TITLE' => 'Card Games',
	'CARDGAMES_NAV' => 'Card Games',
	'CARDGAMES_LOGIN_REQUIRED' => 'You must be logged in to play card games.',
	'CARDGAMES_NOT_ALLOWED' => 'Your account is not allowed to play card games.',
	'CARDGAMES_LAUNCH_HEADING' => 'Card Games',
	'CARDGAMES_LAUNCH_BODY' => 'The card game client is ready to connect with your forum account.',
	'CARDGAMES_OPEN_CLIENT' => 'Open card games',
	'CARDGAMES_CLIENT_NOT_CONFIGURED' => 'The card game client URL has not been configured yet.',
	'CARDGAMES_ERR_DISABLED' => 'Card games are currently disabled.',
	'CARDGAMES_ERR_FORM' => 'Invalid card game token request.',
	'CARDGAMES_ERR_METHOD' => 'Invalid request method.',
	'CARDGAMES_ERR_PERMISSION' => 'You are not allowed to play card games.',
	'CARDGAMES_ERR_RATE_LIMIT' => 'Too many card game token requests. Please wait a moment and try again.',
]);
