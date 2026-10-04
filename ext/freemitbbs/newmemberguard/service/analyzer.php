<?php

namespace freemitbbs\newmemberguard\service;

class analyzer
{
	/** Only posts at least this many visible characters long are flagged as off-topic. */
	const MIN_FLAG_CHARS = 18;

	/** Share of the post's terms that must also occur in the topic before it is considered on-topic. */
	const OFFTOPIC_THRESHOLD = 0.10;

	/** How many recently active topics to score when suggesting a destination. */
	const CANDIDATE_POOL = 120;

	protected \phpbb\db\driver\driver_interface $db;
	protected string $phpbb_root_path;
	protected string $php_ext;

	protected string $posts_table;
	protected string $topics_table;
	protected string $forums_table;
	protected string $users_table;

	public function __construct(\phpbb\db\driver\driver_interface $db, string $table_prefix, string $phpbb_root_path, string $php_ext)
	{
		$this->db = $db;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->posts_table = $table_prefix . 'posts';
		$this->topics_table = $table_prefix . 'topics';
		$this->forums_table = $table_prefix . 'forums';
		$this->users_table = $table_prefix . 'users';
	}

	/**
	 * Analyze a post against the topic it currently sits in.
	 *
	 * @return array|null ['post' => array, 'topic' => array|null, 'post_text' => string, 'score' => float, 'offtopic' => bool]
	 */
	public function analyze(int $post_id): ?array
	{
		$post = $this->post_row($post_id);
		if (!$post)
		{
			return null;
		}

		$topic = $this->topic_context((int) $post['topic_id']);
		$post_text = $this->plain_text((string) $post['post_subject'] . ' ' . (string) $post['post_text'], (string) $post['bbcode_uid']);
		$topic_text = $topic
			? $this->plain_text((string) $topic['topic_title'] . ' ' . (string) ($topic['first_post_text'] ?? ''), (string) ($topic['first_post_bbcode_uid'] ?? ''))
			: '';

		$score = $this->similarity($post_text, $topic_text);
		$length = function_exists('mb_strlen') ? mb_strlen($post_text, 'UTF-8') : strlen($post_text);
		$offtopic = $topic_text !== '' && $length >= self::MIN_FLAG_CHARS && $score < self::OFFTOPIC_THRESHOLD;

		return [
			'post' => $post,
			'topic' => $topic,
			'post_text' => $post_text,
			'score' => $score,
			'offtopic' => $offtopic,
		];
	}

	/**
	 * Return recently active topics scored by lexical similarity to the given post text.
	 *
	 * @return array<int, array{topic_id:int,title:string,forum_id:int,forum_name:string,score:float}>
	 */
	public function candidate_topics(string $post_text, int $exclude_topic_id, int $limit = 8): array
	{
		if (trim($post_text) === '')
		{
			return [];
		}

		$sql = 'SELECT t.topic_id, t.topic_title, t.forum_id, f.forum_name, p.post_text AS first_post_text, p.bbcode_uid AS first_post_bbcode_uid
			FROM ' . $this->topics_table . ' t
			INNER JOIN ' . $this->forums_table . ' f
				ON f.forum_id = t.forum_id
			LEFT JOIN ' . $this->posts_table . ' p
				ON p.post_id = t.topic_first_post_id
			WHERE t.topic_visibility = ' . ITEM_APPROVED . '
				AND t.topic_moved_id = 0
				AND t.topic_id <> ' . (int) $exclude_topic_id . '
				AND f.forum_type = ' . FORUM_POST . '
			ORDER BY t.topic_last_post_time DESC';
		$result = $this->db->sql_query_limit($sql, self::CANDIDATE_POOL);

		$scored = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$candidate_text = $this->plain_text((string) $row['topic_title'] . ' ' . (string) ($row['first_post_text'] ?? ''), (string) ($row['first_post_bbcode_uid'] ?? ''));
			$score = $this->similarity($post_text, $candidate_text);
			if ($score <= 0.0)
			{
				continue;
			}

			$scored[] = [
				'topic_id' => (int) $row['topic_id'],
				'title' => (string) $row['topic_title'],
				'forum_id' => (int) $row['forum_id'],
				'forum_name' => (string) $row['forum_name'],
				'score' => $score,
			];
		}
		$this->db->sql_freeresult($result);

		usort($scored, static function (array $left, array $right): int {
			if ($left['score'] === $right['score'])
			{
				return $right['topic_id'] <=> $left['topic_id'];
			}

			return $left['score'] < $right['score'] ? 1 : -1;
		});

		return array_slice($scored, 0, max(1, $limit));
	}

	public function is_new_member(int $user_id): bool
	{
		if ($user_id <= ANONYMOUS)
		{
			return false;
		}

		$sql = 'SELECT user_new
			FROM ' . $this->users_table . '
			WHERE user_id = ' . $user_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$is_new = (int) $this->db->sql_fetchfield('user_new') === 1;
		$this->db->sql_freeresult($result);

		return $is_new;
	}

	/**
	 * Overlap coefficient: fraction of the first text's terms that also occur in the second.
	 */
	public function similarity(string $first, string $second): float
	{
		$first_terms = $this->terms($first);
		$second_terms = $this->terms($second);
		if (!$first_terms || !$second_terms)
		{
			return 0.0;
		}

		$shared = count(array_intersect_key($first_terms, $second_terms));

		return $shared / max(1, count($first_terms));
	}

	/**
	 * Build a term set from ASCII words (length >= 2) and CJK bigrams.
	 */
	public function terms(string $text): array
	{
		$text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
		$terms = [];

		if (preg_match_all('/[a-z0-9]{2,}/u', $text, $matches))
		{
			foreach ($matches[0] as $word)
			{
				$terms['w:' . $word] = true;
			}
		}

		if (preg_match_all('/[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]+/u', $text, $matches))
		{
			foreach ($matches[0] as $run)
			{
				$length = function_exists('mb_strlen') ? mb_strlen($run, 'UTF-8') : strlen($run);
				if ($length <= 1)
				{
					$terms['c:' . $run] = true;
					continue;
				}

				for ($i = 0; $i + 1 < $length; $i++)
				{
					$terms['c:' . mb_substr($run, $i, 2, 'UTF-8')] = true;
				}
			}
		}

		return $terms;
	}

	public function plain_text(string $text, string $bbcode_uid): string
	{
		if (!function_exists('decode_message'))
		{
			include_once($this->phpbb_root_path . 'includes/functions_content.' . $this->php_ext);
		}

		decode_message($text, $bbcode_uid);
		$text = strip_tags($text);
		$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
		$text = preg_replace('/\[[^\]]+\]/u', ' ', $text) ?? $text;
		$text = preg_replace('/\s+/u', ' ', $text) ?? $text;

		return trim($text);
	}

	protected function post_row(int $post_id): ?array
	{
		$sql = 'SELECT post_id, topic_id, forum_id, poster_id, post_subject, post_text, bbcode_uid, post_visibility
			FROM ' . $this->posts_table . '
			WHERE post_id = ' . $post_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	protected function topic_context(int $topic_id): ?array
	{
		$sql = 'SELECT t.topic_id, t.topic_title, t.forum_id, p.post_text AS first_post_text, p.bbcode_uid AS first_post_bbcode_uid
			FROM ' . $this->topics_table . ' t
			LEFT JOIN ' . $this->posts_table . ' p
				ON p.post_id = t.topic_first_post_id
			WHERE t.topic_id = ' . $topic_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}
}
