<?php

if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang, [
	'ANONPOST_OPTION' => 'Post anonymously',
	'ANONPOST_EXPLAIN' => 'Hide your account, avatar and signature from other readers. Moderators and administrators can still identify the author.',
	'ANONPOST_LOCKED' => 'This post is anonymous and will remain anonymous after editing.',
	'ANONPOST_UNAVAILABLE' => 'Anonymous posting is only available for new topics and replies in designated forums.',
	'ANONPOST_AUTHOR' => 'Anonymous post author (moderators only)',
	'ANONPOST_REUPLOAD' => 'This attachment has an old storage URL containing account information. Remove and upload it again before posting anonymously.',
]);
