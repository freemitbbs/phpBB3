<?php
/**
 * Post Love extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Avathar.be
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace avathar\postlove\service;

class most_liked_posts
{
	private const SECONDS_PER_HOUR = 3600;

	protected \phpbb\auth\auth $auth;
	protected \phpbb\content_visibility $content_visibility;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\event\dispatcher_interface $dispatcher;
	protected \phpbb\user $user;
	protected \phpbb\language\language $language;
	protected string $root_path;
	protected string $php_ext;
	protected string $likes_table;
	protected ?array $foe_user_id_map = null;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\content_visibility $content_visibility,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\event\dispatcher_interface $dispatcher,
		\phpbb\user $user,
		\phpbb\language\language $language,
		string $root_path,
		string $php_ext,
		string $likes_table
	)
	{
		$this->auth = $auth;
		$this->content_visibility = $content_visibility;
		$this->db = $db;
		$this->dispatcher = $dispatcher;
		$this->user = $user;
		$this->language = $language;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->likes_table = $likes_table;
	}

	public function get_readable_forum_ids(): array
	{
		$forum_ids = [];
		foreach ($this->auth->acl_getf('f_read') as $forum_id => $allowed)
		{
			if (!empty($allowed['f_read']))
			{
				$forum_ids[] = (int) $forum_id;
			}
		}

		return array_values(array_unique($forum_ids));
	}

	public function get_period_start_times(?int $now = null): array
	{
		$now = $now ?: time();
		$user_tz = ($this->user->timezone instanceof \DateTimeZone) ? $this->user->timezone : new \DateTimeZone('UTC');
		$local_now = (new \DateTimeImmutable('@' . $now))->setTimezone($user_tz);

		return [
			'ever' => 2,
			'year' => $local_now->setDate((int) $local_now->format('Y'), 1, 1)->setTime(0, 0, 0)->getTimestamp(),
			'month' => $local_now->modify('first day of this month')->setTime(0, 0, 0)->getTimestamp(),
			'week' => $local_now->modify('monday this week')->setTime(0, 0, 0)->getTimestamp(),
		];
	}

	public function get_top_posts(array $forum_ids, int $limit, int $period_start_time, string $period_name, array $excluded_post_ids = []): array
	{
		$limit = max(1, min(100, $limit));
		if (empty($forum_ids))
		{
			return [];
		}

		$excluded_post_ids = array_values(array_unique(array_filter(array_map('intval', $excluded_post_ids), static function ($post_id) {
			return $post_id > 0;
		})));
		$excluded_posts_sql = !empty($excluded_post_ids)
			? ' AND ' . $this->db->sql_in_set('post_id', $excluded_post_ids, true)
			: '';
		$foe_user_id_map = $this->get_current_user_foe_id_map();
		$foe_user_ids = array_map('intval', array_keys($foe_user_id_map));
		$non_foe_poster_sql = !empty($foe_user_ids)
			? ' AND ' . $this->db->sql_in_set('p.poster_id', $foe_user_ids, true)
			: '';

		$sql = 'SELECT u.user_id, u.username, u.user_colour,
				t.topic_title, t.forum_id, t.topic_id,
				most_liked_posts.post_id, most_liked_posts.post_time, most_liked_posts.post_text,
				most_liked_posts.bbcode_uid, most_liked_posts.bbcode_bitfield, most_liked_posts.enable_bbcode,
				most_liked_posts.enable_smilies, most_liked_posts.enable_magic_url, t.topic_type,
				f.forum_name, sum_likes
			FROM (
				SELECT p.forum_id, p.post_id, p.post_time, p.topic_id, p.poster_id,
					p.post_text, p.bbcode_uid, p.bbcode_bitfield,
					p.enable_bbcode, p.enable_smilies, p.enable_magic_url, sum_likes
				FROM (
					SELECT post_id AS post, COUNT(*) AS sum_likes
					FROM ' . $this->likes_table . '
					WHERE liketime > ' . (int) $period_start_time . '
						' . $excluded_posts_sql . '
					GROUP BY post_id
				) AS liked_posts
				LEFT JOIN ' . POSTS_TABLE . ' p ON liked_posts.post = p.post_id
				WHERE ' . $this->content_visibility->get_forums_visibility_sql('post', $forum_ids, 'p.') . '
					' . $non_foe_poster_sql . '
			) AS most_liked_posts
			LEFT JOIN ' . TOPICS_TABLE . ' t ON most_liked_posts.topic_id = t.topic_id
			LEFT JOIN ' . USERS_TABLE . ' u ON most_liked_posts.poster_id = u.user_id
			LEFT JOIN ' . FORUMS_TABLE . ' f ON t.forum_id = f.forum_id
			WHERE t.topic_status <> ' . ITEM_MOVED . '
				AND ' . $this->content_visibility->get_forums_visibility_sql('topic', $forum_ids, 't.') . '
			ORDER BY sum_likes DESC, post_time DESC';

		$query_limit = min(max($limit * 5, $limit), 200);
		$result = $this->db->sql_query_limit($sql, $query_limit, 0, (self::SECONDS_PER_HOUR * 12) - 1);

		$forums = [];
		$rows = [];
		$post_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$poster_id = (int) ($row['user_id'] ?? 0);
			if ($poster_id > 0 && isset($foe_user_id_map[$poster_id]))
			{
				continue;
			}

			$rows[] = $row;
			$post_ids[] = (int) $row['post_id'];
			$forums[(int) $row['forum_id']][] = (int) $row['topic_id'];
			if (count($rows) >= $limit)
			{
				break;
			}
		}
		$this->db->sql_freeresult($result);

		if (empty($rows))
		{
			return [];
		}

		$topic_tracking_info = [];
		foreach ($forums as $forum_id => $topic_ids)
		{
			$topic_tracking_info[$forum_id] = get_complete_topic_tracking($forum_id, $topic_ids);
		}

		$post_likers = $this->get_post_likers_for_period($post_ids, $period_start_time);
		$output = [];
		foreach ($rows as $row)
		{
			$topic_id = (int) $row['topic_id'];
			$forum_id = (int) $row['forum_id'];
			$likers = $post_likers[(int) $row['post_id']] ?? [
				'html' => [],
				'text' => [],
			];
			$post_unread = (isset($topic_tracking_info[$forum_id][$topic_id]) && $row['post_time'] > $topic_tracking_info[$forum_id][$topic_id]);

			$tpl_ary = [
				'POST_ID' => (int) $row['post_id'],
				'U_TOPIC' => append_sid("{$this->root_path}viewtopic.$this->php_ext", 'f=' . $forum_id . '&amp;t=' . $topic_id . '&amp;p=' . $row['post_id'] . '#p' . $row['post_id']),
				'U_FORUM' => append_sid("{$this->root_path}viewforum.$this->php_ext", 'f=' . $forum_id),
				'S_UNREAD' => $post_unread,
				'USERNAME_FULL' => get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
				'POST_TIME' => $this->user->format_date($row['post_time']),
				'TOPIC_TITLE' => censor_text($row['topic_title']),
				'POST_EXCERPT' => $this->build_post_excerpt($row),
				'FORUM_NAME' => $row['forum_name'],
				'POST_LIKES_IN_PERIOD' => $this->language->lang($period_name, (int) $row['sum_likes']),
				'LIKES_IN_PERIOD' => (int) $row['sum_likes'],
				'POST_LIKERS' => htmlspecialchars($this->language->lang('LIKED_BY') . implode(', ', $likers['text']), ENT_COMPAT, 'UTF-8'),
				'POST_LIKERS_LIST' => implode('<br />', $likers['html']),
				'S_HAS_POST_LIKERS' => !empty($likers['html']),
			];

			$vars = ['row', 'tpl_ary'];
			extract($this->dispatcher->trigger_event('avathar.postlove.modify_summary_tpl_ary', compact($vars)));
			$output[] = $tpl_ary;
		}

		return $output;
	}

	protected function get_post_likers_for_period(array $post_ids, int $period_start_time): array
	{
		if (empty($post_ids))
		{
			return [];
		}

		$foe_user_id_map = $this->get_current_user_foe_id_map();
		$foe_user_ids = array_map('intval', array_keys($foe_user_id_map));
		$non_foe_liker_sql = !empty($foe_user_ids)
			? ' AND ' . $this->db->sql_in_set('u.user_id', $foe_user_ids, true)
			: '';

		$sql = 'SELECT pl.post_id, u.user_id, u.username, u.user_colour
			FROM ' . $this->likes_table . ' pl
			JOIN ' . USERS_TABLE . ' u ON u.user_id = pl.user_id
			WHERE ' . $this->db->sql_in_set('pl.post_id', $post_ids) . '
				AND pl.liketime > ' . (int) $period_start_time . '
				' . $non_foe_liker_sql . '
			ORDER BY pl.post_id ASC, pl.liketime ASC';
		$result = $this->db->sql_query($sql);

		$post_likers = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$post_id = (int) $row['post_id'];
			$post_likers[$post_id]['html'][] = get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']);
			$post_likers[$post_id]['text'][] = $row['username'];
		}
		$this->db->sql_freeresult($result);

		return $post_likers;
	}

	protected function get_current_user_foe_id_map(): array
	{
		if ($this->foe_user_id_map !== null)
		{
			return $this->foe_user_id_map;
		}

		$this->foe_user_id_map = [];
		$current_user_id = (int) ($this->user->data['user_id'] ?? ANONYMOUS);
		if ($current_user_id === ANONYMOUS)
		{
			return $this->foe_user_id_map;
		}

		$sql = 'SELECT zebra_id
			FROM ' . ZEBRA_TABLE . '
			WHERE user_id = ' . $current_user_id . '
				AND foe = 1';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$foe_user_id = (int) ($row['zebra_id'] ?? 0);
			if ($foe_user_id > 0)
			{
				$this->foe_user_id_map[$foe_user_id] = true;
			}
		}
		$this->db->sql_freeresult($result);

		return $this->foe_user_id_map;
	}

	protected function build_post_excerpt(array $row): string
	{
		$flags = ((int) $row['enable_bbcode'] ? OPTION_FLAG_BBCODE : 0)
			+ ((int) $row['enable_smilies'] ? OPTION_FLAG_SMILIES : 0)
			+ ((int) $row['enable_magic_url'] ? OPTION_FLAG_LINKS : 0);

		$text = generate_text_for_display(
			$row['post_text'],
			$row['bbcode_uid'],
			$row['bbcode_bitfield'],
			$flags
		);
		$text = $this->strip_quoted_html($text);
		$text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace('/\s+/u', ' ', $text);
		$text = trim((string) $text);

		if ($text === '')
		{
			return $row['topic_title'];
		}

		return truncate_string($text, 120, 255, false, $this->user->lang['ELLIPSIS']);
	}

	protected function strip_quoted_html(string $html): string
	{
		if ($html === '' || stripos($html, '<blockquote') === false || !class_exists(\DOMDocument::class))
		{
			return $html;
		}

		$dom = new \DOMDocument('1.0', 'UTF-8');
		$libxml_previous = libxml_use_internal_errors(true);
		$options = (defined('LIBXML_HTML_NOIMPLIED') ? LIBXML_HTML_NOIMPLIED : 0)
			| (defined('LIBXML_HTML_NODEFDTD') ? LIBXML_HTML_NODEFDTD : 0);
		$loaded = $dom->loadHTML(
			'<?xml encoding="UTF-8"><div id="postlove-excerpt-root">' . $html . '</div>',
			$options
		);
		libxml_clear_errors();
		libxml_use_internal_errors($libxml_previous);

		if (!$loaded)
		{
			return $html;
		}

		$xpath = new \DOMXPath($dom);
		foreach ($xpath->query('//blockquote') as $blockquote)
		{
			if ($blockquote->parentNode)
			{
				$blockquote->parentNode->removeChild($blockquote);
			}
		}

		$root = $xpath->query('//*[@id="postlove-excerpt-root"]')->item(0);
		if (!$root)
		{
			return $html;
		}

		$clean_html = '';
		foreach (iterator_to_array($root->childNodes) as $child)
		{
			$clean_html .= $dom->saveHTML($child);
		}

		return $clean_html;
	}
}
