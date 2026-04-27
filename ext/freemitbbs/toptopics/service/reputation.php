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
	private const POST_QUALITY_SYNC_BATCH_SIZE = 500;

	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected string $likes_table;
	protected string $dislikes_table;
	protected string $post_quality_table;
	protected string $reputation_table;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		string $likes_table,
		string $dislikes_table,
		string $post_quality_table,
		string $reputation_table
	)
	{
		$this->config = $config;
		$this->db = $db;
		$this->likes_table = $likes_table;
		$this->dislikes_table = $dislikes_table;
		$this->post_quality_table = $post_quality_table;
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

		$rows = $this->ensure_users($user_ids);
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

	public function sync_post(int $post_id): void
	{
		$this->sync_posts([$post_id]);
	}

	public function sync_posts(array $post_ids): void
	{
		$post_ids = $this->normalize_post_ids($post_ids);

		if (empty($post_ids))
		{
			return;
		}

		if (count($post_ids) > self::POST_QUALITY_SYNC_BATCH_SIZE)
		{
			foreach (array_chunk($post_ids, self::POST_QUALITY_SYNC_BATCH_SIZE) as $post_id_batch)
			{
				$this->sync_posts($post_id_batch);
			}
			return;
		}

		$old_rows = $this->get_post_quality_rows($post_ids);
		$new_rows = $this->build_post_quality_rows($post_ids);
		$deltas = [];

		foreach ($post_ids as $post_id)
		{
			$old_row = $old_rows[$post_id] ?? null;
			$new_row = $new_rows[$post_id] ?? null;

			if ($old_row && (int) $old_row['is_counted'])
			{
				$this->add_content_delta($deltas, (int) $old_row['poster_id'], -1 * (int) $old_row['quality_length']);
			}

			if ($new_row && (int) $new_row['is_counted'])
			{
				$this->add_content_delta($deltas, (int) $new_row['poster_id'], (int) $new_row['quality_length']);
			}
		}

		$this->delete_post_quality_rows($post_ids);

		if (!empty($new_rows))
		{
			$this->db->sql_multi_insert($this->post_quality_table, array_values($new_rows));
		}

		$this->refresh_users_with_content_deltas($deltas);
	}

	public function sync_topic(int $topic_id): void
	{
		if ($topic_id <= 0)
		{
			return;
		}

		$sql = 'SELECT post_id
			FROM ' . POSTS_TABLE . '
			WHERE topic_id = ' . $topic_id;
		$result = $this->db->sql_query($sql);
		$post_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$post_ids[] = (int) $row['post_id'];
		}
		$this->db->sql_freeresult($result);

		$this->sync_posts($post_ids);
	}

	public function remove_posts(array $post_ids, array $fallback_user_ids = []): void
	{
		$post_ids = $this->normalize_post_ids($post_ids);
		$fallback_user_ids = $this->normalize_user_ids($fallback_user_ids);

		if (empty($post_ids))
		{
			if (!empty($fallback_user_ids))
			{
				$this->refresh_users($fallback_user_ids);
			}
			return;
		}

		if (count($post_ids) > self::POST_QUALITY_SYNC_BATCH_SIZE)
		{
			foreach (array_chunk($post_ids, self::POST_QUALITY_SYNC_BATCH_SIZE) as $post_id_batch)
			{
				$this->remove_posts($post_id_batch, $fallback_user_ids);
			}
			return;
		}

		$old_rows = $this->get_post_quality_rows($post_ids);
		$deltas = [];

		foreach ($old_rows as $old_row)
		{
			if ((int) $old_row['is_counted'])
			{
				$this->add_content_delta($deltas, (int) $old_row['poster_id'], -1 * (int) $old_row['quality_length']);
			}
		}

		$this->delete_post_quality_rows($post_ids);
		$this->refresh_users_with_content_deltas($deltas, $fallback_user_ids);
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

	protected function ensure_users(array $user_ids): array
	{
		$existing_rows = $this->get_rows($user_ids);
		$missing_user_ids = array_values(array_diff($user_ids, array_keys($existing_rows)));

		if (!empty($missing_user_ids))
		{
			$this->refresh_users($missing_user_ids);
			return $this->get_rows($user_ids);
		}

		return $existing_rows;
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

	protected function build_materialized_rows(array $user_ids, ?array $content_length_totals = null): array
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

		$content_length_totals = $content_length_totals ?? $this->get_known_content_lengths($user_ids);
		foreach ($content_length_totals as $user_id => $content_length)
		{
			if (isset($metrics[$user_id]))
			{
				$metrics[$user_id]['content_length_total'] = max(0, (int) $content_length);
			}
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
		$sql = 'SELECT poster_id, SUM(quality_length) AS content_length
			FROM ' . $this->post_quality_table . '
			WHERE is_counted = 1
				AND ' . $this->db->sql_in_set('poster_id', $user_ids) . '
			GROUP BY poster_id';
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$content_lengths[(int) $row['poster_id']] = (int) $row['content_length'];
		}
		$this->db->sql_freeresult($result);

		return $content_lengths;
	}

	protected function get_known_content_lengths(array $user_ids): array
	{
		$rows = $this->get_rows($user_ids);
		$content_lengths = [];
		$missing_user_ids = [];

		foreach ($user_ids as $user_id)
		{
			if (isset($rows[$user_id]))
			{
				$content_lengths[$user_id] = (int) $rows[$user_id]['content_length_total'];
			}
			else
			{
				$missing_user_ids[] = $user_id;
			}
		}

		if (!empty($missing_user_ids))
		{
			$content_lengths += $this->collect_content_lengths($missing_user_ids);
		}

		return $content_lengths;
	}

	protected function refresh_users_with_content_deltas(array $deltas, array $additional_user_ids = []): void
	{
		$content_deltas = [];
		foreach ($deltas as $user_id => $delta)
		{
			$user_id = (int) $user_id;
			$delta = (int) $delta;

			if ($delta !== 0 && $this->is_countable_user($user_id))
			{
				$content_deltas[$user_id] = ($content_deltas[$user_id] ?? 0) + $delta;
			}
		}

		$user_ids = $this->normalize_user_ids(array_merge(array_keys($content_deltas), $additional_user_ids));
		if (empty($user_ids))
		{
			return;
		}

		$existing_rows = $this->get_rows($user_ids);
		$missing_user_ids = [];
		$content_lengths = [];

		foreach ($user_ids as $user_id)
		{
			if (isset($existing_rows[$user_id]))
			{
				$content_lengths[$user_id] = max(0, (int) $existing_rows[$user_id]['content_length_total'] + (int) ($content_deltas[$user_id] ?? 0));
			}
			else
			{
				$missing_user_ids[] = $user_id;
			}
		}

		if (!empty($missing_user_ids))
		{
			$content_lengths += $this->collect_content_lengths($missing_user_ids);
		}

		$this->store_rows($this->build_materialized_rows($user_ids, $content_lengths));
	}

	protected function get_post_quality_rows(array $post_ids): array
	{
		$sql = 'SELECT post_id, poster_id, quality_length, is_counted
			FROM ' . $this->post_quality_table . '
			WHERE ' . $this->db->sql_in_set('post_id', $post_ids);
		$result = $this->db->sql_query($sql);
		$rows = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['post_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function build_post_quality_rows(array $post_ids): array
	{
		$sql = 'SELECT p.post_id, p.poster_id, p.topic_id, p.post_text, p.post_visibility,
				t.topic_visibility, t.topic_type
			FROM ' . POSTS_TABLE . ' p
			INNER JOIN ' . TOPICS_TABLE . ' t
				ON t.topic_id = p.topic_id
			WHERE ' . $this->db->sql_in_set('p.post_id', $post_ids);
		$result = $this->db->sql_query($sql);
		$rows = [];
		$computed_time = time();

		while ($row = $this->db->sql_fetchrow($result))
		{
			$poster_id = (int) $row['poster_id'];
			$is_counted = $this->is_countable_user($poster_id)
				&& (int) $row['post_visibility'] === ITEM_APPROVED
				&& (int) $row['topic_visibility'] === ITEM_APPROVED
				&& (int) $row['topic_type'] !== ITEM_MOVED;

			$quality_length = $this->is_countable_user($poster_id)
				? min(self::PER_POST_CONTENT_CAP, quality_length::calculate((string) ($row['post_text'] ?? '')))
				: 0;

			$rows[(int) $row['post_id']] = [
				'post_id' => (int) $row['post_id'],
				'poster_id' => $poster_id,
				'topic_id' => (int) $row['topic_id'],
				'quality_length' => (int) $quality_length,
				'is_counted' => $is_counted ? 1 : 0,
				'computed_time' => $computed_time,
			];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function delete_post_quality_rows(array $post_ids): void
	{
		if (empty($post_ids))
		{
			return;
		}

		$sql = 'DELETE FROM ' . $this->post_quality_table . '
			WHERE ' . $this->db->sql_in_set('post_id', $post_ids);
		$this->db->sql_query($sql);
	}

	protected function add_content_delta(array &$deltas, int $user_id, int $delta): void
	{
		if ($delta === 0 || !$this->is_countable_user($user_id))
		{
			return;
		}

		$deltas[$user_id] = ($deltas[$user_id] ?? 0) + $delta;
	}

	protected function normalize_post_ids(array $post_ids): array
	{
		return array_values(array_unique(array_filter(array_map('intval', $post_ids), static function ($post_id) {
			return $post_id > 0;
		})));
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

	protected function is_countable_user(int $user_id): bool
	{
		return $user_id > 0 && $user_id !== ANONYMOUS;
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

}
