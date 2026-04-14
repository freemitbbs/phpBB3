<?php

namespace freemitbbs\riskwatch\acp;

class acp_riskwatch_module
{
	private const FORM_KEY = 'freemitbbs/riskwatch';
	private const MAX_MANUAL_ABS_DELTA = 100000;
	private const MAX_MANUAL_EXPIRES_DAYS = 3650;

	private const SETTINGS = [
		['key' => 'riskwatch_refresh_seconds', 'default' => 300, 'min' => 30, 'max' => 86400],
		['key' => 'riskwatch_refresh_batch_size', 'default' => 500, 'min' => 1, 'max' => 10000],
		['key' => 'riskwatch_alert_cooldown_seconds', 'default' => 86400, 'min' => 0, 'max' => 604800],
		['key' => 'riskwatch_reports_days', 'default' => 30, 'min' => 1, 'max' => 365],
		['key' => 'riskwatch_unapproved_days', 'default' => 30, 'min' => 1, 'max' => 365],
		['key' => 'riskwatch_ignore_new_reporters_days', 'default' => 0, 'min' => 0, 'max' => 365],
		['key' => 'riskwatch_threshold_watch', 'default' => 15, 'min' => 0, 'max' => 100000],
		['key' => 'riskwatch_threshold_high', 'default' => 30, 'min' => 0, 'max' => 100000],
		['key' => 'riskwatch_threshold_critical', 'default' => 50, 'min' => 0, 'max' => 100000],
		['key' => 'riskwatch_weight_warnings', 'default' => 8, 'min' => 0, 'max' => 1000],
		['key' => 'riskwatch_weight_reports', 'default' => 2, 'min' => 0, 'max' => 1000],
		['key' => 'riskwatch_weight_unapproved', 'default' => 2, 'min' => 0, 'max' => 1000],
		['key' => 'riskwatch_weight_login', 'default' => 1, 'min' => 0, 'max' => 1000],
		['key' => 'riskwatch_weight_ban', 'default' => 30, 'min' => 0, 'max' => 1000],
		['key' => 'riskwatch_cap_reporters', 'default' => 8, 'min' => 0, 'max' => 1000],
		['key' => 'riskwatch_cap_unapproved', 'default' => 10, 'min' => 0, 'max' => 1000],
		['key' => 'riskwatch_cap_login', 'default' => 10, 'min' => 0, 'max' => 1000],
	];

	public string $tpl_name;
	public string $page_title;
	public string $u_action;

