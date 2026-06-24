<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

function phpbb_request_has_sid_query(\Symfony\Component\HttpFoundation\Request $symfony_request): bool
{
	return $symfony_request->query->has('sid') || $symfony_request->query->has('amp;sid');
}

function phpbb_request_is_get(\phpbb\request\request_interface $request): bool
{
	return strtoupper((string) $request->server('REQUEST_METHOD', 'GET')) === 'GET';
}

function phpbb_request_query_keys_are_allowed(\Symfony\Component\HttpFoundation\Request $symfony_request, array $allowed_keys): bool
{
	$allowed = array_fill_keys($allowed_keys, true);

	foreach (array_keys($symfony_request->query->all()) as $key)
	{
		if (!isset($allowed[$key]))
		{
			return false;
		}
	}

	return true;
}

function phpbb_strip_sid_and_redirect_current_get_request(\phpbb\request\request_interface $request, \Symfony\Component\HttpFoundation\Request $symfony_request, array $headers = []): void
{
	if (!phpbb_request_is_get($request) || !phpbb_request_has_sid_query($symfony_request))
	{
		return;
	}

	$query = $symfony_request->query->all();
	unset($query['sid'], $query['amp;sid']);

	$path = parse_url($symfony_request->getRequestUri(), PHP_URL_PATH);
	if (!is_string($path) || $path === '')
	{
		$path = $symfony_request->getBaseUrl() . $symfony_request->getPathInfo();
	}

	$query_string = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	$location = $path . ($query_string !== '' ? '?' . $query_string : '');

	header_remove('Set-Cookie');
	header('Location: ' . $location, true, 301);
	foreach ($headers as $name => $value)
	{
		header($name . ': ' . $value);
	}
	exit;
}

function phpbb_strip_sid_for_allowed_get_query(\phpbb\request\request_interface $request, \Symfony\Component\HttpFoundation\Request $symfony_request, array $allowed_query_keys, array $headers = []): void
{
	if (!phpbb_request_query_keys_are_allowed($symfony_request, $allowed_query_keys))
	{
		return;
	}

	phpbb_strip_sid_and_redirect_current_get_request($request, $symfony_request, $headers);
}

function phpbb_public_request_client_ip(\phpbb\request\request_interface $request): string
{
	foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $server_key)
	{
		$value = trim((string) $request->server($server_key, ''));
		if ($value === '')
		{
			continue;
		}

		if ($server_key === 'HTTP_X_FORWARDED_FOR')
		{
			$parts = explode(',', $value);
			$value = trim((string) ($parts[0] ?? ''));
		}

		if ($value !== '')
		{
			return substr($value, 0, 64);
		}
	}

	return 'unknown';
}

function phpbb_user_agent_is_declared_crawler(string $user_agent): bool
{
	if ($user_agent === '')
	{
		return false;
	}

	return (bool) preg_match(
		'#(?:bot|spider|crawler|google-read-aloud|bingbot|googlebot|baiduspider|duckduckbot|facebookexternalhit|twitterbot|meta-webindexer|chatgpt-user|slurp)#i',
		$user_agent
	);
}

function phpbb_emit_viewtopic_rate_limit_response(): void
{
	if (!headers_sent())
	{
		send_status_line(429, 'Too Many Requests');
		header_remove('Set-Cookie');
		header('Retry-After: 60');
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('X-Robots-Tag: noindex, nofollow');
		header('Content-Type: text/plain; charset=UTF-8');
	}

	echo 'Too many requests.';
	garbage_collection();
	exit_handler();
}

function phpbb_guard_anonymous_viewtopic_get_request(\phpbb\request\request_interface $request, \phpbb\user $user, \phpbb\cache\driver\driver_interface $cache, int $topic_id, int $post_id): void
{
	if (!phpbb_request_is_get($request) || ($topic_id <= 0 && $post_id <= 0))
	{
		return;
	}

	if ((int) ($user->data['user_id'] ?? ANONYMOUS) !== ANONYMOUS)
	{
		return;
	}

	$user_agent = substr((string) $request->server('HTTP_USER_AGENT', ''), 0, 240);
	if (phpbb_user_agent_is_declared_crawler($user_agent))
	{
		return;
	}

	$client_ip = phpbb_public_request_client_ip($request);
	$referer = trim((string) $request->server('HTTP_REFERER', ''));
	$now = time();
	$identity = hash('sha256', $client_ip . "\n" . $user_agent);
	$cache_key = '_freemitbbs_viewtopic_guard_' . substr($identity, 0, 32);
	$state = $cache->get($cache_key);
	if (!is_array($state))
	{
		$state = [];
	}

	if (!empty($state['blocked_until']) && (int) $state['blocked_until'] > $now)
	{
		phpbb_emit_viewtopic_rate_limit_response();
	}

	$bucket_10s = intdiv($now, 10);
	$bucket_60s = intdiv($now, 60);
	$count_10s = ((int) ($state['bucket_10s'] ?? -1) === $bucket_10s) ? (int) ($state['count_10s'] ?? 0) : 0;
	$count_60s = ((int) ($state['bucket_60s'] ?? -1) === $bucket_60s) ? (int) ($state['count_60s'] ?? 0) : 0;

	$count_10s++;
	$count_60s++;

	$limit_10s = $referer === '' ? 6 : 10;
	$limit_60s = $referer === '' ? 18 : 40;
	$blocked = $count_10s > $limit_10s || $count_60s > $limit_60s;

	$state = [
		'bucket_10s' => $bucket_10s,
		'count_10s' => $count_10s,
		'bucket_60s' => $bucket_60s,
		'count_60s' => $count_60s,
		'blocked_until' => $blocked ? ($now + 120) : 0,
	];
	$cache->put($cache_key, $state, 180);

	if ($blocked)
	{
		phpbb_emit_viewtopic_rate_limit_response();
	}
}

function phpbb_app_path_strips_public_sid(string $path_info): bool
{
	return in_array($path_info, [
		'/feed',
		'/feed/forums',
		'/feed/topics',
		'/feed/topics_active',
		'/help/faq',
		'/postlove/most-liked',
		'/toptopics/inline-preview/batch',
	], true)
		|| preg_match('#^/blog/entry/[0-9]+/share-image$#', $path_info)
		|| preg_match('#^/toptopics/inline-preview/[0-9]+$#', $path_info);
}
