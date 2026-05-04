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
	'ACP_CARDGAMESAUTH' => 'Card Games Auth Bridge',
	'ACP_CARDGAMESAUTH_GRP' => 'Card Games',
	'ACP_CARDGAMESAUTH_SETTINGS' => 'Card games settings',

	'CARDGAMESAUTH_EXPLAIN' => 'Settings for the phpBB session bridge used by the local card-game client and game server.',
	'CARDGAMESAUTH_FIELDSET_GENERAL' => 'General',
	'CARDGAMESAUTH_FIELDSET_CLIENT' => 'Client and game server',
	'CARDGAMESAUTH_FIELDSET_TOKEN' => 'Token issuance',

	'CARDGAMESAUTH_ENABLED' => 'Enable bridge',
	'CARDGAMESAUTH_ENABLED_EXPLAIN' => 'Allow logged-in users with permission to request card-game auth tokens.',
	'CARDGAMESAUTH_NAV_ENABLED' => 'Show navigation link',
	'CARDGAMESAUTH_NAV_ENABLED_EXPLAIN' => 'Show the Card Games link next to the Blog link in the forum header.',
	'CARDGAMESAUTH_CLIENT_URL' => 'Client URL or path',
	'CARDGAMESAUTH_CLIENT_URL_EXPLAIN' => 'Path or URL for the static card-game client, for example /card-games/client/. Leave blank until the client assets exist.',
	'CARDGAMESAUTH_LAUNCH_REDIRECT' => 'Redirect launch page to client',
	'CARDGAMESAUTH_LAUNCH_REDIRECT_EXPLAIN' => 'When enabled, /card-games redirects allowed users directly to the configured client URL.',
	'CARDGAMESAUTH_WS_URL' => 'WebSocket URL',
	'CARDGAMESAUTH_WS_URL_EXPLAIN' => 'Public game server WebSocket URL. Leave blank to default to ws(s)://this-board/card-games/ws.',
	'CARDGAMESAUTH_TOKEN_TTL' => 'Token lifetime',
	'CARDGAMESAUTH_TOKEN_TTL_EXPLAIN' => 'Seconds before issued auth tokens expire. Allowed range: 30-600.',
	'CARDGAMESAUTH_TOKEN_RATE_LIMIT' => 'Token request limit',
	'CARDGAMESAUTH_TOKEN_RATE_LIMIT_EXPLAIN' => 'Maximum tokens a user session may request inside the rate-limit window. Allowed range: 1-120.',
	'CARDGAMESAUTH_TOKEN_RATE_WINDOW' => 'Token rate-limit window',
	'CARDGAMESAUTH_TOKEN_RATE_WINDOW_EXPLAIN' => 'Rate-limit window in seconds. Allowed range: 10-3600.',
]);
