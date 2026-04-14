<?php

namespace freemitbbs\riskwatch\service;

class scorer
{
	private const LEVEL_NORMAL = 0;
	private const LEVEL_WATCH = 1;
	private const LEVEL_HIGH = 2;
	private const LEVEL_CRITICAL = 3;
	private const ALERT_NOTIFICATION_TYPE = 'freemitbbs.riskwatch.notification.type.alert';

	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\log\log_interface $log;
	protected \phpbb\notification\manager $notification_manager;
	protected string $risk_state_table;
	protected string $risk_manual_table;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\log\log_interface $log,
		\phpbb\notification\manager $notification_manager,
		string $risk_state_table,
		string $risk_manual_table
	)
	{
		$this->config = $config;
		$this->db = $db;
		$this->log = $log;
		$this->notification_manager = $notification_manager;
		$this->risk_state_table = $risk_state_table;
		$this->risk_manual_table = $risk_manual_table;
	}

	public function refresh_candidates(int $batch_size = 500): int
	{
		$candidate_ids = $this->collect_candidate_user_ids(max(1, $batch_size));

		return $this->refresh_users($candidate_ids);
	}

	public function refresh_users(array $user_ids): int
	{
		$user_ids = $this->normalize_user_ids($user_ids);
		if (empty($user_ids))
		{
			return 0;
		}

		$now = time();
		$thresholds = $this->get_thresholds();
		$weights = $this->get_weights();
		$caps = $this->get_caps();
		$alert_cooldown = $this->get_int_config('riskwatch_alert_cooldown_seconds', 86400, 0);
		$reports_days = $this->get_int_config('riskwatch_reports_days', 30, 1);
		$unapproved_days = $this->get_int_config('riskwatch_unapproved_days', 30, 1);
		$ignore_new_reporters_days = $this->get_int_config('riskwatch_ignore_new_reporters_days', 0, 0);

		$user_rows = $this->collect_user_rows($user_ids);
		$open_reporters = $this->collect_open_reporters($user_ids, $reports_days, $ignore_new_reporters_days, $now);
		$unapproved_posts = $this->collect_unapproved_posts($user_ids, $unapproved_days, $now);
		$active_bans = $this->collect_active_bans($user_ids, $now);
		$manual_adjustments = $this->collect_manual_adjustments($user_ids, $now);
		$existing_rows = $this->collect_existing_state($user_ids);

		$updates = [];
		foreach ($user_ids as $user_id)
		{
			$user_row = $user_rows[$user_id] ?? null;
			$warnings = (int) ($user_row['user_warnings'] ?? 0);
			$login_attempts = (int) ($user_row['user_login_attempts'] ?? 0);
			$reporters_count = (int) ($open_reporters[$user_id] ?? 0);
			$unapproved_count = (int) ($unapproved_posts[$user_id] ?? 0);
			$active_ban = !empty($active_bans[$user_id]) ? 1 : 0;
			$manual_delta = (int) ($manual_adjustments[$user_id] ?? 0);

			$reporters_capped = min($caps['reporters'], max(0, $reporters_count));
			$unapproved_capped = min($caps['unapproved'], max(0, $unapproved_count));
			$login_capped = min($caps['login'], max(0, $login_attempts));

			$warning_points = $warnings * $weights['warnings'];
			$report_points = $reporters_capped * $weights['reports'];
			$unapproved_points = $unapproved_capped * $weights['unapproved'];
			$login_points = $login_capped * $weights['login'];
			$ban_points = $active_ban * $weights['ban'];

			$risk_score = (int) ($warning_points + $report_points + $unapproved_points + $login_points + $ban_points + $manual_delta);
			$risk_level = $this->resolve_level($risk_score, $thresholds);
			$details_hash = md5(json_encode([
				'risk_score' => $risk_score,
				'risk_level' => $risk_level,
				'warnings' => $warnings,
				'open_reporters_30d' => $reporters_count,
				'unapproved_posts_30d' => $unapproved_count,
				'login_attempts' => $login_attempts,
				'active_ban' => $active_ban,
				'manual_adjustment' => $manual_delta,
			]));

			$existing = $existing_rows[$user_id] ?? null;
			$existing_level = (int) ($existing['risk_level'] ?? self::LEVEL_NORMAL);
			$last_alert_level = (int) ($existing['last_alert_level'] ?? self::LEVEL_NORMAL);
			$last_alert_time = (int) ($existing['last_alert_time'] ?? 0);

			$emit_alert = false;
			if ($risk_level > self::LEVEL_NORMAL && $risk_level > $existing_level)
			{
				if ($alert_cooldown <= 0
					|| $last_alert_level !== $risk_level
					|| $last_alert_time <= ($now - $alert_cooldown))
				{
					$emit_alert = true;
					$this->log_admin_alert(
						$user_row['username'] ?? ('user#' . $user_id),
						$risk_score,
						$risk_level,
						$warning_points,
						$report_points,
						$unapproved_points,
						$login_points,
						$ban_points,
						$manual_delta
					);
					$this->notify_admin_alert(
						$user_id,
						$risk_score,
						$risk_level,
						$warning_points,
						$report_points,
						$unapproved_points,
						$login_points,
						$ban_points,
						$manual_delta,
						$now
					);
					$last_alert_level = $risk_level;
					$last_alert_time = $now;
				}
			}

			$updates[$user_id] = [
				'user_id' => $user_id,
				'risk_score' => $risk_score,
				'risk_level' => $risk_level,
				'warnings_count' => $warnings,
				'open_reporters_30d' => $reporters_count,
				'unapproved_posts_30d' => $unapproved_count,
				'login_attempts' => $login_attempts,
				'active_ban' => $active_ban,
				'manual_adjustment' => $manual_delta,
				'warning_points' => $warning_points,
				'report_points' => $report_points,
				'unapproved_points' => $unapproved_points,
				'login_points' => $login_points,
				'ban_points' => $ban_points,
				'details_json' => json_encode([
					'warnings' => $warnings,
					'open_reporters_30d' => $reporters_count,
					'unapproved_posts_30d' => $unapproved_count,
					'login_attempts' => $login_attempts,
					'active_ban' => $active_ban,
					'manual_adjustment' => $manual_delta,
					'weights' => $weights,
					'caps' => $caps,
					'alert_emitted' => $emit_alert,
				]),
				'computed_time' => $now,
				'last_alert_level' => $last_alert_level,
				'last_alert_time' => $last_alert_time,
				'last_alert_hash' => $details_hash,
			];
		}

		$this->db->sql_transaction('begin');

		foreach ($updates as $user_id => $row)
		{
			if (isset($existing_rows[$user_id]))
			{
				$sql = 'UPDATE ' . $this->risk_state_table . '
					SET ' . $this->db->sql_build_array('UPDATE', $this->without_user_id($row)) . '
					WHERE user_id = ' . (int) $user_id;
				$this->db->sql_query($sql);
			}
			else
			{
				$sql = 'INSERT INTO ' . $this->risk_state_table . ' ' . $this->db->sql_build_array('INSERT', $row);
				$this->db->sql_query($sql);
			}
		}

		$this->db->sql_transaction('commit');

		return count($updates);
	}

	public function get_top_risky_users(int $limit = 50, int $offset = 0): array
	{
		$limit = max(1, min(500, $limit));
		$offset = max(0, $offset);

		$sql = 'SELECT rs.*, u.username, u.user_colour
			FROM ' . $this->risk_state_table . ' rs
			LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = rs.user_id
			WHERE rs.risk_score <> 0
				OR rs.risk_level <> 0
			ORDER BY rs.risk_score DESC, rs.computed_time DESC';
		$result = $this->db->sql_query_limit($sql, $limit, $offset);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	public function count_tracked_users(): int
	{
		$sql = 'SELECT COUNT(*) AS row_count
			FROM ' . $this->risk_state_table . '
			WHERE risk_score <> 0
				OR risk_level <> 0';
		$result = $this->db->sql_query($sql);
		$count = (int) $this->db->sql_fetchfield('row_count');
		$this->db->sql_freeresult($result);

		return $count;
	}

	protected function collect_candidate_user_ids(int $batch_size): array
	{
		$user_ids = [];
		$now = time();
		$reports_cutoff = $now - ($this->get_int_config('riskwatch_reports_days', 30, 1) * 86400);
		$unapproved_cutoff = $now - ($this->get_int_config('riskwatch_unapproved_days', 30, 1) * 86400);

		$queries = [
			'SELECT user_id
				FROM ' . USERS_TABLE . '
				WHERE user_warnings > 0
					OR user_login_attempts > 0',
			'SELECT p.poster_id AS user_id
				FROM ' . REPORTS_TABLE . ' r
				INNER JOIN ' . POSTS_TABLE . ' p ON p.post_id = r.post_id
				WHERE r.report_closed = 0
					AND r.report_time >= ' . (int) $reports_cutoff . '
				GROUP BY p.poster_id',
			'SELECT p.poster_id AS user_id
				FROM ' . POSTS_TABLE . ' p
				WHERE ' . $this->db->sql_in_set('p.post_visibility', [ITEM_UNAPPROVED, ITEM_REAPPROVE]) . '
					AND p.post_time >= ' . (int) $unapproved_cutoff . '
				GROUP BY p.poster_id',
			'SELECT ban_userid AS user_id
				FROM ' . BANLIST_TABLE . '
				WHERE ban_userid > 0
					AND ban_exclude = 0
					AND (ban_end = 0 OR ban_end > ' . (int) $now . ')
				GROUP BY ban_userid',
			'SELECT user_id
				FROM ' . $this->risk_manual_table . '
				WHERE is_active = 1
					AND (expires_at = 0 OR expires_at > ' . (int) $now . ')
				GROUP BY user_id',
			'SELECT user_id
				FROM ' . $this->risk_state_table . '
				WHERE risk_score <> 0
					OR risk_level <> 0',
		];

		foreach ($queries as $sql)
		{
			$result = $this->db->sql_query_limit($sql, $batch_size);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$user_id = (int) ($row['user_id'] ?? 0);
				if ($user_id > ANONYMOUS)
				{
					$user_ids[$user_id] = true;
				}
			}
			$this->db->sql_freeresult($result);
		}

		$user_ids = array_keys($user_ids);
		sort($user_ids);

		if (count($user_ids) > $batch_size)
		{
			$user_ids = array_slice($user_ids, 0, $batch_size);
		}

		return $user_ids;
	}

	protected function collect_user_rows(array $user_ids): array
	{
		if (empty($user_ids))
		{
			return [];
		}

		$sql = 'SELECT user_id, username, user_warnings, user_login_attempts
			FROM ' . USERS_TABLE . '
			WHERE ' . $this->db->sql_in_set('user_id', $user_ids);
		$result = $this->db->sql_query($sql);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['user_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function collect_open_reporters(array $user_ids, int $days, int $ignore_new_reporters_days, int $now): array
	{
		if (empty($user_ids))
		{
			return [];
		}

		$report_cutoff = $now - ($days * 86400);
		$sql = 'SELECT p.poster_id AS user_id, COUNT(DISTINCT r.user_id) AS reporter_count
			FROM ' . REPORTS_TABLE . ' r
			INNER JOIN ' . POSTS_TABLE . ' p ON p.post_id = r.post_id';

		if ($ignore_new_reporters_days > 0)
		{
			$reporter_cutoff = $now - ($ignore_new_reporters_days * 86400);
			$sql .= '
			INNER JOIN ' . USERS_TABLE . ' ru ON ru.user_id = r.user_id
				AND ru.user_regdate <= ' . (int) $reporter_cutoff;
		}

		$sql .= '
			WHERE r.report_closed = 0
				AND r.report_time >= ' . (int) $report_cutoff . '
				AND ' . $this->db->sql_in_set('p.poster_id', $user_ids) . '
			GROUP BY p.poster_id';

		$result = $this->db->sql_query($sql);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['user_id']] = (int) $row['reporter_count'];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function collect_unapproved_posts(array $user_ids, int $days, int $now): array
	{
		if (empty($user_ids))
		{
			return [];
		}

		$cutoff = $now - ($days * 86400);
		$sql = 'SELECT p.poster_id AS user_id, COUNT(p.post_id) AS post_count
			FROM ' . POSTS_TABLE . ' p
			WHERE ' . $this->db->sql_in_set('p.post_visibility', [ITEM_UNAPPROVED, ITEM_REAPPROVE]) . '
				AND p.post_time >= ' . (int) $cutoff . '
				AND ' . $this->db->sql_in_set('p.poster_id', $user_ids) . '
			GROUP BY p.poster_id';
		$result = $this->db->sql_query($sql);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['user_id']] = (int) $row['post_count'];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function collect_active_bans(array $user_ids, int $now): array
	{
		if (empty($user_ids))
		{
			return [];
		}

		$sql = 'SELECT ban_userid AS user_id
			FROM ' . BANLIST_TABLE . '
			WHERE ban_userid > 0
				AND ban_exclude = 0
				AND (ban_end = 0 OR ban_end > ' . (int) $now . ')
				AND ' . $this->db->sql_in_set('ban_userid', $user_ids) . '
			GROUP BY ban_userid';
		$result = $this->db->sql_query($sql);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['user_id']] = true;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function collect_manual_adjustments(array $user_ids, int $now): array
	{
		if (empty($user_ids))
		{
			return [];
		}

		$sql = 'SELECT user_id, COALESCE(SUM(delta), 0) AS manual_delta
			FROM ' . $this->risk_manual_table . '
			WHERE is_active = 1
				AND (expires_at = 0 OR expires_at > ' . (int) $now . ')
				AND ' . $this->db->sql_in_set('user_id', $user_ids) . '
			GROUP BY user_id';
		$result = $this->db->sql_query($sql);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['user_id']] = (int) $row['manual_delta'];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function collect_existing_state(array $user_ids): array
	{
		if (empty($user_ids))
		{
			return [];
		}

		$sql = 'SELECT user_id, risk_level, last_alert_level, last_alert_time
			FROM ' . $this->risk_state_table . '
			WHERE ' . $this->db->sql_in_set('user_id', $user_ids);
		$result = $this->db->sql_query($sql);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['user_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function resolve_level(int $score, array $thresholds): int
	{
		if ($score >= $thresholds['critical'])
		{
			return self::LEVEL_CRITICAL;
		}

		if ($score >= $thresholds['high'])
		{
			return self::LEVEL_HIGH;
		}

		if ($score >= $thresholds['watch'])
		{
			return self::LEVEL_WATCH;
		}

		return self::LEVEL_NORMAL;
	}

	protected function log_admin_alert(
		string $username,
		int $risk_score,
		int $risk_level,
		int $warning_points,
		int $report_points,
		int $unapproved_points,
		int $login_points,
		int $ban_points,
		int $manual_adjustment
	): void
	{
		$this->log->add(
			'admin',
			ANONYMOUS,
			'',
			'LOG_RISKWATCH_ALERT',
			time(),
			[
				$username,
				(string) $risk_score,
				$this->level_name($risk_level),
				'W:' . $warning_points . ' R:' . $report_points . ' U:' . $unapproved_points . ' L:' . $login_points . ' B:' . $ban_points . ' M:' . $manual_adjustment,
			]
		);
	}

	protected function notify_admin_alert(
		int $user_id,
		int $risk_score,
		int $risk_level,
		int $warning_points,
		int $report_points,
		int $unapproved_points,
		int $login_points,
		int $ban_points,
		int $manual_adjustment,
		int $alert_time
	): void
	{
		try
		{
			$this->notification_manager->add_notifications(self::ALERT_NOTIFICATION_TYPE, [
				'alert_item_id' => (int) sprintf('%u', crc32((string) ($user_id . '|' . $risk_level . '|' . $alert_time))),
				'risk_user_id' => $user_id,
				'risk_score' => $risk_score,
				'risk_level' => $risk_level,
				'risk_level_label' => ucfirst($this->level_name($risk_level)),
				'warning_points' => $warning_points,
				'report_points' => $report_points,
				'unapproved_points' => $unapproved_points,
				'login_points' => $login_points,
				'ban_points' => $ban_points,
				'manual_adjustment' => $manual_adjustment,
				'alert_time' => $alert_time,
			]);
		}
		catch (\Throwable $e)
		{
			// Keep scoring resilient even if notification delivery fails.
		}
	}

	protected function without_user_id(array $row): array
	{
		unset($row['user_id']);
		return $row;
	}

	protected function normalize_user_ids(array $user_ids): array
	{
		$user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids), static function ($user_id) {
			return $user_id > ANONYMOUS;
		})));
		sort($user_ids);

		return $user_ids;
	}

	protected function get_thresholds(): array
	{
		$watch = $this->get_int_config('riskwatch_threshold_watch', 15, 0);
		$high = $this->get_int_config('riskwatch_threshold_high', 30, $watch);
		$critical = $this->get_int_config('riskwatch_threshold_critical', 50, $high);

		return [
			'watch' => $watch,
			'high' => $high,
			'critical' => $critical,
		];
	}

	protected function get_weights(): array
	{
		return [
			'warnings' => $this->get_int_config('riskwatch_weight_warnings', 8, 0),
			'reports' => $this->get_int_config('riskwatch_weight_reports', 2, 0),
			'unapproved' => $this->get_int_config('riskwatch_weight_unapproved', 2, 0),
			'login' => $this->get_int_config('riskwatch_weight_login', 1, 0),
			'ban' => $this->get_int_config('riskwatch_weight_ban', 30, 0),
		];
	}

	protected function get_caps(): array
	{
		return [
			'reporters' => $this->get_int_config('riskwatch_cap_reporters', 8, 0),
			'unapproved' => $this->get_int_config('riskwatch_cap_unapproved', 10, 0),
			'login' => $this->get_int_config('riskwatch_cap_login', 10, 0),
		];
	}

	protected function level_name(int $level): string
	{
		switch ($level)
		{
			case self::LEVEL_CRITICAL:
				return 'critical';
			case self::LEVEL_HIGH:
				return 'high';
			case self::LEVEL_WATCH:
				return 'watch';
			default:
				return 'normal';
		}
	}

	protected function get_int_config(string $key, int $default, int $min = 0): int
	{
		$value = isset($this->config[$key]) ? (int) $this->config[$key] : $default;

		return max($min, $value);
	}
}