	public function main($id, $mode)
	{
		global $phpbb_container, $phpbb_root_path, $phpEx;

		/** @var \phpbb\config\config $config */
		$config = $phpbb_container->get('config');
		/** @var \phpbb\template\template $template */
		$template = $phpbb_container->get('template');
		/** @var \phpbb\request\request $request */
		$request = $phpbb_container->get('request');
		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');
		/** @var \phpbb\user $user */
		$user = $phpbb_container->get('user');
		/** @var \phpbb\db\driver\driver_interface $db */
		$db = $phpbb_container->get('dbal.conn');
		/** @var \phpbb\log\log_interface $log */
		$log = $phpbb_container->get('log');
		/** @var \phpbb\pagination $pagination */
		$pagination = $phpbb_container->get('pagination');
		/** @var \freemitbbs\riskwatch\service\scorer $scorer */
		$scorer = $phpbb_container->get('freemitbbs.riskwatch.scorer');

		if (!function_exists('get_username_string'))
		{
			include_once($phpbb_root_path . 'includes/functions_content.' . $phpEx);
		}

		$risk_manual_table = (string) $phpbb_container->getParameter('tables.freemitbbs.riskwatch.user_risk_manual');

		$language->add_lang('info_acp_riskwatch', 'freemitbbs/riskwatch');
		$language->add_lang('riskwatch', 'freemitbbs/riskwatch');

		$this->tpl_name = 'acp_riskwatch';
		$this->page_title = 'ACP_RISKWATCH';

		add_form_key(self::FORM_KEY);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$submitted = $request->variable('riskwatch', ['' => 0]);
			foreach (self::SETTINGS as $setting)
			{
				$value = (int) ($submitted[$setting['key']] ?? $setting['default']);
				$value = max((int) $setting['min'], min((int) $setting['max'], $value));
				$config->set($setting['key'], (string) $value);
			}

			$this->normalize_thresholds($config);

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$messages = [];
		$errors = [];

		if ($request->is_set_post('recompute'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$updated = $scorer->refresh_candidates((int) ($config['riskwatch_refresh_batch_size'] ?? 500));
			$messages[] = $language->lang('RISKWATCH_RECOMPUTE_RESULT', (int) $updated);
		}

		if ($request->is_set_post('manual_add'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$result = $this->add_manual_adjustment($request, $db, $scorer, $language, $user, $log, $risk_manual_table);
			if ($result['ok'])
			{
				$messages[] = $result['message'];
			}
			else
			{
				$errors[] = $result['message'];
			}
		}

		if ($request->is_set_post('manual_disable'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$result = $this->disable_manual_adjustment($request, $db, $scorer, $language, $user, $log, $risk_manual_table);
			if ($result['ok'])
			{
				$messages[] = $result['message'];
			}
			else
			{
				$errors[] = $result['message'];
			}
		}

		$start = $request->variable('start', 0);
		$per_page = max(10, min(100, (int) ($config['topics_per_page'] ?? 25)));
		$total = $scorer->count_tracked_users();
		$start = $pagination->validate_start($start, $per_page, $total);
		$rows = $scorer->get_top_risky_users($per_page, $start);
		$base_url = $this->u_action;
		$pagination->generate_template_pagination($base_url, 'pagination', 'start', $total, $per_page, $start);

		foreach ($rows as $row)
		{
			$template->assign_block_vars('risk_rows', [
				'USERNAME_FULL' => get_username_string(
					'full',
					(int) $row['user_id'],
					(string) $row['username'],
					(string) $row['user_colour']
				),
				'RISK_SCORE' => (int) $row['risk_score'],
				'RISK_LEVEL_LABEL' => $language->lang('RISKWATCH_LEVEL_' . strtoupper($this->level_name((int) $row['risk_level']))),
				'WARNINGS_COUNT' => (int) $row['warnings_count'],
				'OPEN_REPORTERS_30D' => (int) $row['open_reporters_30d'],
				'UNAPPROVED_POSTS_30D' => (int) $row['unapproved_posts_30d'],
				'LOGIN_ATTEMPTS' => (int) $row['login_attempts'],
				'ACTIVE_BAN' => ((int) $row['active_ban']) ? $language->lang('YES') : $language->lang('NO'),
				'MANUAL_ADJUSTMENT' => (int) $row['manual_adjustment'],
				'COMPUTED_TIME' => ((int) $row['computed_time'] > 0) ? $user->format_date((int) $row['computed_time']) : '-',
			]);
		}

		$manual_rows = $this->get_active_manual_adjustments($db, $risk_manual_table);
		foreach ($manual_rows as $row)
		{
			$expires_at = (int) $row['expires_at'];
			$is_expired = ($expires_at > 0 && $expires_at <= time());
			$template->assign_block_vars('manual_rows', [
				'MANUAL_ID' => (int) $row['manual_id'],
				'USERNAME_FULL' => get_username_string(
					'full',
					(int) $row['user_id'],
					(string) $row['username'],
					(string) $row['user_colour']
				),
				'DELTA' => (int) $row['delta'],
				'REASON' => (string) $row['reason'],
				'CREATED_BY_FULL' => get_username_string(
					'full',
					(int) $row['created_by'],
					(string) $row['created_by_username'],
					(string) $row['created_by_colour']
				),
				'CREATED_TIME' => ((int) $row['created_time'] > 0) ? $user->format_date((int) $row['created_time']) : '-',
				'EXPIRES_TIME' => ($expires_at > 0) ? $user->format_date($expires_at) : $language->lang('NEVER'),
				'STATUS_LABEL' => $language->lang($is_expired ? 'RISKWATCH_MANUAL_STATUS_EXPIRED' : 'RISKWATCH_MANUAL_STATUS_ACTIVE'),
				'S_IS_EXPIRED' => $is_expired,
			]);
		}

		foreach ($messages as $message)
		{
			$template->assign_block_vars('risk_messages', [
				'MESSAGE' => $message,
			]);
		}

		foreach ($errors as $message)
		{
			$template->assign_block_vars('risk_errors', [
				'MESSAGE' => $message,
			]);
		}

		$template->assign_vars([
			'U_ACTION' => $this->u_action,
			'PAGE_NUMBER' => $pagination->on_page($total, $per_page, $start),
			'S_RISKWATCH_ROWS' => !empty($rows),
			'RISKWATCH_TOTAL' => $total,
			'S_MANUAL_ROWS' => !empty($manual_rows),
			'MANUAL_USER_ID' => (int) $request->variable('manual_user_id', 0),
			'MANUAL_DELTA' => (int) $request->variable('manual_delta', 0),
			'MANUAL_REASON' => (string) $request->variable('manual_reason', '', true),
			'MANUAL_EXPIRES_DAYS' => (int) $request->variable('manual_expires_days', 0),
		]);

		foreach (self::SETTINGS as $setting)
		{
			$key = $setting['key'];
			$template->assign_block_vars('settings', [
				'NAME' => $key,
				'LABEL' => $language->lang(strtoupper($key)),
				'EXPLAIN' => $language->lang(strtoupper($key) . '_EXPLAIN'),
				'VALUE' => isset($config[$key]) ? $config[$key] : $setting['default'],
			]);
		}
	}

	protected function add_manual_adjustment(
		\phpbb\request\request $request,
		\phpbb\db\driver\driver_interface $db,
		\freemitbbs\riskwatch\service\scorer $scorer,
		\phpbb\language\language $language,
		\phpbb\user $user,
		\phpbb\log\log_interface $log,
		string $risk_manual_table
	): array
	{
		$target_user_id = (int) $request->variable('manual_user_id', 0);
		$delta = (int) $request->variable('manual_delta', 0);
		$reason = trim((string) $request->variable('manual_reason', '', true));
		$expires_days = max(0, min(self::MAX_MANUAL_EXPIRES_DAYS, (int) $request->variable('manual_expires_days', 0)));

		if ($target_user_id <= ANONYMOUS)
		{
			return [
				'ok' => false,
				'message' => $language->lang('RISKWATCH_ERR_MANUAL_USER_REQUIRED'),
			];
		}

		$target_user = $this->get_user_row($db, $target_user_id);
		if ($target_user === null)
		{
			return [
				'ok' => false,
				'message' => $language->lang('RISKWATCH_ERR_MANUAL_USER_NOT_FOUND', $target_user_id),
			];
		}

		if ($delta === 0)
		{
			return [
				'ok' => false,
				'message' => $language->lang('RISKWATCH_ERR_MANUAL_DELTA_REQUIRED'),
			];
		}

		$delta = max(-self::MAX_MANUAL_ABS_DELTA, min(self::MAX_MANUAL_ABS_DELTA, $delta));

		if ($reason === '')
		{
			return [
				'ok' => false,
				'message' => $language->lang('RISKWATCH_ERR_MANUAL_REASON_REQUIRED'),
			];
		}

		$reason = $this->truncate_text($reason, 255);
		$expires_at = ($expires_days > 0) ? (time() + ($expires_days * 86400)) : 0;

		$sql = 'INSERT INTO ' . $risk_manual_table . ' ' . $db->sql_build_array('INSERT', [
			'user_id' => $target_user_id,
			'delta' => $delta,
			'reason' => $reason,
			'created_by' => (int) $user->data['user_id'],
			'created_time' => time(),
			'expires_at' => $expires_at,
			'is_active' => 1,
		]);
		$db->sql_query($sql);

		$scorer->refresh_users([$target_user_id]);

		$log->add(
			'admin',
			(int) $user->data['user_id'],
			(string) $user->ip,
			'LOG_RISKWATCH_MANUAL_ADD',
			time(),
			[
				(string) $target_user['username'],
				(string) $delta,
				$reason,
				($expires_at > 0) ? $user->format_date($expires_at) : $language->lang('NEVER'),
			]
		);

		return [
			'ok' => true,
			'message' => $language->lang('RISKWATCH_MANUAL_ADD_SUCCESS', (string) $target_user['username'], $delta),
		];
	}

	protected function disable_manual_adjustment(
		\phpbb\request\request $request,
		\phpbb\db\driver\driver_interface $db,
		\freemitbbs\riskwatch\service\scorer $scorer,
		\phpbb\language\language $language,
		\phpbb\user $user,
		\phpbb\log\log_interface $log,
		string $risk_manual_table
	): array
	{
		$manual_id = (int) $request->variable('manual_disable', 0);
		if ($manual_id <= 0)
		{
			return [
				'ok' => false,
				'message' => $language->lang('RISKWATCH_ERR_MANUAL_NOT_FOUND'),
			];
		}

		$sql = 'SELECT m.manual_id, m.user_id, m.delta, m.reason, u.username
			FROM ' . $risk_manual_table . ' m
			LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = m.user_id
			WHERE m.manual_id = ' . $manual_id . '
				AND m.is_active = 1';
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$row)
		{
			return [
				'ok' => false,
				'message' => $language->lang('RISKWATCH_ERR_MANUAL_NOT_FOUND'),
			];
		}

		$sql = 'UPDATE ' . $risk_manual_table . '
			SET is_active = 0
			WHERE manual_id = ' . $manual_id . '
				AND is_active = 1';
		$db->sql_query($sql);

		$user_id = (int) $row['user_id'];
		if ($user_id > ANONYMOUS)
		{
			$scorer->refresh_users([$user_id]);
		}

		$log->add(
			'admin',
			(int) $user->data['user_id'],
			(string) $user->ip,
			'LOG_RISKWATCH_MANUAL_DISABLE',
			time(),
			[
				(string) ($row['username'] ?? ('user#' . $user_id)),
				(string) ((int) ($row['delta'] ?? 0)),
				(string) ($row['reason'] ?? ''),
			]
		);

		return [
			'ok' => true,
			'message' => $language->lang('RISKWATCH_MANUAL_DISABLE_SUCCESS', (string) ($row['username'] ?? ('#' . $user_id))),
		];
	}

	protected function get_user_row(\phpbb\db\driver\driver_interface $db, int $user_id): ?array
	{
		$sql = 'SELECT user_id, username, user_colour
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . $user_id;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		return $row ?: null;
	}

	protected function get_active_manual_adjustments(
		\phpbb\db\driver\driver_interface $db,
		string $risk_manual_table,
		int $limit = 200
	): array
	{
		$sql = 'SELECT m.*, u.username, u.user_colour,
				cu.username AS created_by_username, cu.user_colour AS created_by_colour
			FROM ' . $risk_manual_table . ' m
			LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = m.user_id
			LEFT JOIN ' . USERS_TABLE . ' cu ON cu.user_id = m.created_by
			WHERE m.is_active = 1
			ORDER BY m.created_time DESC, m.manual_id DESC';
		$result = $db->sql_query_limit($sql, max(1, $limit));

		$rows = [];
		while ($row = $db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$db->sql_freeresult($result);

		return $rows;
	}

	protected function normalize_thresholds(\phpbb\config\config $config): void
	{
		$watch = max(0, (int) ($config['riskwatch_threshold_watch'] ?? 15));
		$high = max($watch, (int) ($config['riskwatch_threshold_high'] ?? 30));
		$critical = max($high, (int) ($config['riskwatch_threshold_critical'] ?? 50));

		$config->set('riskwatch_threshold_watch', (string) $watch);
		$config->set('riskwatch_threshold_high', (string) $high);
		$config->set('riskwatch_threshold_critical', (string) $critical);
	}

	protected function level_name(int $level): string
	{
		switch ($level)
		{
			case 3:
				return 'critical';
			case 2:
				return 'high';
			case 1:
				return 'watch';
			default:
				return 'normal';
		}
	}

	protected function truncate_text(string $value, int $max_length): string
	{
		if ($max_length <= 0)
		{
			return '';
		}

		if (function_exists('mb_substr'))
		{
			return mb_substr($value, 0, $max_length, 'UTF-8');
		}

		return substr($value, 0, $max_length);
	}
}
