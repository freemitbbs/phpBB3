<?php
/**
*
* @package MoT DIM v1.2.0
* @copyright (c) 2024 - 2025 Mike-on-Tour
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace mot\dim\cron\task;

class mot_dim_cron extends \phpbb\cron\task\base
{
	public function __construct(protected \phpbb\config\config $config, protected \phpbb\db\driver\driver_interface $db, protected \phpbb\log\log $log, protected \phpbb\user $user,
								protected $root_path, protected $php_ext)
	{
	}

	/**
	* Runs this cron task.
	*
	*/
	public function run()
	{
		$this->check_users_to_delete();
	}

	/**
	* Returns whether this cron task can run, given current board configuration.
	*
	*/
	public function is_runnable() : bool
	{
		return (bool) $this->config['mot_dim_enable'];
	}

	/**
	* Returns whether this cron task should run now, because enough time has passed since it was last run.
	*
	*/
	public function should_run() : bool
	{
		return $this->config['mot_dim_cron_last_gc'] < time() - $this->config['mot_dim_cron_gc'];
	}

/* ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- */

	private function check_users_to_delete()
	{
		// Check if extension is active
		if ($this->config['mot_dim_enable'])
		{
			// Compute the threshold timestamp
			$threshold = time() - ($this->config['mot_dim_days_delete'] * 86400);

			// Get the protected users and groups
			$protected_users = json_decode($this->config['mot_dim_protected_users']);
			$protected_groups = json_decode($this->config['mot_dim_protected_groups']);

			$sql_ary = [
				'SELECT'		=> 'u.user_id, u.user_type, u.user_lastvisit, u.user_posts',

				'FROM'			=> [USERS_TABLE	=> 'u'],

				'LEFT_JOIN'		=> [
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
			// Add a LIMIT to the query to ascertain that following queries using the IN clause do not abort because of too many user_ids to handle (an internet search revealed that 1000 seems to be the limit)
			$sql .= ' LIMIT 1000';
			$result = $this->db->sql_query($sql);
			$user_id_result = $this->db->sql_fetchrowset($result);
			$this->db->sql_freeresult($result);

			// Check if we have any users to delete
			if (!empty($user_id_result))
			{
				// Check whether the phpBB function to handle user deletion is available and load it if not
				if (!function_exists('user_delete'))
				{
					include($this->root_path . 'includes/functions_user.' . $this->php_ext);
				}

				// Reformat the array holding the users
				$user_ids = [
					'inactive'		=> [],
					'sleeper'		=> [],
					'zeroposter'	=> [],
				];

				foreach ($user_id_result as $row)
				{
					if ($row['user_type'] == USER_INACTIVE)
					{
						$user_ids['inactive'][] = (int) $row['user_id'];
					}

					if ($row['user_type'] == USER_NORMAL && $row['user_lastvisit'] == 0)
					{
						$user_ids['sleeper'][] = (int) $row['user_id'];
					}

					if ($row['user_type'] == USER_NORMAL && $row['user_lastvisit'] > 0 && $row['user_posts'] == 0)
					{
						$user_ids['zeroposter'][] = (int) $row['user_id'];
					}
				}

				foreach ($user_ids as $key => $row)
				{
					if (!empty($row))
					{
						$log_key = '';
						// Get usernames for log purposes
						$username_ary = [];
						user_get_id_name($row, $username_ary);

						// Delete users
						user_delete('retain', $row);

						// Log the action
						$log_key = $key == 'inactive' ? 'MOT_DIM_LOG_INACTIVE_DEL' : $log_key;
						$log_key = $key == 'sleeper' ? 'MOT_DIM_LOG_SLEEPER_DEL' : $log_key;
						$log_key = $key == 'zeroposter' ? 'MOT_DIM_LOG_ZEROPOSTER_DEL' : $log_key;
						$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, $log_key, false, [count($row), implode(', ', $username_ary)]);
					}
				}
			}

			// Set the last run config variable
			$this->config->set('mot_dim_cron_last_gc', time());
		}
	}
}
