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

class topstats_install extends \phpbb\db\migration\migration
{
	/**
	 * @return array
	 */
	public static function depends_on()
	{
		return array('\phpbb\db\migration\data\v330\v330');
	}

	public function update_data()
	{
		return array(
			// Recent / ticker
			array('config.add', array('tsrat_number', 5)),
			array('config.add', array('tsrat_numberp', 5)),
			array('config.add', array('tsrat_numberc', 5)),
			array('config.add', array('ts_jsscroll', 0)),
			array('config.add', array('ts_jsspeed', 400)),
			array('config.add', array('ts_jsinterval', 4000)),
			array('config.add', array('ts_jsscroll_direction', 1)),
			array('config.add', array('ts_jsscroll_pause', 1)),
			array('config.add', array('ts_jsscroll_navigation', 1)),
			array('config.add', array('display_top_recent_index', 0)),
			array('config.add', array('display_top_recent_portal', 0)),
			array('config.add', array('display_top_recent_custom', 0)),

			// Top stats (index)
			array('config.add', array('tsmvt_number', 5)),
			array('config.add', array('tsmrt_number', 5)),
			array('config.add', array('tsmau_number', 5)),
			array('config.add', array('tsmaf_number', 5)),
			array('config.add', array('tslvb_number', 5)),
			array('config.add', array('tslru_number', 5)),
			array('config.add', array('tsttm_number', 5)),
			array('config.add', array('tstlm_number', 5)),
			array('config.add', array('display_top_stats_index', 0)),

			// Top stats (portal)
			array('config.add', array('tsmvt_numberp', 5)),
			array('config.add', array('tsmrt_numberp', 5)),
			array('config.add', array('tsmau_numberp', 5)),
			array('config.add', array('tsmaf_numberp', 5)),
			array('config.add', array('tslvb_numberp', 5)),
			array('config.add', array('tslru_numberp', 5)),
			array('config.add', array('tsttm_numberp', 5)),
			array('config.add', array('tstlm_numberp', 5)),
			array('config.add', array('display_top_stats_portal', 0)),

			// Top stats (custom)
			array('config.add', array('tsmvt_numberc', 5)),
			array('config.add', array('tsmrt_numberc', 5)),
			array('config.add', array('tsmau_numberc', 5)),
			array('config.add', array('tsmaf_numberc', 5)),
			array('config.add', array('tslvb_numberc', 5)),
			array('config.add', array('tslru_numberc', 5)),
			array('config.add', array('tsttm_numberc', 5)),
			array('config.add', array('tstlm_numberc', 5)),
			array('config.add', array('display_top_stats_custom', 0)),
			
			// Top Poster (custom)
			array('config.add', array('tsttm_numbertp', 5)),
			array('config.add', array('tstlm_numbertp', 5)),
			array('config.add', array('display_top_stats_topposter', 0)),

			// Exclusions
			array('config.add', array('topstats_excluded_users', '')),

			// Category under “Extensions”
			array('module.add', array('acp', 'ACP_CAT_DOT_MODS', 'TOP_STATS')),

			// Recent active
			array('module.add', array('acp', 'TOP_STATS', array(
				'module_basename'	=> '\stoker\topstats\acp\topstats_module',
				'module_langname'	=> 'ACP_TS_RECENT',
				'module_mode'		=> 'recent',
			))),

			// Top stats blocks
			array('module.add', array('acp', 'TOP_STATS', array(
				'module_basename'	=> '\stoker\topstats\acp\topstats_module',
				'module_langname'	=> 'ACP_TS_STATS',
				'module_mode'		=> 'stats',
			))),
			
			// Top Poster
			array('module.add', array('acp', 'TOP_STATS', array(
				'module_basename'	=> '\stoker\topstats\acp\topstats_module',
				'module_langname'	=> 'ACP_TS_TOPPOSTER',
				'module_mode'		=> 'topposter',
			))),
		);
	}
}
