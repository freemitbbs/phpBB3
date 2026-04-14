<?php

namespace freemitbbs\toptopics\service;

class ranker
{
	private const DEFAULT_AGE_OFFSET_HOURS = 2.0;
	private const DEFAULT_CACHE_SECONDS = 600;
	private const DEFAULT_GRAVITY = 1.8;
	private const DEFAULT_EARLY_WINDOW_HOURS = 24;
	private const DEFAULT_LOOKBACK_DAYS = 365;
	private const DEFAULT_VELOCITY_BOOST = 1.2;
	private const DEFAULT_EARLY_LIKE_MINIMUM = 3;
	private const DEFAULT_EARLY_VELOCITY_THRESHOLD = 0.5;
	private const DEFAULT_DISCUSSION_REPLY_MINIMUM = 10;
	private const DEFAULT_DISCUSSION_REPLY_LIKE_RATIO = 4.0;
	private const DEFAULT_DISCUSSION_IMBALANCE_PENALTY = 0.8;
	private const DEFAULT_FLAG_WARNING_THRESHOLD = 1;
	private const DEFAULT_FLAG_WARNING_PENALTY = 0.7;
	private const DEFAULT_FLAG_HARD_THRESHOLD = 2;
	private const DEFAULT_FLAG_HARD_PENALTY = 0.3;
	private const DEFAULT_HIDE_FLAG_THRESHOLD = 3;
	private const DEFAULT_HIDE_POINT_THRESHOLD = -5;
	private const DEFAULT_TRUST_BOOST_CAP = 0.1;
	private const DEFAULT_REPLY_WEIGHT = 0.75;
	private const DEFAULT_VIEW_WEIGHT = 0.15;
	private const DEFAULT_CONTENT_WEIGHT = 0.35;
	private const DEFAULT_REACTION_WEIGHT = 0.3;
	private const DEFAULT_MANUAL_BOOST_MULTIPLIER = 2.0;
	private const DEFAULT_MANUAL_DEMOTE_MULTIPLIER = 0.3;
	private const DEFAULT_CANDIDATE_POOL_MIN = 250;
	private const DEFAULT_CANDIDATE_POOL_MULTIPLIER = 50;
	private const DEFAULT_CANDIDATE_POOL_LIMIT = 2000;
	private const MATERIALIZED_REBUILD_LOCK_SECONDS = 30;
	private const DEFAULT_REFRESH_BATCH_SIZE = 10;
	private const CONTENT_LENGTH_CAP = 4000;
	private const CONTENT_LENGTH_SCALE = 120.0;
	private const MATERIALIZED_TOPIC_PAYLOAD_FIELDS = [
		'topic_last_post_id',
		'topic_last_poster_id',
		'topic_last_poster_name',
		'topic_last_poster_colour',
	];

