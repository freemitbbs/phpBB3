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
	'CARDGAMESAUTH_FIELDSET_PROXY' => 'Server DB proxy',

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
	'CARDGAMESAUTH_TOKEN_SECRET' => 'Game auth token secret',
	'CARDGAMESAUTH_TOKEN_SECRET_EXPLAIN' => 'Shared secret used to sign phpBB-issued game auth tokens. Set the same value as GAME_AUTH_TOKEN_SECRET on the game server. Keep this private; changing it invalidates existing tokens and requires updating the game server.',
	'CARDGAMESAUTH_TOKEN_TTL' => 'Token lifetime',
	'CARDGAMESAUTH_TOKEN_TTL_EXPLAIN' => 'Seconds before issued auth tokens expire. Allowed range: 30-600.',
	'CARDGAMESAUTH_TOKEN_RATE_LIMIT' => 'Token request limit',
	'CARDGAMESAUTH_TOKEN_RATE_LIMIT_EXPLAIN' => 'Maximum tokens a user session may request inside the rate-limit window. Allowed range: 1-120.',
	'CARDGAMESAUTH_TOKEN_RATE_WINDOW' => 'Token rate-limit window',
	'CARDGAMESAUTH_TOKEN_RATE_WINDOW_EXPLAIN' => 'Rate-limit window in seconds. Allowed range: 10-3600.',
	'CARDGAMESAUTH_TOKEN_CLOCK_TOLERANCE' => 'Token clock tolerance',
	'CARDGAMESAUTH_TOKEN_CLOCK_TOLERANCE_EXPLAIN' => 'Seconds of clock skew allowed when the server proxy verifies issued game tokens. Allowed range: 0-300.',
	'CARDGAMESAUTH_PROXY_ENABLED' => 'Enable server proxy',
	'CARDGAMESAUTH_PROXY_ENABLED_EXPLAIN' => 'Allow the external game server to call authenticated /card-games/server/* JSON endpoints.',
	'CARDGAMESAUTH_PROXY_SECRET' => 'Proxy HMAC secret',
	'CARDGAMESAUTH_PROXY_SECRET_EXPLAIN' => 'Shared secret used by the external game server to sign proxy requests. Keep this private; changing it requires updating the game server.',
	'CARDGAMESAUTH_PROXY_CLOCK_SKEW' => 'Proxy timestamp window',
	'CARDGAMESAUTH_PROXY_CLOCK_SKEW_EXPLAIN' => 'Allowed request timestamp skew in seconds. Allowed range: 30-3600.',
	'CARDGAMESAUTH_PROXY_NONCE_TTL' => 'Proxy nonce lifetime',
	'CARDGAMESAUTH_PROXY_NONCE_TTL_EXPLAIN' => 'Seconds to remember signed request nonces for replay protection. Allowed range: 30-3600.',
	'CARDGAMESAUTH_PROXY_MAX_BODY_BYTES' => 'Proxy max request body bytes',
	'CARDGAMESAUTH_PROXY_MAX_BODY_BYTES_EXPLAIN' => 'Maximum JSON body size accepted by server proxy endpoints. Allowed range: 1024-1048576.',
]);
