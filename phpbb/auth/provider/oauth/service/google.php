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

namespace phpbb\auth\provider\oauth\service;

use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\OAuth2\Service\Exception\InvalidAuthorizationStateException;

/**
 * Google OAuth service
 */
class google extends base
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var array */
	protected $user_info = [];

	/**
	 * Constructor.
	 *
	 * @param \phpbb\config\config				$config		Config object
	 * @param \phpbb\request\request_interface	$request	Request object
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\request\request_interface $request)
	{
		$this->config	= $config;
		$this->request	= $request;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_auth_scope()
	{
		return [
			'userinfo_email',
			'userinfo_profile',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_service_credentials()
	{
		return [
			'key'		=> $this->config['auth_oauth_google_key'],
			'secret'	=> $this->config['auth_oauth_google_secret'],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function perform_auth_login()
	{
		if (!($this->service_provider instanceof \OAuth\OAuth2\Service\Google))
		{
			throw new exception('AUTH_PROVIDER_OAUTH_ERROR_INVALID_SERVICE_TYPE');
		}

		try
		{
			// Get token and state
			$this->service_provider->requestAccessToken(
				$this->request->variable('code', ''),
				$this->request->variable('state', '')
			);
		}
		catch (InvalidAuthorizationStateException|TokenResponseException $e)
		{
			throw new exception('AUTH_PROVIDER_OAUTH_ERROR_REQUEST');
		}

		$result = $this->request_user_info();

		// Return the unique identifier
		return $result['id'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function perform_token_auth()
	{
		if (!($this->service_provider instanceof \OAuth\OAuth2\Service\Google))
		{
			throw new exception('AUTH_PROVIDER_OAUTH_ERROR_INVALID_SERVICE_TYPE');
		}

		$result = $this->request_user_info();

		// Return the unique identifier
		return $result['id'];
	}

	/**
	 * Returns the email from the last Google userinfo response.
	 *
	 * @return string
	 */
	public function get_user_email()
	{
		return !empty($this->user_info['email']) ? strtolower($this->user_info['email']) : '';
	}

	/**
	 * Returns whether the last Google userinfo email was verified.
	 *
	 * @return bool
	 */
	public function is_user_email_verified()
	{
		return !empty($this->user_info['verified_email']);
	}

	/**
	 * Requests and stores Google userinfo for the current token.
	 *
	 * @return array
	 */
	protected function request_user_info()
	{
		try
		{
			$this->user_info = (array) json_decode($this->service_provider->request('https://www.googleapis.com/oauth2/v1/userinfo'), true);
		}
		catch (\OAuth\Common\Exception\Exception $e)
		{
			throw new exception('AUTH_PROVIDER_OAUTH_ERROR_REQUEST');
		}

		if (empty($this->user_info['id']))
		{
			throw new exception('AUTH_PROVIDER_OAUTH_ERROR_REQUEST');
		}

		return $this->user_info;
	}
}
