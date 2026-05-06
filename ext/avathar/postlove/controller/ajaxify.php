<?php
/**
 * Post Love extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2014 Stanislav Atanasov
 * @copyright (c) 2026 Avathar.be
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace avathar\postlove\controller;

/**
 * AJAX controller for the like/unlike toggle.
 *
 * Handles the POST /postlove/toggle/{post_id} route. Returns a JsonResponse
 * with toggle_action (add/remove), toggle_title (tooltip text), and
 * toggle_likers (updated likers string) on success, or {error: 1} on failure.
 *
 * Permission checks:
 * - User must have the u_postlove ACL permission
 * - Author self-like is controlled by the postlove_author_like config setting
 * - Post must exist
 */
class ajaxify
{
	private const SUMMARY_CACHE_VERSION_CONFIG = 'postlove_summary_cache_version';

	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\content_visibility $content_visibility;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\user $user;
	protected \phpbb\language\language $language;
	protected \phpbb\cache\service $cache;
	protected notifyhelper $notifyhelper;
	protected $toptopics_cache_invalidator;
	protected $toptopics_reputation;
	protected string $likes_table;
	protected string $table_prefix;

	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\content_visibility $content_visibility, \phpbb\db\driver\driver_interface $db, \phpbb\request\request_interface $request, \phpbb\user $user, \phpbb\language\language $language, \phpbb\cache\service $cache, \avathar\postlove\controller\notifyhelper $notifyhelper, $toptopics_cache_invalidator, $toptopics_reputation,
									$likes_table, $table_prefix)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->content_visibility = $content_visibility;
		$this->db = $db;
		$this->request = $request;
		$this->user = $user;
		$this->language = $language;
		$this->cache = $cache;
		$this->notifyhelper = $notifyhelper;
		$this->toptopics_cache_invalidator = $toptopics_cache_invalidator;
		$this->toptopics_reputation = $toptopics_reputation;
		$this->likes_table = $likes_table;
		$this->table_prefix = $table_prefix;
	}

	/**
	 * Handle the like toggle action.
	 *
	 * @param string $action The action to perform ('toggle')
	 * @param int    $post   The post ID to like/unlike
	 * @return \Symfony\Component\HttpFoundation\JsonResponse|int
	 */
	public function base ($action, $post)
	{
		switch ($action)
		{
			case 'toggle':
				if ($this->user->data['user_id'] == ANONYMOUS || !$this->auth->acl_get('u_postlove'))
				{
					return new \Symfony\Component\HttpFoundation\JsonResponse(array(
						'error'	=> 1
					));
				}
				if (!check_link_hash($this->request->variable('hash', ''), 'postlove_toggle_' . (int) $post))
				{
					return new \Symfony\Component\HttpFoundation\JsonResponse(array(
						'error' => 1,
						'message' => $this->language->lang('FORM_INVALID'),
					));
				}
				else
				{
					//get state for the like
					$sql_array = array(
						'SELECT'	=> 'pl.liketime as liketime, pl.user_id as liker_id, p.topic_id as topic_id, p.poster_id as poster_id, p.post_subject as post_subject, p.forum_id as forum_id, p.post_visibility as post_visibility',
						'FROM'	=> array(
							POSTS_TABLE	=> 'p',
						),
						'LEFT_JOIN'	=> array(
							array(
								'FROM'	=> array($this->likes_table	=> 'pl'),
								'ON'	=> 'pl.post_id = p.post_id AND pl.user_id = ' . $this->user->data['user_id']
							),
						),
						'WHERE'	=> 'p.post_id = ' . (int) $post
					);
					$sql = $this->db->sql_build_query('SELECT', $sql_array);
					$result = $this->db->sql_query($sql);
					$row = $this->db->sql_fetchrow($result);
					$this->db->sql_freeresult($result);
					if (!$row
						|| !$this->auth->acl_get('f_read', (int) $row['forum_id'])
						|| !$this->content_visibility->is_visible('post', (int) $row['forum_id'], $row)
						|| (!$this->config['postlove_author_like'] && $row['poster_id'] == $this->user->data['user_id']))
					{
						return new \Symfony\Component\HttpFoundation\JsonResponse(array(
							'error'	=> 1
						));
					}

					else
					{
							if (!$row['liketime'])
							{
								if ($this->has_user_disliked((int) $post))
								{
									return new \Symfony\Component\HttpFoundation\JsonResponse(array(
										'error' => 1,
										'message' => $this->language->lang('POSTLOVE_REMOVE_DISLIKE_FIRST'),
									));
								}

								//so we don't have record for this user loving this post ... give some love!
								$sql = 'INSERT INTO ' . $this->likes_table . ' (post_id, user_id, type, liketime, liked_user_id) VALUES (' . (int) $post . ', ' . $this->user->data['user_id'] . ', \'post\', ' . time() . ', ' . $row['poster_id'] . ')';
								$this->db->sql_return_on_error(true);
								$this->db->sql_query($sql);
								$inserted = (int) $this->db->sql_affectedrows() > 0;
								$this->db->sql_return_on_error(false);

								if (!$inserted && !$this->has_user_liked((int) $post))
								{
									return new \Symfony\Component\HttpFoundation\JsonResponse(array(
										'error' => 1
									));
								}

								if ($inserted)
								{
									$this->cache->destroy('sql', $this->likes_table);
									$this->invalidate_summary_cache();
									$this->invalidate_toptopics_state((int) $row['forum_id'], (int) $row['poster_id'], (int) $post);
									$this->notifyhelper->notify('add', $row['topic_id'], (int) $post, $row['post_subject'], $row['poster_id'] , $this->user->data['user_id']);
								}

								$likers = $this->get_likers_data((int) $post);
								return new \Symfony\Component\HttpFoundation\JsonResponse(array(
									'toggle_action'	=> 'add',
									'toggle_post'	=> $post,
									'toggle_title'	=> $this->language->lang('CLICK_TO_UNLIKE'),
									'toggle_likers'	=> $likers['string'],
									'toggle_likers_count' => $likers['count'],
								));
							}
							else
						{
							//so we have a record ... and the user don't love it anymore!
								$sql = 'DELETE FROM ' . $this->likes_table . ' WHERE post_id = ' . (int) $post . ' AND user_id = ' . (int) $this->user->data['user_id'];
								$this->db->sql_query($sql);
								$deleted = (int) $this->db->sql_affectedrows() > 0;

								if ($deleted)
								{
									$this->cache->destroy('sql', $this->likes_table);
									$this->invalidate_summary_cache();
									$this->invalidate_toptopics_state((int) $row['forum_id'], (int) $row['poster_id'], (int) $post);
									$this->notifyhelper->notify('remove', $row['topic_id'], (int) $post, $row['post_subject'], $row['poster_id'], $this->user->data['user_id']);
								}
								$likers = $this->get_likers_data((int) $post);
								return new \Symfony\Component\HttpFoundation\JsonResponse(array(
									'toggle_action' => 'remove',
									'toggle_post'	=> $post,
									'toggle_likers'	=> $likers['string'],
									'toggle_likers_count' => $likers['count'],
									'toggle_title'	=> $this->language->lang('CLICK_TO_LIKE'),
								));
							}
					}
				}
			break;
		}
		// Fallback for unhandled actions
		return new \Symfony\Component\HttpFoundation\JsonResponse(array(
			'error' => 1,
			'message' => $this->language->lang('FORM_INVALID'),
		));
	}

	/**
	 * Build the "liked by: user1, user2" tooltip string for a post.
	 *
	 * @param int $post_id The post ID
	 * @return string The formatted likers string, or empty if no likes
	 */
	protected function get_likers_string(int $post_id): string
	{
		return $this->get_likers_data($post_id)['string'];
	}

	protected function get_likers_data(int $post_id): array
	{
		$sql = 'SELECT u.username
			FROM ' . $this->likes_table . ' pl
			JOIN ' . USERS_TABLE . ' u ON u.user_id = pl.user_id
			WHERE pl.post_id = ' . (int) $post_id . '
			ORDER BY pl.liketime ASC';
		$result = $this->db->sql_query($sql);
		$likers = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$likers[] = $row['username'];
		}
		$this->db->sql_freeresult($result);

		if (empty($likers))
		{
			return [
				'string' => '',
				'count' => 0,
			];
		}

		return [
			'string' => $this->language->lang('LIKED_BY') . implode(', ', $likers),
			'count' => count($likers),
		];
	}

	protected function invalidate_summary_cache(): void
	{
		$this->config->set(self::SUMMARY_CACHE_VERSION_CONFIG, (string) microtime(true));
	}

	protected function has_user_liked(int $post_id): bool
	{
		$sql = 'SELECT user_id
			FROM ' . $this->likes_table . '
			WHERE post_id = ' . $post_id . '
				AND user_id = ' . (int) $this->user->data['user_id'];
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return (bool) $row;
	}

	protected function has_user_disliked(int $post_id): bool
	{
		$sql = 'SELECT user_id
			FROM ' . $this->table_prefix . 'posts_dislikes
			WHERE post_id = ' . $post_id . '
				AND user_id = ' . (int) $this->user->data['user_id'];

		$this->db->sql_return_on_error(true);
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $result ? $this->db->sql_fetchrow($result) : false;
		if ($result)
		{
			$this->db->sql_freeresult($result);
		}
		$this->db->sql_return_on_error(false);

		return (bool) $row;
	}

	protected function invalidate_toptopics_state(int $forum_id, int $poster_id, int $post_id = 0): void
	{
		if ($forum_id > 0 && is_object($this->toptopics_cache_invalidator) && method_exists($this->toptopics_cache_invalidator, 'invalidate_forums'))
		{
			$this->toptopics_cache_invalidator->invalidate_forums([$forum_id]);
		}

		if (!is_object($this->toptopics_reputation))
		{
			return;
		}

		if ($post_id > 0 && method_exists($this->toptopics_reputation, 'refresh_post_context'))
		{
			$this->toptopics_reputation->refresh_post_context($post_id);
			return;
		}

		if ($poster_id > 0 && $poster_id !== ANONYMOUS && method_exists($this->toptopics_reputation, 'refresh_user'))
		{
			$this->toptopics_reputation->refresh_user($poster_id);
		}
		else if ($poster_id > 0 && $poster_id !== ANONYMOUS && method_exists($this->toptopics_reputation, 'invalidate_user'))
		{
			$this->toptopics_reputation->invalidate_user($poster_id);
		}
	}
}
