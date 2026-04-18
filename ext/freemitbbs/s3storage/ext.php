<?php

namespace freemitbbs\s3storage;

class ext extends \phpbb\extension\base
{
	private const MIN_PHP_VERSION = '8.1.0';
	private const MIN_PHPBB_VERSION = '3.3.0';

	public function is_enableable()
	{
		$errors = [];

		if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<'))
		{
			$errors[] = 'This extension requires PHP ' . self::MIN_PHP_VERSION . ' or higher. You are running PHP ' . PHP_VERSION . '.';
		}

		if (phpbb_version_compare(PHPBB_VERSION, self::MIN_PHPBB_VERSION, '<'))
		{
			$errors[] = 'This extension requires phpBB ' . self::MIN_PHPBB_VERSION . ' or higher. You are running phpBB ' . PHPBB_VERSION . '.';
		}

		if (!function_exists('curl_init'))
		{
			$errors[] = 'This extension requires the PHP cURL extension.';
		}

		return empty($errors) ? true : $errors;
	}
}
