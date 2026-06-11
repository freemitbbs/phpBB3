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

/**
*/

/**
* @ignore
*/
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_request_canonical.' . $phpEx);

/* @var $symfony_request \phpbb\symfony_request */
$symfony_request = $phpbb_container->get('symfony_request');
$path_info = $symfony_request->getPathInfo();
if (phpbb_app_path_strips_public_sid($path_info))
{
	phpbb_strip_sid_and_redirect_current_get_request($request, $symfony_request, [
		'Cache-Control' => 'public, max-age=3600, s-maxage=86400',
		'CDN-Cache-Control' => 'public, max-age=86400',
		'Cloudflare-CDN-Cache-Control' => 'public, max-age=86400',
	]);
}
if (phpbb_request_is_get($request) && strpos($path_info, '/collapse/') === 0 && !$request->is_ajax())
{
	header_remove('Set-Cookie');
	http_response_code(403);
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	header('X-Robots-Tag: noindex, nofollow');
	exit;
}

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('app');

/* @var $http_kernel \Symfony\Component\HttpKernel\HttpKernel */
$http_kernel = $phpbb_container->get('http_kernel');

$response = $http_kernel->handle($symfony_request);
$response->send();
$http_kernel->terminate($symfony_request, $response);
