<?php

namespace freemitbbs\toptopics\service;

class reputation
{
	private const DEFAULT_MIN_REPUTATION_DISLIKE = 10;
	private const DEFAULT_MIN_REPUTATION_REPORT = 50;
	private const DEFAULT_LIKE_WEIGHT = 6.0;
	private const DEFAULT_DISLIKE_WEIGHT = 6.0;
	private const DEFAULT_FLAG_WEIGHT = 12.0;
	private const CONTENT_WEIGHT_SCALE = 24.0;
	private const CONTENT_LENGTH_CAP = 40000;
	private const CONTENT_LENGTH_SCALE = 500.0;
	private const PER_POST_CONTENT_CAP = 4000;

	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected string $likes_table;
	protected string $dislikes_table;
	protected string $reputation_table;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		string $likes_table,
		string $dislikes_table,
		string $reputation_table
	)
	{
		$this->config = $config;
		$this->db = $db;
		$this->likes_table = $likes_table;
		$this->dislikes_table = $dislikes_table;
		$this->reputation_table = $reputation_table;
	}

	public function get_score(int $user_id): int
	{
		$scores = $this->get_scores([$user_id]);

		return $scores[$user_id] ?? 0;
	}

	public function get_scores(array $user_ids): array
	{
		$user_ids = $this->normalize_user_ids($user_ids);

		if (empty($user_ids))
		{
			return [];
		}

		$this->ensure_users($user_ids);
		$rows = $this->get_rows($user_ids);
		$scores = array_fill_keys($user_ids, 0);

		foreach ($rows as $user_id => $row)
		{
			$scores[$user_id] = (int) round((float) ($row['reputation_score'] ?? 0));
		}

		return $scores;
	}

	public function get_required_dislike_score(): int
	{
		return $this->get_int_config('toptopics_min_reputation_dislike', self::DEFAULT_MIN_REPUTATION_DISLIKE, 0, 1000000);
	}

	public function get_required_report_score(): int
	{
		return $this->get_int_config('toptopics_min_reputation_report', self::DEFAULT_MIN_REPUTATION_REPORT, 0, 1000000);
	}

	public function can_dislike(int $user_id): bool
	{
		$required = $this->get_required_dislike_score();

		return $required <= 0 || $this->get_score($user_id) >= $required;
	}

	public function can_report(int $user_id): bool
	{
		$required = $this->get_required_report_score();

		return $required <= 0 || $this->get_score($user_id) >= $required;
	}

	public function refresh_user(int $user_id): void
	{
		$this->refresh_users([$user_id]);
	}

	public function refresh_users(array $user_ids): void
	{
		$user_ids = $this->normalize_user_ids($user_ids);

		if (empty($user_ids))
		{
			return;
		}

		$this->store_rows($this->build_materialized_rows($user_ids));
	}

	public function invalidate_user(int $user_id): void
	{
		$this->invalidate_users([$user_id]);
	}

	public function invalidate_users(array $user_ids): void
	{
		$user_ids = $this->normalize_user_ids($user_ids);

		if (empty($user_ids))
		{
			return;
		}

		$sql = 'DELETE FROM ' . $this->reputation_table . '
			WHERE ' . $this->db->sql_in_set('user_id', $user_ids);
		$this->db->sql_query($sql);
	}

	protected function ensure_users(array $user_ids): void
	{
		$existing_rows = $this->get_rows($user_ids);
		$missing_user_ids = array_values(array_diff($user_ids, array_keys($existing_rows)));

		if (!empty($missing_user_ids))
		{
			$this->refresh_users($missing_user_ids);
		}
	}

	protected function get_rows(array $user_ids): array
	{
		$sql = 'SELECT user_id, reputation_score, computed_time,
				likes_received, dislikes_received, open_flags_received, content_length_total
			FROM ' . $this->reputation_table . '
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

	protected function build_materialized_rows(array $user_ids): array
	{
		$metrics = [];
		foreach ($user_ids as $user_id)
		{
			$metrics[(int) $user_id] = [
				'likes_received' => 0,
				'dislikes_received' => 0,
				'open_flags_received' => 0,
				'content_length_total' => 0,
			];
		}

		foreach ($this->collect_content_lengths($user_ids) as $user_id => $content_length)
		{
			$metrics[$user_id]['content_length_total'] = $content_length;
		}

		foreach ($this->collect_post_event_counts($this->likes_table, 'pl', $user_ids) as $user_id => $count)
		{
			$metrics[$user_id]['likes_received'] = $count;
		}

		foreach ($this->collect_post_event_counts($this->dislikes_table, 'pd', $user_ids) as $user_id => $count)
		{
			$metrics[$user_id]['dislikes_received'] = $count;
		}

		foreach ($this->collect_report_counts($user_ids) as $user_id => $count)
		{
			$metrics[$user_id]['open_flags_received'] = $count;
		}

		$content_weight = $this->get_float_config('toptopics_content_weight', 0.35, 0.0, 10.0);
		$computed_time = time();
		$rows = [];

		foreach ($metrics as $user_id => $user_metrics)
		{
			$content_signal = log(1.0 + (min(self::CONTENT_LENGTH_CAP, $user_metrics['content_length_total']) / self::CONTENT_LENGTH_SCALE));
			$content_score = $content_signal * $content_weight * self::CONTENT_WEIGHT_SCALE;
			$like_score = log(1.0 + $user_metrics['likes_received']) * self::DEFAULT_LIKE_WEIGHT;
			$dislike_penalty = log(1.0 + $user_metrics['dislikes_received']) * self::DEFAULT_DISLIKE_WEIGHT;
			$flag_penalty = log(1.0 + $user_metrics['open_flags_received']) * self::DEFAULT_FLAG_WEIGHT;
			$rows[$user_id] = [
				'user_id' => (int) $user_id,
				'reputation_score' => (int) round($content_score + $like_score - $dislike_penalty - $flag_penalty),
				'computed_time' => $computed_time,
				'likes_received' => (int) $user_metrics['likes_received'],
				'dislikes_received' => (int) $user_metrics['dislikes_received'],
				'open_flags_received' => (int) $user_metrics['open_flags_received'],
				'content_length_total' => (int) $user_metrics['content_length_total'],
			];
		}

		return $rows;
	}

	protected function store_rows(array $rows): void
	{
		if (empty($rows))
		{
			return;
		}

		$this->invalidate_users(array_keys($rows));
		$this->db->sql_multi_insert($this->reputation_table, array_values($rows));
	}

	protected function collect_content_lengths(array $user_ids): array
	{
		$content_lengths = [];
		$sql = 'SELECT p.poster_id, p.post_text
			FROM ' . TOPICS_TABLE . ' t
			INNER JOIN ' . POSTS_TABLE . ' p
				ON p.topic_id = t.topic_id
			WHERE ' . $this->db->sql_in_set('p.poster_id', $user_ids) . '
				AND p.post_visibility = ' . ITEM_APPROVED . '
				AND t.topic_type <> ' . ITEM_MOVED . '
				AND t.topic_visibility = ' . ITEM_APPROVED;
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$poster_id = (int) $row['poster_id'];
			$content_lengths[$poster_id] = ($content_lengths[$poster_id] ?? 0)
				+ min(self::PER_POST_CONTENT_CAP, $this->calculate_quality_content_length((string) ($row['post_text'] ?? '')));
		}
		$this->db->sql_freeresult($result);

		return $content_lengths;
	}

	protected function calculate_quality_content_length(string $text): int
	{
		$text = $this->strip_quote_blocks($text);

		$text = preg_replace('#\[(?:img|attachment)(?:=[^\]]*)?(?::[a-z0-9]+)?\].*?\[/(?:img|attachment)(?::[a-z0-9]+)?\]#isu', ' ', $text);
		$text = preg_replace('#\[url(?:=[^\]]*)?(?::[a-z0-9]+)?\].*?\[/url(?::[a-z0-9]+)?\]#isu', ' ', $text);
		$text = preg_replace('#https?://\S+|www\.\S+#iu', ' ', $text);
		$text = preg_replace('#\[/?[a-z][a-z0-9_=-]*(?:[^\]]*)?(?::[a-z0-9]+)?\]#iu', ' ', $text);
		$text = strip_tags((string) $text);
		$text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		if (preg_match_all('/[\p{L}\p{N}]/u', $text, $matches) === false)
		{
			return 0;
		}

		return count($matches[0]);
	}

	protected function strip_quote_blocks(string $text): string
	{
		$pattern = '#\[quote(?:=[^\]]*)?(?::[a-z0-9]+)?\].*?\[/quote(?::[a-z0-9]+)?\]#isu';
		for ($i = 0; $i < 10; $i++)
		{
			$stripped = preg_replace($pattern, ' ', $text);
			if ($stripped === null || $stripped === $text)
			{
				break;
			}

			$text = $stripped;
		}

		return $text;
	}

	protected function collect_post_event_counts(string $event_table, string $alias, array $user_ids): array
	{
		$sql = 'SELECT p.poster_id, COUNT(*) AS event_count
			FROM ' . $event_table . ' ' . $alias . '
			INNER JOIN ' . POSTS_TABLE . ' p
				ON p.post_id = ' . $alias . '.post_id
			WHERE ' . $this->db->sql_in_set('p.poster_id', $user_ids) . '
				AND p.post_visibility = ' . ITEM_APPROVED . '
			GROUP BY p.poster_id';
		$result = $this->db->sql_query($sql);
		$counts = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$counts[(int) $row['poster_id']] = (int) $row['event_count'];
		}
		$this->db->sql_freeresult($result);

		return $counts;
	}

	protected function collect_report_counts(array $user_ids): array
	{
		$sql = 'SELECT p.poster_id, COUNT(*) AS event_count
			FROM ' . REPORTS_TABLE . ' r
			INNER JOIN ' . POSTS_TABLE . ' p
				ON p.post_id = r.post_id
			WHERE ' . $this->db->sql_in_set('p.poster_id', $user_ids) . '
				AND p.post_visibility = ' . ITEM_APPROVED . '
				AND r.report_closed = 0
			GROUP BY p.poster_id';
		$result = $this->db->sql_query($sql);
		$counts = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$counts[(int) $row['poster_id']] = (int) $row['event_count'];
		}
		$this->db->sql_freeresult($result);

		return $counts;
	}

	protected function normalize_user_ids(array $user_ids): array
	{
		return array_values(array_unique(array_filter(array_map('intval', $user_ids), static function ($user_id) {
			return $user_id > 0 && $user_id !== ANONYMOUS;
		})));
	}

	protected function get_int_config(string $key, int $default, ?int $min = null, ?int $max = null): int
	{
		$value = isset($this->config[$key]) ? (int) $this->config[$key] : $default;

		if ($min !== null)
		{
			$value = max($min, $value);
		}

		if ($max !== null)
		{
			$value = min($max, $value);
		}

		return $value;
	}

	protected function get_float_config(string $key, float $default, ?float $min = null, ?float $max = null): float
	{
		$value = isset($this->config[$key]) ? (float) $this->config[$key] : $default;

		if ($min !== null)
		{
			$value = max($min, $value);
		}

		if ($max !== null)
		{
			$value = min($max, $value);
		}

		return $value;
	}

	protected function get_text_length_sql(string $column_name): string
	{
		switch ($this->db->get_sql_layer())
		{
			case 'mssql_odbc':
			case 'mssqlnative':
				return 'LEN(' . $column_name . ')';

			case 'sqlite3':
			case 'oracle':
				return 'LENGTH(' . $column_name . ')';

			case 'mysqli':
			case 'mysql4':
			case 'postgres':
			default:
				return 'CHAR_LENGTH(' . $column_name . ')';
		}
	}
}
