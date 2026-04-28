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
	'ACP_ADULTACCESS_GRP' => 'Adult Access',
	'ACP_ADULTACCESS' => 'Adult forum access',
	'ADULTACCESS_FORUM_IDS' => 'Adult forum IDs',
	'ADULTACCESS_FORUM_IDS_EXPLAIN' => 'Comma-separated post forum IDs. Saving will copy current readable member access to the hidden 18+ opt-in group, remove non-staff forum visibility grants, and preserve other forum permissions.',
	'ADULTACCESS_GROUP_LABEL' => 'Hidden opt-in group',
	'ADULTACCESS_SYNC_EXPLAIN' => 'This extension gates the selected forums through a hidden user group. While a forum is gated, Adult Access owns forum visibility permissions; other permission edits are kept when the forum is removed from this list.',
	'ADULTACCESS_FORUM_STATUS' => 'Forum status',
	'ADULTACCESS_ADULT_GROUP_ACCESS' => '18+ group access',
	'ADULTACCESS_BLOCKED_GROUPS' => 'Visibility bypasses',
	'ADULTACCESS_OTHER_GROUPS' => 'Other ACL groups',
	'ADULTACCESS_FORUM_MISSING' => 'Missing or not a post forum',
	'ADULTACCESS_CONFIG_UPDATED_INVALID' => 'Settings saved. Ignored invalid forum IDs: %s',
	'ADULTACCESS_CONFIG_UPDATED_SKIPPED' => 'Could not gate these forum IDs: %s',
	'ADULTACCESS_SKIP_FORUM_MISSING' => 'missing or not a post forum',
	'ADULTACCESS_SKIP_GROUP_MISSING' => 'hidden opt-in group is missing',
	'ADULTACCESS_SKIP_NO_SOURCE' => 'no readable Registered or existing 18+ permission source',
]);
