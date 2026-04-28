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

class topstats_install_206 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array('\stoker\topstats\migrations\topstats_install_202');
	}
	
	public function update_data()
	{
		return array(
			// Add permissions
			array('permission.add', array('u_topstats_view', true)),
			array('permission.add', array('u_topposters_view', true)),
			
			// Grant to REGISTERED by default
			array('permission.permission_set', array('REGISTERED', 'u_topstats_view', 'group')),
			array('permission.permission_set', array('REGISTERED', 'u_topposters_view', 'group')),
			
			// Grant to ADMINISTRATORS by default
			array('permission.permission_set', array('ADMINISTRATORS', 'u_topstats_view', 'group')),
			array('permission.permission_set', array('ADMINISTRATORS', 'u_topposters_view', 'group')),
			
			// Grant to GLOBAL_MODERATORS by default
			array('permission.permission_set', array('GLOBAL_MODERATORS', 'u_topstats_view', 'group')),
			array('permission.permission_set', array('GLOBAL_MODERATORS', 'u_topposters_view', 'group')),
		);
	}
}
