<?php
/**
 *
 * Top Stats extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 stoker - https://phpbb3bbcodes.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

declare(strict_types=1);

namespace stoker\topstats;

/**
 * Extension class for custom enable/disable/purge actions
 */
class ext extends \phpbb\extension\base
{
	/**
	 * Enable extension if phpBB version requirement is met
	 *
	 * @return bool
	 */
	public function is_enableable()
	{
		$config = $this->container->get('config');
		$user = $this->container->get('user');

		$required_phpbb = '3.3.0';
		$required_php = '7.4.0';

		$phpbb_ok = phpbb_version_compare($config['version'], $required_phpbb, '>=');
		$php_ok = version_compare(PHP_VERSION, $required_php, '>=');
		$topstats_ok = !isset($config['tsjss_speed']);

		if (method_exists($user, 'add_lang_ext'))
		{
			$user->add_lang_ext('stoker/topstats', 'common');
		}

		if (!$phpbb_ok)
		{
			trigger_error($user->lang('TS_REQUIRE_PHPBB', $required_phpbb, $config['version']), E_USER_WARNING);
		}

		if (!$php_ok)
		{
			trigger_error($user->lang('TS_REQUIRE_PHP', $required_php, PHP_VERSION), E_USER_WARNING);
		}

		if (!$topstats_ok)
		{
			trigger_error($user->lang('TS_REQUIRE_REMOVE'), E_USER_WARNING);
		}

		return $phpbb_ok && $php_ok && $topstats_ok;
	}
}
