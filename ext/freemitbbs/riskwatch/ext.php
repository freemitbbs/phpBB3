<?php

namespace freemitbbs\riskwatch;

class ext extends \phpbb\extension\base
{
	private const MIN_PHP_VERSION = '8.1.0';
	private const MIN_PHPBB_VERSION = '3.3.0';
	private const ALERT_NOTIFICATION_TYPE = 'freemitbbs.riskwatch.notification.type.alert';

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

		return empty($errors) ? true : $errors;
	}

	public function enable_step($old_state)
	{
		switch ($old_state)
		{
			case '':
				$this->container->get('notification_manager')->enable_notifications(self::ALERT_NOTIFICATION_TYPE);
				return 'notifications';

			default:
				return parent::enable_step($old_state);
		}
	}

	public function disable_step($old_state)
	{
		switch ($old_state)
		{
			case '':
				$this->container->get('notification_manager')->disable_notifications(self::ALERT_NOTIFICATION_TYPE);
				return 'notifications';

			default:
				return parent::disable_step($old_state);
		}
	}

	public function purge_step($old_state)
	{
		switch ($old_state)
		{
			case '':
				try
				{
					$this->container->get('notification_manager')->purge_notifications(self::ALERT_NOTIFICATION_TYPE);
				}
				catch (\phpbb\notification\exception $e)
				{
					// no-op
				}

				return 'notifications';

			default:
				return parent::purge_step($old_state);
		}
	}
}
