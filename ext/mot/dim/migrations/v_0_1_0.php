<?php
/**
*
* @package MoT DIM v0.2.0
* @copyright (c) 2024 Mike-on-Tour
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace mot\dim\migrations;

class v_0_1_0 extends \phpbb\db\migration\migration
{

	/**
	* If our config variable already exists in the db
	* skip this migration.
	*/
	public function effectively_installed()
	{
		return isset($this->config['mot_dim_enable']);
	}

	public function update_data()
	{
		return [
			// Add the config variable we want to be able to set
			['config.add', ['mot_dim_enable', false]],
			['config.add', ['mot_dim_days_delete', 5]],
			['config.add', ['mot_dim_enable_sleeper', true]],
			['config.add', ['mot_dim_enable_zeropost', true]],
			['config.add', ['mot_dim_protected_users', json_encode([])]],
			['config.add', ['mot_dim_protected_groups', json_encode([])]],
			['config.add', ['mot_dim_cron_unit', 1, true]],		// 1 means a day, 0 means an hour
			['config.add', ['mot_dim_cron_gc', 86400, true]],
			['config.add', ['mot_dim_cron_last_gc', 0, true]],

			// Add a parent module (ACP_MOT_DIM) to the Extensions tab (ACP_CAT_DOT_MODS)
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_MOT_DIM'
			]],

			// Add our settings_module to the parent module (ACP_MOT_DIM)
			['module.add', [
				'acp',
				'ACP_MOT_DIM',
				[
					'module_basename'	=> '\mot\dim\acp\mot_dim_acp_module',
					'module_langname'	=> 'ACP_MOT_DIM_SETTINGS',
					'module_mode'		=> 'settings',
					'module_auth'		=> 'ext_mot/dim && acl_a_board',
				],
			]],
		];
	}
}
