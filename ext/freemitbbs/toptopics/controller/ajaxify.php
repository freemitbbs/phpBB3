<?php

namespace freemitbbs\toptopics\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class ajaxify
{
	private const DEFAULT_POST_COLLAPSE_DISLIKE_THRESHOLD = 5;

	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\content_visibility $content_visibility;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\user $user;
	protected \phpbb\language\language $language;
	protected \freemitbbs\toptopics\service\ranker $ranker;
	protected \freemitbbs\toptopics\service\reputation $reputation;
	protected string $dislikes_table;
	protected string $dislike_history_table;
	protected string $likes_table;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\content_visibility $content_visibility,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\request\request_interface $request,
		\phpbb\user $user,
		\phpbb\language\language $language,
		\freemitbbs\toptopics\service\ranker $ranker,
		\freemitbbs\toptopics\service\reputation $reputation,
		string $dislikes_table,
		string $dislike_history_table,
		string $likes_table
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->content_visibility = $content_visibility;
		$this->db = $db;
		$this->request = $request;
		$this->user = $user;
		$this->language = $language;
		$this->ranker = $ranker;
		$this->reputation = $reputation;
		$this->dislikes_table = $dislikes_table;
		$this->dislike_history_table = $dislike_history_table;
		$this->likes_table = $likes_table;
	}

	public function base($action, $post)
	{
		$post = (int) $post;
		$action = (string) $action;

		if (!in_array($action, ['add', 'remove', 'toggle'], true))
		{
			return $this->json_error($this->language->lang('FORM_INVALID'));
		}

		if ($this->user->data['user_id'] == ANONYMOUS || !$this->auth->acl_get('u_toptopics_dislike'))
		{
			return $this->json_error($this->language->lang('LOGIN_TO_DISLIKE_POST'));
		}

		if (!check_link_hash($this->request->variable('hash', ''), 'toptopics_dislike_' . $post))
		{
			return $this->json_error($this->language->lang('FORM_INVALID'));
		}

		$row = $this->get_post_state($post);
		if (!$row
			|| !$this->auth->acl_get('f_read', (int) $row['forum_id'])
			|| !$this->content_visibility->is_visible('post', (int) $row['forum_id'], $row))
		{
			return $this->json_error();
		}

		if ((int) $row['poster_id'] === (int) $this->user->data['user_id'])
		{
			return $this->json_error($this->language->lang('CANT_DISLIKE_OWN_POST'));
		}

		$min_posts = (int) $this->config['toptopics_downvote_min_posts'];
		if ((int) $this->user->data['user_posts'] < $min_posts)
		{
			return $this->json_error($this->language->lang('TOPTOPICS_MIN_POSTS_TO_DISLIKE', $min_posts));
		}

		$current_reputation = $this->reputation->get_score((int) $this->user->data['user_id']);
		$required_reputation = $this->reputation->get_required_dislike_score();
		if ($required_reputation > 0 && $current_reputation < $required_reputation)
		{
			return $this->json_error($this->language->lang(
				'TOPTOPICS_MIN_REPUTATION_TO_DISLIKE',
				$this->format_reputation($required_reputation),
				$this->format_reputation($current_reputation)
			));
		}

		$current_user_disliked = !empty($row['dislike_user_id']);
		$action = $this->resolve_action($action, $current_user_disliked);

		if ($action === 'add')
		{
			return $this->add_dislike($post, (int) $row['forum_id'], (int) $row['poster_id'], $current_user_disliked, !empty($row['like_user_id']));
		}

		return $this->remove_dislike($post, (int) $row['forum_id'], (int) $row['poster_id'], $current_user_disliked);
	}

	protected function add_dislike(int $post_id, int $forum_id, int $poster_id, bool $current_user_disliked, bool $current_user_liked): JsonResponse
	{
		if ($current_user_disliked)
		{
			return $this->build_success_response($post_id, 'add', $this->get_post_reaction_counts($post_id));
		}

		if ($current_user_liked)
		{
			return $this->json_error($this->language->lang('TOPTOPICS_REMOVE_LIKE_FIRST'));
		}

		$rate_limit_error = $this->check_rate_limits();
		if ($rate_limit_error !== '')
		{
			return $this->json_error($rate_limit_error);
		}

		$inserted = $this->insert_dislike($post_id, $poster_id);
		$is_disliked = $inserted ? true : $this->has_user_disliked($post_id);

		if (!$is_disliked)
		{
			return $this->json_error();
		}

		if ($inserted)
		{
			$this->insert_dislike_history($post_id);
			$this->invalidate_rank_cache_for_forums([$forum_id]);
			$this->reputation->refresh_user($poster_id);
		}

		return $this->build_success_response($post_id, 'add', $this->get_post_reaction_counts($post_id));
	}

	protected function remove_dislike(int $post_id, int $forum_id, int $poster_id, bool $current_user_disliked): JsonResponse
	{
		if ($current_user_disliked)
		{
			$sql = 'DELETE FROM ' . $this->dislikes_table . '
				WHERE post_id = ' . $post_id . '
					AND user_id = ' . (int) $this->user->data['user_id'];
			$this->db->sql_query($sql);
			if ((int) $this->db->sql_affectedrows() > 0)
			{
				$this->invalidate_rank_cache_for_forums([$forum_id]);
				$this->reputation->refresh_user($poster_id);
			}
		}

		return $this->build_success_response($post_id, 'remove', $this->get_post_reaction_counts($post_id));
	}

	protected function resolve_action(string $action, bool $current_user_disliked): string
	{
		if ($action === 'toggle')
		{
			return $current_user_disliked ? 'remove' : 'add';
		}

		return $action;
	}

	protected function get_post_state(int $post_id): array|false
	{
		$sql = 'SELECT p.post_id, p.poster_id, p.forum_id, p.post_visibility,
					ud.user_id AS dislike_user_id,
					ul.user_id AS like_user_id,
					COALESCE(dc.dislike_count, 0) AS dislike_count
				FROM ' . POSTS_TABLE . ' p
			LEFT JOIN ' . $this->dislikes_table . ' ud
				ON ud.post_id = p.post_id
				AND ud.user_id = ' . (int) $this->user->data['user_id'] . '
			LEFT JOIN ' . $this->likes_table . ' ul
				ON ul.post_id = p.post_id
				AND ul.user_id = ' . (int) $this->user->data['user_id'] . '
			LEFT JOIN (
				SELECT post_id, COUNT(*) AS dislike_count
				FROM ' . $this->dislikes_table . '
				WHERE post_id = ' . $post_id . '
				GROUP BY post_id
			) dc
				ON dc.post_id = p.post_id
			WHERE p.post_id = ' . $post_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row;
	}

	protected function get_post_reaction_counts(int $post_id): array
	{
		$sql = 'SELECT
				(SELECT COUNT(*) FROM ' . $this->dislikes_table . ' WHERE post_id = ' . $post_id . ') AS dislike_count,
				(SELECT COUNT(*) FROM ' . $this->likes_table . ' WHERE post_id = ' . $post_id . ') AS like_count';
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result) ?: [];
		$this->db->sql_freeresult($result);

		$dislike_count = (int) ($row['dislike_count'] ?? 0);
		$like_count = (int) ($row['like_count'] ?? 0);

		return [
			'dislike_count' => $dislike_count,
			'like_count' => $like_count,
			'net_dislike_score' => $dislike_count - $like_count,
		];
	}

	protected function has_user_disliked(int $post_id): bool
	{
		$sql = 'SELECT user_id
			FROM ' . $this->dislikes_table . '
			WHERE post_id = ' . $post_id . '
				AND user_id = ' . (int) $this->user->data['user_id'];
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return (bool) $row;
	}

	protected function insert_dislike(int $post_id, int $poster_id): bool
	{
		$sql = 'INSERT INTO ' . $this->dislikes_table . ' ' .
			$this->db->sql_build_array('INSERT', [
				'post_id' => $post_id,
				'user_id' => (int) $this->user->data['user_id'],
				'disliketime' => time(),
				'disliked_user_id' => $poster_id,
			]);

		$this->db->sql_return_on_error(true);
		$this->db->sql_query($sql);
		$affected_rows = (int) $this->db->sql_affectedrows();
		$this->db->sql_return_on_error(false);

		return $affected_rows > 0;
	}

	protected function insert_dislike_history(int $post_id): void
	{
		$sql = 'INSERT INTO ' . $this->dislike_history_table . ' ' .
			$this->db->sql_build_array('INSERT', [
				'post_id' => $post_id,
				'user_id' => (int) $this->user->data['user_id'],
				'action_time' => time(),
			]);
		$this->db->sql_query($sql);
	}

	protected function check_rate_limits(): string
	{
		$counts = $this->get_rate_limit_counts();
		$minute_limit = (int) $this->config['toptopics_downvote_per_minute'];
		$day_limit = (int) $this->config['toptopics_downvote_per_day'];

		if ($minute_limit > 0 && $counts['minute'] >= $minute_limit)
		{
			return $this->language->lang('TOPTOPICS_RATE_LIMIT_MINUTE');
		}

		if ($day_limit > 0 && $counts['day'] >= $day_limit)
		{
			return $this->language->lang('TOPTOPICS_RATE_LIMIT_DAY');
		}

		return '';
	}

	protected function get_rate_limit_counts(): array
	{
		$minute_cutoff = time() - 60;
		$day_cutoff = time() - 86400;
		$sql = 'SELECT
				SUM(CASE WHEN action_time >= ' . $minute_cutoff . ' THEN 1 ELSE 0 END) AS minute_count,
				SUM(CASE WHEN action_time >= ' . $day_cutoff . ' THEN 1 ELSE 0 END) AS day_count
			FROM ' . $this->dislike_history_table . '
			WHERE user_id = ' . (int) $this->user->data['user_id'] . '
				AND action_time >= ' . $day_cutoff;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result) ?: [];
		$this->db->sql_freeresult($result);

		return [
			'minute' => (int) ($row['minute_count'] ?? 0),
			'day' => (int) ($row['day_count'] ?? 0),
		];
	}

	protected function build_success_response(int $post_id, string $action, array $counts): JsonResponse
	{
		$is_disliked = ($action === 'add');
		$dislike_count = (int) ($counts['dislike_count'] ?? 0);
		$net_dislike_score = (int) ($counts['net_dislike_score'] ?? $dislike_count);
		$collapse_threshold = $this->get_post_collapse_dislike_threshold();
		$should_collapse = ($collapse_threshold > 0 && $net_dislike_score >= $collapse_threshold);

		return new JsonResponse([
			'toggle_action' => $action,
			'toggle_post' => $post_id,
			'toggle_title' => $this->language->lang($is_disliked ? 'CLICK_TO_UNDISLIKE' : 'CLICK_TO_DISLIKE'),
			'toggle_count' => $dislike_count,
			'toggle_count_title' => $this->language->lang('TOPTOPICS_DISLIKES_COUNT', $dislike_count),
			'toggle_net_dislike_score' => $net_dislike_score,
			'toggle_fade_class' => $this->get_post_dislike_fade_class($net_dislike_score),
			'toggle_collapse' => $should_collapse,
			'toggle_collapse_message' => $should_collapse ? $this->language->lang('TOPTOPICS_POST_COLLAPSED', $net_dislike_score, $collapse_threshold) : '',
			'toggle_collapse_display_title' => $this->language->lang('POST_DISPLAY'),
			'next_action' => $is_disliked ? 'remove' : 'add',
		]);
	}

	protected function get_post_collapse_dislike_threshold(): int
	{
		return max(0, (int) ($this->config['toptopics_post_collapse_dislike_threshold'] ?? self::DEFAULT_POST_COLLAPSE_DISLIKE_THRESHOLD));
	}

	protected function get_post_dislike_fade_class(int $net_dislike_score): string
	{
		$level = $this->get_post_dislike_fade_level($net_dislike_score);
		return $level > 0 ? 'toptopics-dislike-fade toptopics-dislike-fade-level-' . $level : '';
	}

	protected function get_post_dislike_fade_level(int $net_dislike_score): int
	{
		$threshold = $this->get_post_collapse_dislike_threshold();
		if ($threshold <= 0 || $net_dislike_score <= 0)
		{
			return 0;
		}

		if ($threshold <= 1 || $net_dislike_score >= $threshold)
		{
			return 4;
		}

		$visible_range = max(1, $threshold - 1);
		return max(1, min(4, (int) ceil(($net_dislike_score / $visible_range) * 4)));
	}

	protected function json_error(string $message = ''): JsonResponse
	{
		$message = ($message !== '') ? $message : $this->language->lang('FORM_INVALID');
		$response = [
			'error' => 1,
			'message' => $message,
			'MESSAGE_TITLE' => $this->language->lang('INFORMATION'),
			'MESSAGE_TEXT' => $message,
		];

		return new JsonResponse($response);
	}

	protected function invalidate_rank_cache_for_forums(array $forum_ids): void
	{
		$this->ranker->invalidate_forums($forum_ids);
	}

	protected function format_reputation(int $score): string
	{
		return (string) $score;
	}
}
