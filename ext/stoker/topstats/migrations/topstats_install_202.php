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

namespace stoker\topstats\migrations;

class topstats_install_202 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array('\stoker\topstats\migrations\topstats_install');
	}
	
	public function update_data()
	{
		return array(
			array('config.add', array('ts_recent_cache_time', 0)),
			array('config.add', array('topstats_excluded_forums', '')),
			array('config.add', array('ts_topposter_cache_time', -1)),
		);
	}
}