	protected \phpbb\auth\auth $auth;
	protected \phpbb\cache\service $cache;
	protected \freemitbbs\toptopics\service\cache_invalidator $cache_invalidator;
	protected \phpbb\config\config $config;
	protected \phpbb\content_visibility $content_visibility;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\user $user;
	protected string $likes_table;
	protected string $dislikes_table;
	protected string $post_reactions_table;
	protected string $topic_overrides_table;
	protected string $scope_snapshots_table;
	protected string $scope_forums_table;
	protected ?bool $has_post_reactions_table = null;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\cache\service $cache,
		\freemitbbs\toptopics\service\cache_invalidator $cache_invalidator,
		\phpbb\config\config $config,
		\phpbb\content_visibility $content_visibility,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\user $user,
		string $likes_table,
		string $dislikes_table,
		string $topic_overrides_table,
		string $scope_snapshots_table,
		string $scope_forums_table
	)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->cache_invalidator = $cache_invalidator;
		$this->config = $config;
		$this->content_visibility = $content_visibility;
		$this->db = $db;
		$this->user = $user;
		$this->likes_table = $likes_table;
		$this->dislikes_table = $dislikes_table;
		$this->post_reactions_table = $this->derive_post_reactions_table($likes_table);
		$this->topic_overrides_table = $topic_overrides_table;
		$this->scope_snapshots_table = $scope_snapshots_table;
		$this->scope_forums_table = $scope_forums_table;
	}

	public function get_topics(array $forum_ids, int $limit): array
	{
		$forum_ids = array_values(array_unique(array_map('intval', $forum_ids)));
		sort($forum_ids);
		$limit = max(0, $limit);

		if (empty($forum_ids) || !$limit)
		{
			return [];
		}

		$options = $this->get_rank_options();
		$cache_ttl = $options['summary_cache_seconds'];
		$visibility_scope = $this->get_visibility_cache_scope($forum_ids);
		if ($cache_ttl > 0 && $this->is_materializable_visibility_scope($visibility_scope))
		{
			return $this->get_materialized_scope_topics($forum_ids, $limit, $options);
		}
		else if ($cache_ttl > 0 && $this->can_merge_public_snapshot_with_user_delta($visibility_scope))
		{
			return $this->get_public_snapshot_with_user_delta(
				$forum_ids,
				$limit,
				$options,
				(int) $visibility_scope['user_id']
			);
		}

		$cache_key = $this->build_cache_key($forum_ids, $limit, $options);

		if ($cache_ttl > 0)
		{
			$cached_topics = $this->cache->get($cache_key);
			if ($cached_topics !== false && is_array($cached_topics))
			{
				return $cached_topics;
			}
		}

		$ranked_topics = $this->compute_topics($forum_ids, $limit, $options);

		if ($cache_ttl > 0)
		{
			$this->cache->put($cache_key, $ranked_topics, $cache_ttl);
		}

		return $ranked_topics;
	}

	public function get_topics_for_scopes(array $scopes): array
	{
		$normalized_scopes = [];
		foreach ($scopes as $scope_id => $scope)
		{
			$forum_ids = array_values(array_unique(array_filter(array_map('intval', $scope['forum_ids'] ?? []), static function ($forum_id) {
				return $forum_id > 0;
			})));
			sort($forum_ids);
			$normalized_scopes[(string) $scope_id] = [
				'forum_ids' => $forum_ids,
				'limit' => max(0, (int) ($scope['limit'] ?? 0)),
			];
		}

		if (empty($normalized_scopes))
		{
			return [];
		}

		$options = $this->get_rank_options();
		$all_forum_ids = [];
		foreach ($normalized_scopes as $scope)
		{
			foreach ($scope['forum_ids'] as $forum_id)
			{
				$all_forum_ids[$forum_id] = true;
			}
		}

		$all_forum_ids = array_keys($all_forum_ids);
		sort($all_forum_ids);

		if ($options['summary_cache_seconds'] <= 0
			|| !$this->is_materializable_visibility_scope($this->get_visibility_cache_scope($all_forum_ids)))
		{
			$topics_by_scope = [];
			foreach ($normalized_scopes as $scope_id => $scope)
			{
				$topics_by_scope[$scope_id] = !empty($scope['forum_ids']) && $scope['limit'] > 0
					? $this->get_topics($scope['forum_ids'], $scope['limit'])
					: [];
			}

			return $topics_by_scope;
		}

		$options_hash = $this->build_options_hash($options);
		$topics_by_scope = [];
		$missing_scopes = [];

		foreach ($normalized_scopes as $scope_id => $scope)
		{
			if (empty($scope['forum_ids']) || $scope['limit'] <= 0)
			{
				$topics_by_scope[$scope_id] = [];
				continue;
			}

			$cached_topics = $this->get_existing_materialized_scope_topics($scope['forum_ids'], $scope['limit'], $options_hash);
			if ($cached_topics !== null)
			{
				$topics_by_scope[$scope_id] = $cached_topics;
				continue;
			}

			$missing_scopes[$scope_id] = $scope;
		}

		if (empty($missing_scopes))
		{
			return $topics_by_scope;
		}

		$locked_scope_keys = [];
		$scope_candidate_ids = [];
		$all_candidate_ids = [];

		foreach ($missing_scopes as $scope_id => $scope)
		{
			$scope_key = $this->build_materialized_scope_key($scope['forum_ids'], $scope['limit']);
			$lock_key = $this->build_materialized_scope_lock_key($scope_key);
			if ($this->acquire_materialized_scope_lock($lock_key))
			{
				$locked_scope_keys[$scope_id] = $lock_key;
			}

			$scope_candidate_ids[$scope_id] = $this->get_candidate_topic_ids($scope['forum_ids'], $scope['limit'], $options);
			foreach ($scope_candidate_ids[$scope_id] as $topic_id)
			{
				$all_candidate_ids[(int) $topic_id] = true;
			}
		}

		try
		{
			$ranked_topics = !empty($all_candidate_ids)
				? $this->compute_topics_from_candidate_ids(array_keys($all_candidate_ids), count($all_candidate_ids), $options)
				: [];

			foreach ($missing_scopes as $scope_id => $scope)
			{
				$candidate_lookup = array_fill_keys($scope_candidate_ids[$scope_id], true);
				$scope_topics = [];

				if (!empty($candidate_lookup))
				{
					foreach ($ranked_topics as $topic)
					{
						$topic_id = (int) ($topic['topic_id'] ?? 0);
						if ($topic_id > 0 && isset($candidate_lookup[$topic_id]))
						{
							$scope_topics[] = $topic;
							if (count($scope_topics) >= $scope['limit'])
							{
								break;
							}
						}
					}
				}

				$topics_by_scope[$scope_id] = $scope_topics;

				if (isset($locked_scope_keys[$scope_id]))
				{
					$this->store_materialized_scope(
						$this->build_materialized_scope_key($scope['forum_ids'], $scope['limit']),
						$scope['forum_ids'],
						$scope['limit'],
						$options_hash,
						$this->build_generation_hash($scope['forum_ids']),
						$scope_topics
					);
				}
			}
		}
		finally
		{
			foreach ($locked_scope_keys as $lock_key)
			{
				$this->release_materialized_scope_lock($lock_key);
			}
		}

		return $topics_by_scope;
	}

	public function has_stale_materialized_scopes(int $max_scopes = 1): bool
	{
		return !empty($this->get_stale_materialized_scopes($max_scopes));
	}

	public function refresh_stale_materialized_scopes(int $max_scopes = self::DEFAULT_REFRESH_BATCH_SIZE): int
	{
		$stale_scopes = $this->get_stale_materialized_scopes($max_scopes);
		if (empty($stale_scopes))
		{
			return 0;
		}

		$options = $this->get_rank_options();
		$options_hash = $this->build_options_hash($options);
		$refreshed = 0;

		foreach ($stale_scopes as $scope)
		{
			$scope_key = (string) ($scope['scope_key'] ?? '');
			$scope_forum_ids = json_decode((string) ($scope['forum_ids_json'] ?? '[]'), true);
			$scope_forum_ids = is_array($scope_forum_ids) ? $scope_forum_ids : [];
			$scope_forum_ids = array_values(array_unique(array_filter(array_map('intval', $scope_forum_ids), static function ($forum_id) {
				return $forum_id > 0;
			})));
			sort($scope_forum_ids);

			if ($scope_key === '' || empty($scope_forum_ids))
			{
				$this->delete_materialized_scope($scope_key);
				continue;
			}

			$lock_key = $this->build_materialized_scope_lock_key($scope_key);
			if (!$this->acquire_materialized_scope_lock($lock_key))
			{
				continue;
			}

			try
			{
				$this->rebuild_materialized_scope(
					$scope_key,
					$scope_forum_ids,
					max(1, (int) ($scope['topic_limit'] ?? 0)),
					$options,
					$options_hash
				);
				$refreshed++;
			}
			finally
			{
				$this->release_materialized_scope_lock($lock_key);
			}
		}

		return $refreshed;
	}

	protected function compute_topics(array $forum_ids, int $limit, array $options): array
	{
		$candidate_topic_ids = $this->get_candidate_topic_ids($forum_ids, $limit, $options);
		if (empty($candidate_topic_ids))
		{
			return [];
		}

		return $this->compute_topics_from_candidate_ids($candidate_topic_ids, $limit, $options);
	}

	protected function compute_topics_from_candidate_ids(array $candidate_topic_ids, int $limit, array $options): array
	{
		$topic_sql = $this->db->sql_in_set('t.topic_id', $candidate_topic_ids);
		$candidate_post_sql = $this->db->sql_in_set('p.topic_id', $candidate_topic_ids);
		$candidate_topic_join_sql = $this->db->sql_in_set('tt.topic_id', $candidate_topic_ids);
		$candidate_reaction_topic_sql = $this->db->sql_in_set('pr.topic_id', $candidate_topic_ids);
		$now = time();
		$early_window_seconds = $options['early_window_hours'] * 3600;
		$content_length_sql = $this->get_text_length_sql('base.first_post_text');
		$points_sql = '(base.like_count - base.dislike_count)';
		$replies_sql = 'CASE WHEN base.topic_posts_approved > 0 THEN base.topic_posts_approved - 1 ELSE 0 END';
		$views_sql = 'CASE WHEN base.topic_views > 0 THEN base.topic_views ELSE 0 END';
		$age_hours_sql = '((' . $now . ' - base.topic_time) / 3600.0)';
		$age_divisor_sql = 'CASE WHEN ' . $age_hours_sql . ' > 1.0 THEN ' . $age_hours_sql . ' ELSE 1.0 END';
		$velocity_hours_sql = 'CASE WHEN ' . $age_hours_sql . ' > 24.0 THEN 24.0 WHEN ' . $age_hours_sql . ' > 1.0 THEN ' . $age_hours_sql . ' ELSE 1.0 END';
		$content_length_capped_sql = 'CASE WHEN ' . $content_length_sql . ' > ' . self::CONTENT_LENGTH_CAP . ' THEN ' . self::CONTENT_LENGTH_CAP . ' ELSE ' . $content_length_sql . ' END';
		$reply_signal_sql = $this->sql_ln('(1.0 + ' . $replies_sql . ')');
		$view_signal_sql = $this->sql_ln('(1.0 + (' . $views_sql . ' / ' . $age_divisor_sql . '))');
		$content_signal_sql = $this->sql_ln('(1.0 + (' . $content_length_capped_sql . ' / ' . self::CONTENT_LENGTH_SCALE . '))');
		$reaction_signal_sql = $this->sql_ln('(1.0 + base.reaction_count)');
		$signal_score_sql = '(' . $points_sql
			. ' + (' . $reply_signal_sql . ' * ' . $options['reply_weight'] . ')'
			. ' + (' . $view_signal_sql . ' * ' . $options['view_weight'] . ')'
			. ' + (' . $reaction_signal_sql . ' * ' . $options['reaction_weight'] . ')'
			. ' + (' . $content_signal_sql . ' * ' . $options['content_weight'] . '))';
		$base_rank_sql = '((' . $signal_score_sql . ' - 1.0) / ' . $this->sql_power('(' . $age_hours_sql . ' + ' . $options['age_offset_hours'] . ')', $options['gravity']) . ')';
		$rank_after_velocity_sql = 'CASE
			WHEN base.early_like_count >= ' . $options['early_like_minimum'] . '
				AND (base.early_like_count / ' . $velocity_hours_sql . ') >= ' . $options['early_velocity_threshold'] . '
			THEN (' . $base_rank_sql . ' * ' . $options['velocity_boost'] . ')
			ELSE ' . $base_rank_sql . '
		END';
		$rank_after_discussion_sql = 'CASE
			WHEN ' . $replies_sql . ' >= ' . $options['discussion_reply_minimum'] . '
				AND ' . $replies_sql . ' > (CASE WHEN base.like_count > 1 THEN base.like_count ELSE 1 END * ' . $options['discussion_reply_like_ratio'] . ')
			THEN (' . $rank_after_velocity_sql . ' * ' . $options['discussion_penalty'] . ')
			ELSE ' . $rank_after_velocity_sql . '
		END';
		$rank_after_flags_sql = 'CASE
			WHEN ' . $options['flag_hard_threshold'] . ' > 0 AND base.flag_count >= ' . $options['flag_hard_threshold'] . '
				THEN (' . $rank_after_discussion_sql . ' * ' . $options['flag_hard_penalty'] . ')
			WHEN ' . $options['flag_warning_threshold'] . ' > 0 AND base.flag_count >= ' . $options['flag_warning_threshold'] . '
				THEN (' . $rank_after_discussion_sql . ' * ' . $options['flag_warning_penalty'] . ')
			ELSE ' . $rank_after_discussion_sql . '
		END';
		$trust_sql = '(1.0 + CASE
			WHEN (' . $this->sql_ln('(base.user_posts + 2.0)') . ' / 50.0) < ' . $options['trust_boost_cap'] . '
				THEN (' . $this->sql_ln('(base.user_posts + 2.0)') . ' / 50.0)
			ELSE ' . $options['trust_boost_cap'] . '
		END)';
		$pre_manual_rank_sql = '(' . $rank_after_flags_sql . ' * ' . $trust_sql . ')';
		$rank_sql = 'CASE
			WHEN scored.override_state = \'boost\'
				THEN ((CASE WHEN scored.pre_manual_rank > 0.0001 THEN scored.pre_manual_rank ELSE 0.0001 END) * ' . $options['manual_boost_multiplier'] . ')
			WHEN scored.override_state = \'demote\'
				THEN (scored.pre_manual_rank * ' . $options['manual_demote_multiplier'] . ')
			ELSE scored.pre_manual_rank
		END';
		$reaction_select_sql = ',
					0 AS reaction_count';
		$reaction_join_sql = '';

		if ($this->has_post_reactions_table())
		{
			$reaction_select_sql = ',
					COALESCE(reaction_data.reaction_count, 0) AS reaction_count';
			$reaction_join_sql = '
				LEFT JOIN (
					SELECT pr.topic_id, COUNT(pr.reaction_id) AS reaction_count
					FROM ' . $this->post_reactions_table . ' pr
					WHERE ' . $candidate_reaction_topic_sql . '
					GROUP BY pr.topic_id
				) reaction_data ON reaction_data.topic_id = t.topic_id';
		}

		$base_sql = 'SELECT t.topic_id, t.forum_id, t.topic_title, t.topic_time, t.topic_last_post_time, t.topic_last_post_id,
					t.topic_last_poster_id, t.topic_last_poster_name, t.topic_last_poster_colour, t.topic_type,
					t.topic_status, t.poll_start, t.topic_posts_approved, t.topic_views,
					t.topic_poster, t.topic_first_poster_name, t.topic_first_poster_colour,
					COALESCE(fp.post_text, \'\') AS first_post_text,
					COALESCE(ov.override_state, \'\') AS override_state,
					f.forum_name, COALESCE(u.user_posts, 0) AS user_posts,
					COALESCE(like_data.like_count, 0) AS like_count,
					COALESCE(like_data.early_like_count, 0) AS early_like_count,
					COALESCE(dislike_data.dislike_count, 0) AS dislike_count,
					COALESCE(report_data.flag_count, 0) AS flag_count'
					. $reaction_select_sql . '
				FROM ' . TOPICS_TABLE . ' t
				INNER JOIN ' . FORUMS_TABLE . ' f ON f.forum_id = t.forum_id
				LEFT JOIN ' . POSTS_TABLE . ' fp ON fp.post_id = t.topic_first_post_id
				LEFT JOIN ' . $this->topic_overrides_table . ' ov ON ov.topic_id = t.topic_id
				LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = t.topic_poster
				LEFT JOIN (
					SELECT p.topic_id,
						COUNT(pl.post_id) AS like_count,
						SUM(CASE WHEN pl.liketime <= tt.topic_time + ' . $early_window_seconds . ' THEN 1 ELSE 0 END) AS early_like_count
					FROM ' . $this->likes_table . ' pl
					INNER JOIN ' . POSTS_TABLE . ' p ON p.post_id = pl.post_id
					INNER JOIN ' . TOPICS_TABLE . ' tt ON tt.topic_id = p.topic_id
					WHERE ' . $candidate_post_sql . '
						AND ' . $candidate_topic_join_sql . '
					GROUP BY p.topic_id
				) like_data ON like_data.topic_id = t.topic_id
				LEFT JOIN (
					SELECT p.topic_id, COUNT(pd.post_id) AS dislike_count
					FROM ' . $this->dislikes_table . ' pd
					INNER JOIN ' . POSTS_TABLE . ' p ON p.post_id = pd.post_id
					WHERE ' . $candidate_post_sql . '
					GROUP BY p.topic_id
				) dislike_data ON dislike_data.topic_id = t.topic_id
				LEFT JOIN (
					SELECT p.topic_id, COUNT(r.report_id) AS flag_count
					FROM ' . REPORTS_TABLE . ' r
					INNER JOIN ' . POSTS_TABLE . ' p ON p.post_id = r.post_id
					WHERE r.report_closed = 0
						AND ' . $candidate_post_sql . '
					GROUP BY p.topic_id
				) report_data ON report_data.topic_id = t.topic_id'
				. $reaction_join_sql . '
				WHERE ' . $topic_sql;

		$scored_sql = 'SELECT base.*,
				' . $points_sql . ' AS points,
				' . $replies_sql . ' AS replies,
				' . $views_sql . ' AS views,
				' . $content_length_capped_sql . ' AS content_length,
				' . $signal_score_sql . ' AS signal_score,
				' . $pre_manual_rank_sql . ' AS pre_manual_rank
			FROM (' . $base_sql . ') base';

		$ranked_sql = 'SELECT scored.*,
				' . $rank_sql . ' AS rank
			FROM (' . $scored_sql . ') scored';

		$sql = 'SELECT ranked.*
			FROM (' . $ranked_sql . ') ranked
			WHERE ranked.override_state <> \'kill\'
				AND (
					ranked.like_count <> 0
					OR ranked.dislike_count <> 0
					OR ranked.flag_count <> 0
					OR ranked.reaction_count <> 0
					OR ranked.replies <> 0
					OR ranked.views <> 0
					OR ranked.override_state = \'boost\'
				)
				AND (
					ranked.override_state = \'boost\'
					OR (
						(' . $options['hide_flag_threshold'] . ' <= 0 OR ranked.flag_count < ' . $options['hide_flag_threshold'] . ')
						AND ranked.points > ' . $options['hide_point_threshold'] . '
					)
				)
				AND ranked.rank > 0
			ORDER BY ranked.rank DESC, ranked.topic_time DESC';

		$result = $this->db->sql_query_limit($sql, $limit);

		$ranked_topics = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ranked_topics[] = [
				'topic_id' => (int) $row['topic_id'],
				'forum_id' => (int) $row['forum_id'],
				'topic_title' => (string) $row['topic_title'],
				'topic_time' => (int) $row['topic_time'],
				'topic_last_post_time' => (int) $row['topic_last_post_time'],
				'topic_last_post_id' => (int) $row['topic_last_post_id'],
				'topic_last_poster_id' => (int) $row['topic_last_poster_id'],
				'topic_last_poster_name' => (string) $row['topic_last_poster_name'],
				'topic_last_poster_colour' => (string) $row['topic_last_poster_colour'],
				'topic_type' => (int) $row['topic_type'],
				'topic_status' => (int) $row['topic_status'],
				'poll_start' => (int) $row['poll_start'],
				'topic_poster' => (int) $row['topic_poster'],
				'topic_first_poster_name' => (string) $row['topic_first_poster_name'],
				'topic_first_poster_colour' => (string) $row['topic_first_poster_colour'],
				'forum_name' => (string) $row['forum_name'],
				'like_count' => (int) $row['like_count'],
				'dislike_count' => (int) $row['dislike_count'],
				'flag_count' => (int) $row['flag_count'],
				'replies' => (int) $row['replies'],
				'views' => (int) $row['views'],
				'rank' => (float) $row['rank'],
			];
		}
		$this->db->sql_freeresult($result);

		return $ranked_topics;
	}

	protected function get_candidate_topic_ids(array $forum_ids, int $limit, array $options): array
	{
		$forum_sql = $this->db->sql_in_set('t.forum_id', $forum_ids);
		$lookback_cutoff = time() - ($options['lookback_days'] * 86400);
		$candidate_limit = $this->get_candidate_pool_limit($limit);

		$sql = 'SELECT t.topic_id
			FROM ' . TOPICS_TABLE . ' t
			WHERE ' . $forum_sql . '
				AND t.topic_type <> ' . ITEM_MOVED . '
				AND t.topic_time >= ' . $lookback_cutoff . '
				AND ' . $this->content_visibility->get_forums_visibility_sql('topic', $forum_ids, 't.') . '
			ORDER BY t.topic_last_post_time DESC';
		$result = $this->db->sql_query_limit($sql, $candidate_limit);
		$topic_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topic_ids[] = (int) $row['topic_id'];
		}
		$this->db->sql_freeresult($result);

		$override_sql = 'SELECT t.topic_id
			FROM ' . TOPICS_TABLE . ' t
			INNER JOIN ' . $this->topic_overrides_table . ' ov
				ON ov.topic_id = t.topic_id
			WHERE ' . $forum_sql . '
				AND t.topic_type <> ' . ITEM_MOVED . '
				AND t.topic_time >= ' . $lookback_cutoff . '
				AND ' . $this->content_visibility->get_forums_visibility_sql('topic', $forum_ids, 't.');
		$result = $this->db->sql_query($override_sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topic_ids[] = (int) $row['topic_id'];
		}
		$this->db->sql_freeresult($result);

		$topic_ids = array_values(array_unique(array_filter($topic_ids)));
		sort($topic_ids);

		return $topic_ids;
	}

	protected function get_candidate_pool_limit(int $limit): int
	{
		$configured_limit = $this->get_int_config('toptopics_candidate_pool_limit', self::DEFAULT_CANDIDATE_POOL_LIMIT, 50, 20000);

		return max(
			self::DEFAULT_CANDIDATE_POOL_MIN,
			min($configured_limit, $limit * self::DEFAULT_CANDIDATE_POOL_MULTIPLIER)
		);
	}

	protected function build_cache_key(array $forum_ids, int $limit, array $options): string
	{
		return '_freemitbbs_toptopics_' . md5(json_encode([
			'forum_ids' => $forum_ids,
			'limit' => $limit,
			'options' => $options,
			'generation' => $this->cache_invalidator->get_cache_scope($forum_ids),
			'visibility' => $this->get_visibility_cache_scope($forum_ids),
		]));
	}

	public function invalidate_forums(array $forum_ids): void
	{
		$this->cache_invalidator->invalidate_forums($forum_ids);
	}

	public function invalidate_all(): void
	{
		$this->cache_invalidator->invalidate_all();
		$this->clear_materialized_scopes();
	}

	public function clear_materialized_scopes_for_forums(array $forum_ids): void
	{
		$forum_ids = array_values(array_unique(array_filter(array_map('intval', $forum_ids), static function ($forum_id) {
			return $forum_id > 0;
		})));
		sort($forum_ids);

		if (empty($forum_ids))
		{
			return;
		}

		$sql = 'SELECT DISTINCT scope_key
			FROM ' . $this->scope_forums_table . '
			WHERE ' . $this->db->sql_in_set('forum_id', $forum_ids);
		$result = $this->db->sql_query($sql);
		$scope_keys = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$scope_keys[] = (string) ($row['scope_key'] ?? '');
		}
		$this->db->sql_freeresult($result);

		foreach (array_filter(array_unique($scope_keys)) as $scope_key)
		{
			$this->delete_materialized_scope($scope_key);
		}
	}

	protected function get_visibility_cache_scope(array $forum_ids): array
	{
		$approve_forums = array_keys($this->auth->acl_getf('m_approve', true));
		$approve_forums = array_values(array_intersect(array_map('intval', $approve_forums), $forum_ids));
		sort($approve_forums);

		$scope = [
			'approve_forums' => $approve_forums,
			'display_unapproved_posts' => (bool) $this->config['display_unapproved_posts'],
		];

		if ($scope['display_unapproved_posts'] && (int) $this->user->data['user_id'] !== ANONYMOUS)
		{
			$scope['user_id'] = (int) $this->user->data['user_id'];
		}

		return $scope;
	}

	protected function get_rank_options(): array
	{
		return [
			'summary_cache_seconds' => $this->get_int_config('toptopics_summary_cache_seconds', self::DEFAULT_CACHE_SECONDS, 0, 86400),
			'age_offset_hours' => $this->get_float_config('toptopics_age_offset_hours', self::DEFAULT_AGE_OFFSET_HOURS, 0.1),
			'gravity' => $this->get_float_config('toptopics_gravity', self::DEFAULT_GRAVITY, 0.1),
			'lookback_days' => $this->get_int_config('toptopics_lookback_days', self::DEFAULT_LOOKBACK_DAYS, 1),
			'content_weight' => $this->get_float_config('toptopics_content_weight', self::DEFAULT_CONTENT_WEIGHT, 0.0, 10.0),
			'manual_boost_multiplier' => $this->get_float_config('toptopics_manual_boost_multiplier', self::DEFAULT_MANUAL_BOOST_MULTIPLIER, 1.0, 100.0),
			'manual_demote_multiplier' => $this->get_float_config('toptopics_manual_demote_multiplier', self::DEFAULT_MANUAL_DEMOTE_MULTIPLIER, 0.0, 1.0),
			'early_window_hours' => $this->get_int_config('toptopics_early_window_hours', self::DEFAULT_EARLY_WINDOW_HOURS, 1),
			'early_like_minimum' => $this->get_int_config('toptopics_early_like_minimum', self::DEFAULT_EARLY_LIKE_MINIMUM, 1),
			'early_velocity_threshold' => $this->get_float_config('toptopics_early_velocity_threshold', self::DEFAULT_EARLY_VELOCITY_THRESHOLD, 0.01),
			'velocity_boost' => $this->get_float_config('toptopics_velocity_boost', self::DEFAULT_VELOCITY_BOOST, 0.01),
			'discussion_reply_minimum' => $this->get_int_config('toptopics_discussion_reply_minimum', self::DEFAULT_DISCUSSION_REPLY_MINIMUM, 0),
			'discussion_reply_like_ratio' => $this->get_float_config('toptopics_discussion_reply_like_ratio', self::DEFAULT_DISCUSSION_REPLY_LIKE_RATIO, 0.1),
			'discussion_penalty' => $this->get_float_config('toptopics_discussion_penalty', self::DEFAULT_DISCUSSION_IMBALANCE_PENALTY, 0.01, 1.0),
			'flag_warning_threshold' => $this->get_int_config('toptopics_flag_warning_threshold', self::DEFAULT_FLAG_WARNING_THRESHOLD, 0),
			'flag_warning_penalty' => $this->get_float_config('toptopics_flag_warning_penalty', self::DEFAULT_FLAG_WARNING_PENALTY, 0.01, 1.0),
			'flag_hard_threshold' => $this->get_int_config('toptopics_flag_hard_threshold', self::DEFAULT_FLAG_HARD_THRESHOLD, 0),
			'flag_hard_penalty' => $this->get_float_config('toptopics_flag_hard_penalty', self::DEFAULT_FLAG_HARD_PENALTY, 0.01, 1.0),
			'hide_flag_threshold' => $this->get_int_config('toptopics_hide_flag_threshold', self::DEFAULT_HIDE_FLAG_THRESHOLD, 0),
			'hide_point_threshold' => $this->get_int_config('toptopics_hide_point_threshold', self::DEFAULT_HIDE_POINT_THRESHOLD),
			'reaction_weight' => $this->get_float_config('toptopics_reaction_weight', self::DEFAULT_REACTION_WEIGHT, 0.0, 10.0),
			'trust_boost_cap' => $this->get_float_config('toptopics_trust_boost_cap', self::DEFAULT_TRUST_BOOST_CAP, 0.0, 1.0),
			'reply_weight' => $this->get_float_config('toptopics_reply_weight', self::DEFAULT_REPLY_WEIGHT, 0.0, 10.0),
			'view_weight' => $this->get_float_config('toptopics_view_weight', self::DEFAULT_VIEW_WEIGHT, 0.0, 10.0),
		];
	}

	protected function derive_post_reactions_table(string $likes_table): string
	{
		$likes_suffix = 'posts_likes';
		if (substr($likes_table, -strlen($likes_suffix)) !== $likes_suffix)
		{
			return '';
		}

		return substr($likes_table, 0, -strlen($likes_suffix)) . 'post_reactions';
	}

	protected function has_post_reactions_table(): bool
	{
		if ($this->has_post_reactions_table !== null)
		{
			return $this->has_post_reactions_table;
		}

		if ($this->post_reactions_table === '')
		{
			$this->has_post_reactions_table = false;
			return false;
		}

		$this->db->sql_return_on_error(true);
		$result = $this->db->sql_query_limit('SELECT reaction_id FROM ' . $this->post_reactions_table, 1);
		$this->has_post_reactions_table = ($result !== false);
		if ($result !== false)
		{
			$this->db->sql_freeresult($result);
		}
		$this->db->sql_return_on_error(false);

		return $this->has_post_reactions_table;
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

	protected function is_materializable_visibility_scope(array $visibility_scope): bool
	{
		return empty($visibility_scope['approve_forums']) && empty($visibility_scope['user_id']);
	}

	protected function can_merge_public_snapshot_with_user_delta(array $visibility_scope): bool
	{
		return empty($visibility_scope['approve_forums']) && !empty($visibility_scope['user_id']);
	}

	protected function get_public_snapshot_with_user_delta(array $forum_ids, int $limit, array $options, int $user_id): array
	{
		$topics = $this->get_materialized_scope_topics($forum_ids, $limit, $options);
		$delta_topics = $this->get_user_specific_delta_topics($forum_ids, $limit, $options, $user_id);

		if (empty($delta_topics))
		{
			return $topics;
		}

		$merged_topics = [];
		foreach ($topics as $topic)
		{
			$merged_topics[(int) $topic['topic_id']] = $topic;
		}
		foreach ($delta_topics as $topic)
		{
			$merged_topics[(int) $topic['topic_id']] = $topic;
		}

		$merged_topics = array_values($merged_topics);
		usort($merged_topics, static function (array $left, array $right): int {
			$left_rank = (float) ($left['rank'] ?? 0.0);
			$right_rank = (float) ($right['rank'] ?? 0.0);
			if ($left_rank === $right_rank)
			{
				return ((int) ($right['topic_time'] ?? 0)) <=> ((int) ($left['topic_time'] ?? 0));
			}

			return $right_rank <=> $left_rank;
		});

		return array_slice($merged_topics, 0, $limit);
	}

	protected function get_user_specific_delta_topics(array $forum_ids, int $limit, array $options, int $user_id): array
	{
		$candidate_topic_ids = $this->get_user_specific_candidate_topic_ids($forum_ids, $options, $user_id);
		if (empty($candidate_topic_ids))
		{
			return [];
		}

		return $this->compute_topics_from_candidate_ids($candidate_topic_ids, $limit, $options);
	}

	protected function get_user_specific_candidate_topic_ids(array $forum_ids, array $options, int $user_id): array
	{
		if ($user_id <= 0 || $user_id === ANONYMOUS)
		{
			return [];
		}

		$forum_sql = $this->db->sql_in_set('t.forum_id', $forum_ids);
		$lookback_cutoff = time() - ($options['lookback_days'] * 86400);
		$candidate_limit = max(
			self::DEFAULT_CANDIDATE_POOL_MIN,
			$this->get_int_config('toptopics_candidate_pool_limit', self::DEFAULT_CANDIDATE_POOL_LIMIT, 50, 20000)
		);
		$visibility_sql = 't.topic_visibility IN (' . ITEM_UNAPPROVED . ', ' . ITEM_REAPPROVE . ')';

		$sql = 'SELECT t.topic_id
			FROM ' . TOPICS_TABLE . ' t
			WHERE ' . $forum_sql . '
				AND t.topic_type <> ' . ITEM_MOVED . '
				AND t.topic_time >= ' . $lookback_cutoff . '
				AND t.topic_poster = ' . $user_id . '
				AND ' . $visibility_sql . '
			ORDER BY t.topic_last_post_time DESC';
		$result = $this->db->sql_query_limit($sql, $candidate_limit);
		$topic_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topic_ids[] = (int) $row['topic_id'];
		}
		$this->db->sql_freeresult($result);

		$override_sql = 'SELECT t.topic_id
			FROM ' . TOPICS_TABLE . ' t
			INNER JOIN ' . $this->topic_overrides_table . ' ov
				ON ov.topic_id = t.topic_id
			WHERE ' . $forum_sql . '
				AND t.topic_type <> ' . ITEM_MOVED . '
				AND t.topic_time >= ' . $lookback_cutoff . '
				AND t.topic_poster = ' . $user_id . '
				AND ' . $visibility_sql;
		$result = $this->db->sql_query($override_sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topic_ids[] = (int) $row['topic_id'];
		}
		$this->db->sql_freeresult($result);

		$topic_ids = array_values(array_unique(array_filter($topic_ids)));
		sort($topic_ids);

		return $topic_ids;
	}

	protected function get_materialized_scope_topics(array $forum_ids, int $limit, array $options): array
	{
		$scope_key = $this->build_materialized_scope_key($forum_ids, $limit);
		$options_hash = $this->build_options_hash($options);
		$cached_topics = $this->get_existing_materialized_scope_topics($forum_ids, $limit, $options_hash);
		if ($cached_topics !== null)
		{
			return $cached_topics;
		}

		$lock_key = $this->build_materialized_scope_lock_key($scope_key);
		$lock_acquired = $this->acquire_materialized_scope_lock($lock_key);

		try
		{
			if (!$lock_acquired)
			{
				return [];
			}

			return $this->rebuild_materialized_scope($scope_key, $forum_ids, $limit, $options, $options_hash);
		}
		finally
		{
			if ($lock_acquired)
			{
				$this->release_materialized_scope_lock($lock_key);
			}
		}
	}

	protected function get_existing_materialized_scope_topics(array $forum_ids, int $limit, string $options_hash): ?array
	{
		$scope_key = $this->build_materialized_scope_key($forum_ids, $limit);
		$fresh_cutoff = time() - $this->get_int_config('toptopics_summary_cache_seconds', self::DEFAULT_CACHE_SECONDS, 0, 86400);

		$sql = 'SELECT topics_json, options_hash, updated_time
			FROM ' . $this->scope_snapshots_table . '
			WHERE scope_key = \'' . $this->db->sql_escape($scope_key) . '\'';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return null;
		}

		$cached_topics = json_decode((string) $row['topics_json'], true);
		$has_cached_topics = is_array($cached_topics) && $this->is_materialized_topic_payload_compatible($cached_topics);
		if (!$has_cached_topics)
		{
			return null;
		}

		if ((string) $row['options_hash'] === $options_hash
			&& (int) $row['updated_time'] >= $fresh_cutoff)
		{
			return $cached_topics;
		}

		return $cached_topics;
	}

	protected function rebuild_materialized_scope(
		string $scope_key,
		array $forum_ids,
		int $limit,
		array $options,
		?string $options_hash = null
	): array
	{
		$topics = $this->compute_topics($forum_ids, $limit, $options);

		$this->store_materialized_scope(
			$scope_key,
			$forum_ids,
			$limit,
			$options_hash ?? $this->build_options_hash($options),
			$this->build_generation_hash($forum_ids),
			$topics
		);

		return $topics;
	}

	protected function store_materialized_scope(
		string $scope_key,
		array $forum_ids,
		int $limit,
		string $options_hash,
		string $generation_hash,
		array $topics
	): void
	{
		$forum_ids = array_values(array_unique(array_filter(array_map('intval', $forum_ids), static function ($forum_id) {
			return $forum_id > 0;
		})));
		sort($forum_ids);

		$this->delete_materialized_scope($scope_key);

		$sql = 'INSERT INTO ' . $this->scope_snapshots_table . ' ' . $this->db->sql_build_array('INSERT', [
			'scope_key' => $scope_key,
			'forum_ids_json' => json_encode($forum_ids),
			'topic_limit' => $limit,
			'options_hash' => $options_hash,
			'generation_hash' => $generation_hash,
			'topics_json' => json_encode(array_values($topics)),
			'updated_time' => time(),
		]);
		$this->db->sql_query($sql);

		foreach ($forum_ids as $forum_id)
		{
			$sql = 'INSERT INTO ' . $this->scope_forums_table . ' ' . $this->db->sql_build_array('INSERT', [
				'scope_key' => $scope_key,
				'forum_id' => $forum_id,
			]);
			$this->db->sql_query($sql);
		}
	}

	protected function clear_materialized_scopes(): void
	{
		$this->db->sql_query('DELETE FROM ' . $this->scope_forums_table);
		$this->db->sql_query('DELETE FROM ' . $this->scope_snapshots_table);
	}

	protected function delete_materialized_scope(string $scope_key): void
	{
		if ($scope_key === '')
		{
			return;
		}

		$escaped_scope_key = $this->db->sql_escape($scope_key);
		$this->db->sql_query('DELETE FROM ' . $this->scope_forums_table . " WHERE scope_key = '" . $escaped_scope_key . "'");
		$this->db->sql_query('DELETE FROM ' . $this->scope_snapshots_table . " WHERE scope_key = '" . $escaped_scope_key . "'");
	}

	protected function build_materialized_scope_key(array $forum_ids, int $limit): string
	{
		return '_freemitbbs_toptopics_scope_' . md5(json_encode([
			'forum_ids' => array_values($forum_ids),
			'limit' => $limit,
		]));
	}

	protected function build_options_hash(array $options): string
	{
		return md5(json_encode($options));
	}

	protected function build_generation_hash(array $forum_ids): string
	{
		return md5(json_encode($this->cache_invalidator->get_cache_scope($forum_ids)));
	}

	protected function is_materialized_topic_payload_compatible(array $topics): bool
	{
		if (empty($topics))
		{
			return true;
		}

		$sample = reset($topics);
		if (!is_array($sample))
		{
			return false;
		}

		foreach (self::MATERIALIZED_TOPIC_PAYLOAD_FIELDS as $field)
		{
			if (!array_key_exists($field, $sample))
			{
				return false;
			}
		}

		return true;
	}

	protected function get_stale_materialized_scopes(int $max_scopes): array
	{
		$max_scopes = max(1, $max_scopes);
		$options = $this->get_rank_options();
		$cache_ttl = $options['summary_cache_seconds'];
		if ($cache_ttl <= 0)
		{
			return [];
		}

		$options_hash = $this->build_options_hash($options);
		$refresh_cutoff = time() - $this->get_materialized_refresh_age_seconds($cache_ttl);
		$sql = 'SELECT scope_key, forum_ids_json, topic_limit, updated_time, options_hash
			FROM ' . $this->scope_snapshots_table . "
			WHERE options_hash <> '" . $this->db->sql_escape($options_hash) . "'
				OR updated_time <= " . (int) $refresh_cutoff . '
			ORDER BY updated_time ASC';
		$result = $this->db->sql_query_limit($sql, $max_scopes);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function get_materialized_refresh_age_seconds(int $cache_ttl): int
	{
		return max(15, (int) floor($cache_ttl * 0.8));
	}

	protected function build_materialized_scope_lock_key(string $scope_key): string
	{
		return '_freemitbbs_toptopics_scope_lock_' . md5($scope_key);
	}

	protected function acquire_materialized_scope_lock(string $lock_key): bool
	{
		$existing = $this->cache->get($lock_key);
		if ($existing !== false)
		{
			return false;
		}

		$this->cache->put($lock_key, time(), self::MATERIALIZED_REBUILD_LOCK_SECONDS);

		return true;
	}

	protected function release_materialized_scope_lock(string $lock_key): void
	{
		$this->cache->destroy($lock_key);
	}

	protected function get_text_length_sql(string $column_name): string
	{
		switch ($this->db->get_sql_layer())
		{
			case 'mssql_odbc':
			case 'mssqlnative':
				return 'LEN(' . $column_name . ')';

			case 'sqlite3':
			case 'postgres':
			case 'oracle':
				return 'LENGTH(' . $column_name . ')';

			case 'mysqli':
			case 'mysql4':
			default:
				return 'CHAR_LENGTH(' . $column_name . ')';
		}
	}

	protected function sql_ln(string $expression): string
	{
		switch ($this->db->get_sql_layer())
		{
			case 'postgres':
			case 'oracle':
			case 'sqlite3':
				return 'LN(' . $expression . ')';

			case 'mssql_odbc':
			case 'mssqlnative':
			case 'mysqli':
			case 'mysql4':
			default:
				return 'LOG(' . $expression . ')';
		}
	}

	protected function sql_power(string $base, float $exponent): string
	{
		return 'POWER(' . $base . ', ' . $exponent . ')';
	}
}
