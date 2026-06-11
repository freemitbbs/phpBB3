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
