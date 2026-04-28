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
	'UCP_ADULTACCESS' => 'Adult Content Access',
	'ADULTACCESS_CONFIRM_TITLE' => 'Adult forum access confirmation',
	'ADULTACCESS_CONFIRM_EXPLAIN' => 'This page controls whether adult forums are shown for your account.',
	'ADULTACCESS_ATTESTATION' => 'I confirm that I am at least 18 years old and that viewing adult forums does not violate the laws or regulations applicable to my location.',
	'ADULTACCESS_CONFIRM_BUTTON' => 'I Confirm',
	'ADULTACCESS_CANCEL_BUTTON' => 'Cancel',
	'ADULTACCESS_ENABLED_TITLE' => 'Adult forum access is enabled',
	'ADULTACCESS_ENABLED_EXPLAIN' => 'Adult forums are currently visible to your account. Disabling access will hide them immediately and remove related bookmarks and subscriptions.',
	'ADULTACCESS_DISABLE_BUTTON' => 'Disable Access',
	'ADULTACCESS_KEEP_BUTTON' => 'Keep Access',
	'ADULTACCESS_LAST_CONFIRMED' => 'Last confirmed',
	'ADULTACCESS_NOT_READY' => 'Adult forum access is not available right now. Please contact the board administrator.',
	'ADULTACCESS_NO_FORUMS_CONFIGURED' => 'No adult forums are configured yet.',
	'ADULTACCESS_RETURN_BUTTON' => 'Return to UCP',
	'ADULTACCESS_OPT_IN_SAVED' => 'Adult forum access has been enabled for your account.',
	'ADULTACCESS_OPT_OUT_SAVED' => 'Adult forum access has been disabled for your account.',
]);
