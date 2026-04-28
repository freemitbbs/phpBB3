<?php
/**
*
* @package MoT DIM v1.2.0
* @copyright (c) 2024 - 2025 Mike-on-Tour
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace mot\dim\controller;

class mot_dim_acp
{
	public function __construct(protected \phpbb\config\config $config, protected \phpbb\db\driver\driver_interface $db, protected \phpbb\group\helper $group_helper,
								protected \phpbb\controller\helper $helper, protected \phpbb\language\language $language, protected \phpbb\extension\manager $phpbb_extension_manager,
								protected \phpbb\request\request_interface $request, protected \phpbb\template\template $template, protected \phpbb\user $user, protected $root_path,
								protected $php_ext)
	{
		$this->md_manager = $this->phpbb_extension_manager->create_extension_metadata_manager('mot/dim');
		$this->mot_dim_version = $this->md_manager->get_metadata('version');
	}

	public function settings()
	{
		$form_key = 'acp_mot_dim_settings';
		add_form_key($form_key);

		// Check for this function and include it if not existent since it is needed to convert user_id into usernames and vice versa for the protected members section
		if (!function_exists('user_get_id_name'))
		{
			include($this->root_path . 'includes/functions_user.' . $this->php_ext);
		}

		$action = $this->request->variable('action', '');
		switch ($action)
		{
			case 'submit':
				if (!check_form_key($form_key))
				{
					trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
				}

				// get the names of members to be protected and convert it to array of user_ids
				$protected_users_ids = [];
				$protected_users_names = $this->request->variable('mot_dim_protected_users', '', true);
				$username_arr = explode("\n", $protected_users_names);
				user_get_id_name($protected_users_ids, $username_arr);
				sort($protected_users_ids);

				$cron_unit = $this->request->variable('mot_dim_cron_unit', 0);
				$cron_interval = $this->request->variable('mot_dim_cron_interval', 0);
				$cron_interval = $cron_unit ? $cron_interval * 86400 : $cron_interval * 3600;

				$this->config->set('mot_dim_enable', $this->request->variable('mot_dim_enable', 0));
				$this->config->set('mot_dim_days_delete', $this->request->variable('mot_dim_days_delete', 0));
				$this->config->set('mot_dim_enable_sleeper', $this->request->variable('mot_dim_enable_sleeper', 0));
				$this->config->set('mot_dim_enable_zeropost', $this->request->variable('mot_dim_enable_zeropost', 0));
				$this->config->set('mot_dim_protected_users', json_encode($protected_users_ids));
				$this->config->set('mot_dim_protected_groups', json_encode($this->request->variable('mot_dim_protected_groups', [0])));
				$this->config->set('mot_dim_cron_gc', $cron_interval);
				$this->config->set('mot_dim_cron_unit', $cron_unit);

				trigger_error($this->language->lang('ACP_MOT_DIM_SETTING_SAVED') . adm_back_link($this->u_action));

				break;
		}

		// Get the user_ids of protected members and convert it to string for use in template
		$username_arr = [];
		$protected_users_ids = json_decode($this->config['mot_dim_protected_users']);
		user_get_id_name($protected_users_ids, $username_arr);
		sort($username_arr);
		$protected_users_names = implode("\n", $username_arr);

		// Get the group properties of those groups used as default
		$sql_ary = [
			'SELECT'	=> 'g.group_id, g.group_type, g.group_name, u.group_id',

			'FROM'		=> [GROUPS_TABLE	=> 'g',],

			'LEFT_JOIN'	=> [
					[
						'FROM'		=> [USERS_TABLE	=> 'u'],
						'ON'		=> 'g.group_id = u.group_id',
					],
			],

			'WHERE'		=> $this->db->sql_in_set('u.user_type', [USER_NORMAL, USER_INACTIVE]),

			'GROUP_BY'	=> 'u.group_id',

			'ORDER_BY'	=> 'g.group_type DESC, g.group_name ASC',
		];
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);
		$result = $this->db->sql_query($sql);
		$groups = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		$group_count = count($groups);
		$protected_groups = '';
		$protected_groups_arr = json_decode($this->config['mot_dim_protected_groups']);

		foreach ($groups as $option)
		{
			$selected = in_array($option['group_id'], $protected_groups_arr) ? ' selected' : '';
			$protected_groups .= '<option ' . (($option['group_type'] == GROUP_SPECIAL) ? ' class="sep"' : '') . ' value="' . $option['group_id'] . '"' . $selected . '>' . $this->group_helper->get_name($option['group_name']) . '</option>';
		}

		$cron_unit_select = '';
		$cron_units = [$this->language->lang('MOT_DIM_UNIT_HOUR'), $this->language->lang('MOT_DIM_UNIT_DAY')];
		for ($i = 0; $i < 2; $i++)
		{
			$selected = ($i == (int) $this->config['mot_dim_cron_unit']) ? ' selected' : '';
			$cron_unit_select .= '<option value="' . $i . '"' . $selected . '>' . $cron_units[$i] . '</option>';
		}

		$this->template->assign_vars([
			'ACP_MOT_DIM_ENABLE'					=> $this->config['mot_dim_enable'],
			'ACP_MOT_DIM_DAYS_DELETE'				=> $this->config['mot_dim_days_delete'],
			'ACP_MOT_DIM_PROTECTED_USERS'			=> $protected_users_names,
			'ACP_MOT_DIM_ENABLE_SLEEPER'			=> $this->config['mot_dim_enable_sleeper'],
			'ACP_MOT_DIM_ENABLE_ZEROPOST'			=> $this->config['mot_dim_enable_zeropost'],
			'ACP_MOT_DIM_GROUP_COUNT'				=> $group_count,
			'ACP_MOT_DIM_PROTECTED_GROUPS'			=> $protected_groups,
			'ACP_MOT_DIM_CRON_INTERVAL'				=> $this->config['mot_dim_cron_unit'] ? $this->config['mot_dim_cron_gc'] / 86400 : $this->config['mot_dim_cron_gc'] / 3600,
			'ACP_MOT_DIM_CRON_UNIT_SELECT'			=> $cron_unit_select,
			'ACP_MOT_DIM_LAST_CRON_RUN'				=> $this->config['mot_dim_cron_last_gc'] ? $this->user->format_date($this->config['mot_dim_cron_last_gc']) : '-',
			'ACP_MOT_DIM_VERSION_STRING'			=> $this->language->lang('ACP_MOT_DIM_VERSION', $this->mot_dim_version, date('Y')),
			'U_ACTION'								=> $this->u_action . '&amp;action=submit',
			'U_ACTION_TEST_SETTINGS'				=> $this->helper->route('mot_dim_result_check_controller', []),
		]);
	}


// --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

	/**
	 * Set custom form action.
	 *
	 * @param	$u_action	Custom form action
	 * @return	$this		This controller for chaining calls
	 */
	public function set_page_url(string $u_action) : object
	{
		$this->u_action = $u_action;

		return $this;
	}
}
