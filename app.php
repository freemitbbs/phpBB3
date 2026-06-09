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

/* @var $symfony_request \phpbb\symfony_request */
$symfony_request = $phpbb_container->get('symfony_request');
if ($request->is_set('sid', \phpbb\request\request_interface::GET) && preg_match('#^/blog/entry/[0-9]+/share-image$#', $symfony_request->getPathInfo()))
{
	header_remove('Set-Cookie');
	header('Location: ' . $symfony_request->getBaseUrl() . $symfony_request->getPathInfo(), true, 301);
	header('Cache-Control: public, max-age=3600, s-maxage=86400');
	header('CDN-Cache-Control: public, max-age=86400');
	header('Cloudflare-CDN-Cache-Control: public, max-age=86400');
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
