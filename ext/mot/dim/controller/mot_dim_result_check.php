<?php
/**
*
* @package MoT DIM v1.2.0
* @copyright (c) 2024 - 2025 Mike-on-Tour
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace mot\dim\controller;

class mot_dim_result_check
{
	public function __construct(protected \phpbb\config\config $config, protected \phpbb\db\driver\driver_interface $db, protected \phpbb\controller\helper $helper,
								protected \phpbb\language\language $language, protected \phpbb\pagination $pagination, protected \phpbb\request\request_interface $request,
								protected \phpbb\template\template $template, protected \phpbb\user $user)
	{
	}

	public function main()
	{
		// set parameters for pagination
		$start = $this->request->variable('start', 0);
		$limit = 25;

		// Compute the threshold timestamp
		$threshold = time() - ($this->config['mot_dim_days_delete'] * 86400);

		// Get the protected users and groups
		$protected_users = json_decode($this->config['mot_dim_protected_users']);
		$protected_groups = json_decode($this->config['mot_dim_protected_groups']);

		$sql_ary = [
			'SELECT'		=> 'u.user_id, u.username, u.user_type, u.group_id, u.user_lastvisit, u.user_regdate, u.user_posts, u.user_lastpost_time, g.group_type, g.group_name',

			'FROM'			=> [USERS_TABLE		=> 'u',],

			'LEFT_JOIN'		=> [
						[
							'FROM'		=> [GROUPS_TABLE		=> 'g'],
							'ON'		=> 'g.group_id = u.group_id',
						],
						[
							'FROM'		=> [POSTS_TABLE		=> 'p'],
							'ON'		=> 'p.poster_id = u.user_id',
						],
			],

			'WHERE'			=> '(
									(u.user_type = '. USER_INACTIVE . ' AND u.user_inactive_reason = ' . INACTIVE_REGISTER . ')' .
									($this->config['mot_dim_enable_sleeper'] ? ' OR (u.user_type = '. USER_NORMAL . ' AND u.user_lastvisit = 0)' : '') .
									($this->config['mot_dim_enable_zeropost'] ? ' OR (u.user_type = '. USER_NORMAL . ' AND u.user_lastvisit > 0 AND u.user_posts = 0)' : '') . '
								)
								AND (u.user_lastpost_time = 0 OR (u.user_lastpost_time > 0 AND (p.post_visibility > 0 OR p.post_visibility IS NULL)))
								AND u.user_regdate < ' . (int) $threshold .
								(!empty($protected_users) ? ' AND (' . $this->db->sql_in_set('u.user_id', $protected_users, true) . ')' : '') .
								(!empty($protected_groups) ? ' AND (' . $this->db->sql_in_set('u.group_id', $protected_groups, true) . ')' : ''),

			'GROUP_BY'		=> 'u.user_id',

			'ORDER_BY'		=> 'u.user_id ASC',
		];
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);
		$result = $this->db->sql_query($sql);
		$user_result = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		// Get the total users count
		$total_users = count($user_result);

		// And now do the query with the pagination values
		$result = $this->db->sql_query_limit($sql, $limit, $start);
		$user_result = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		// Prepare table content
		foreach ($user_result as $row)
		{
			$reason = ($row['user_type'] == USER_INACTIVE) ? $this->language->lang('MOT_DIM_NOT_ACTIVATED') : '';
			$reason = ($row['user_type'] == USER_NORMAL && $row['user_lastvisit'] == 0) ? $this->language->lang('MOT_DIM_SLEEPER') : $reason;
			$reason = ($row['user_type'] == USER_NORMAL && $row['user_lastvisit'] > 0 && $row['user_posts'] == 0) ? $this->language->lang('MOT_DIM_ZEROPOSTER') : $reason;
			$this->template->assign_block_vars('users', [
				'USERNAME'		=> $row['username'],
				'GROUP'			=> $row['group_type'] == GROUP_SPECIAL ? $this->language->lang('G_' . $row['group_name']) : $row['group_name'],
				'REG_DATE'		=> $this->user->format_date($row['user_regdate']),
				'REASON'		=> $reason,
			]);
		}

		//base url for pagination
		$base_url = $this->helper->route('mot_dim_result_check_controller');

		// Load pagination
		$start = $this->pagination->validate_start($start, $limit, $total_users);
		$this->pagination->generate_template_pagination($base_url, 'pagination', 'start', $total_users, $limit, $start);

		$this->template->assign_vars([
			'MOT_DIM_TOTAL_USERS'		=> $this->language->lang('MOT_DIM_TOTAL_USERS', $total_users),
		]);

		// output page
		page_header($this->language->lang('MOT_DIM_CHECK_RESULT'));

		$this->template->set_filenames([
			'body' => 'mot_dim_result_check.html',
		]);

		page_footer();
	}
}
