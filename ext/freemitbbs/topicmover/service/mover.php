<?php

namespace freemitbbs\topicmover\service;

class mover
{
	private const SOURCE_FORUM_ID = 2;
	private const DEFAULT_THRESHOLD = 5;
	private const DEFAULT_ENDPOINT = 'https://api.deepseek.com/chat/completions';
	private const DEFAULT_MODEL = 'deepseek-chat';
	private const DEFAULT_MIN_LATEST_REPLY_AGE_HOURS = 12;
	private const MIN_CONFIDENCE = 0.70;
	private const API_TIMEOUT_SECONDS = 25;
	private const FIRST_POST_MAX_CHARS = 4000;
	private const REPLY_MAX_CHARS = 1600;
	private const FORUM_DESC_MAX_CHARS = 700;

	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\log\log_interface $log;
	protected string $phpbb_root_path;
	protected string $php_ext;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\log\log_interface $log,
		string $phpbb_root_path,
		string $php_ext
	)
	{
		$this->config = $config;
		$this->db = $db;
		$this->log = $log;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	public function is_configured(): bool
	{
		return $this->api_key() !== '' && $this->api_endpoint() !== '';
	}

	public function has_candidates(): bool
	{
		return count($this->candidate_topics(1)) > 0;
	}

	public function process_candidates(): array
	{
		$result = [
			'checked' => 0,
			'moved' => 0,
			'skipped' => 0,
			'errors' => 0,
		];

		if (!$this->is_configured())
		{
			return $result;
		}

		$forums = $this->destination_forums();
		if (!$forums)
		{
			return $result;
		}

		$forum_ids = array_fill_keys(array_map('intval', array_keys($forums)), true);
		foreach ($this->candidate_topics() as $topic)
		{
			$result['checked']++;
			$topic_id = (int) $topic['topic_id'];

			try
			{
				$context = $this->topic_context($topic);
				$decision = $this->classify_topic($context, $forums);
				$destination_forum_id = (int) ($decision['destination_forum_id'] ?? 0);
				$confidence = (float) ($decision['confidence'] ?? 0);
				$reason = trim((string) ($decision['reason'] ?? ''));

				if ($destination_forum_id <= 0 || !isset($forum_ids[$destination_forum_id]) || $confidence < self::MIN_CONFIDENCE)
				{
					$result['skipped']++;
					continue;
				}

				$this->move_topic($topic_id, $destination_forum_id);
				$this->log->add('admin', ANONYMOUS, '', 'LOG_TOPICMOVER_MOVED', false, [
					(string) $topic_id,
					(string) self::SOURCE_FORUM_ID,
					(string) $destination_forum_id,
					$reason,
				]);
				$result['moved']++;
			}
			catch (\Throwable $e)
			{
				$this->log->add('admin', ANONYMOUS, '', 'LOG_TOPICMOVER_FAILED', false, [
					(string) $topic_id,
					$e->getMessage(),
				]);
				$result['errors']++;
			}
		}

		return $result;
	}

	protected function candidate_topics(int $limit = 0): array
	{
		$threshold = max(0, min(100000, (int) ($this->config['topicmover_threshold'] ?? self::DEFAULT_THRESHOLD)));
		$min_latest_reply_age_hours = $this->min_latest_reply_age_hours();
		$non_author_reply_count_sql = '(SELECT COUNT(p.post_id)
			FROM ' . POSTS_TABLE . ' p
			WHERE p.topic_id = t.topic_id
				AND p.post_visibility = ' . ITEM_APPROVED . '
				AND p.post_id <> t.topic_first_post_id
				AND p.poster_id <> t.topic_poster)';
		$sql_where = [
			't.forum_id = ' . self::SOURCE_FORUM_ID,
			't.topic_moved_id = 0',
			't.topic_visibility = ' . ITEM_APPROVED,
			$non_author_reply_count_sql . ' > ' . $threshold,
			't.topic_last_post_time <= ' . (time() - ($min_latest_reply_age_hours * 3600)),
			'COALESCE(u.topicmover_no_move, 0) = 0',
		];
		$excluded_user_ids = $this->excluded_user_ids();
		if ($excluded_user_ids)
		{
			$sql_where[] = $this->db->sql_in_set('t.topic_poster', array_map('intval', array_keys($excluded_user_ids)), true);
		}

		$sql = 'SELECT t.topic_id, t.forum_id, t.topic_title, t.topic_first_post_id, t.topic_last_post_time, ' . $non_author_reply_count_sql . ' AS topic_replies, t.topic_posts_approved, t.topic_visibility, t.topic_moved_id, t.topic_poster
			FROM ' . TOPICS_TABLE . ' t
			LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = t.topic_poster
			WHERE ' . implode(' AND ', $sql_where) . '
			ORDER BY t.topic_last_post_time DESC';
		$result = $limit > 0 ? $this->db->sql_query_limit($sql, $limit) : $this->db->sql_query($sql);
		$topics = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topics[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $topics;
	}

	protected function destination_forums(): array
	{
		$excluded = $this->excluded_forum_ids();
		$excluded[self::SOURCE_FORUM_ID] = true;

		$sql_where = [
			'forum_type = ' . FORUM_POST,
			'forum_status = ' . ITEM_UNLOCKED,
			"forum_password = ''",
		];
		if ($excluded)
		{
			$sql_where[] = $this->db->sql_in_set('forum_id', array_map('intval', array_keys($excluded)), true);
		}

		$sql = 'SELECT forum_id, forum_name, forum_desc, forum_desc_uid
			FROM ' . FORUMS_TABLE . '
			WHERE ' . implode(' AND ', $sql_where) . '
			ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql);
		$forums = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$forum_id = (int) $row['forum_id'];
			$forums[$forum_id] = [
				'forum_id' => $forum_id,
				'name' => $this->clean_text((string) $row['forum_name'], ''),
				'description' => $this->truncate($this->clean_text((string) $row['forum_desc'], (string) $row['forum_desc_uid']), self::FORUM_DESC_MAX_CHARS),
			];
		}
		$this->db->sql_freeresult($result);

		return $forums;
	}

	protected function topic_context(array $topic): array
	{
		$topic_id = (int) $topic['topic_id'];
		$first_post_id = (int) $topic['topic_first_post_id'];
		$first_post = $this->post_by_id($first_post_id);
		$replies = [];

		$sql = 'SELECT post_id, post_subject, post_text, bbcode_uid, post_time, poster_id, post_username
			FROM ' . POSTS_TABLE . '
			WHERE topic_id = ' . $topic_id . '
				AND post_id <> ' . $first_post_id . '
				AND post_visibility = ' . ITEM_APPROVED . '
			ORDER BY post_time DESC';
		$result = $this->db->sql_query_limit($sql, 5);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$replies[] = $this->post_context($row, self::REPLY_MAX_CHARS);
		}
		$this->db->sql_freeresult($result);

		return [
			'topic_id' => $topic_id,
			'title' => $this->clean_text((string) $topic['topic_title'], ''),
			'reply_count' => (int) $topic['topic_replies'],
			'first_post' => $first_post ? $this->post_context($first_post, self::FIRST_POST_MAX_CHARS) : null,
			'latest_replies' => array_reverse($replies),
		];
	}

	protected function post_by_id(int $post_id): ?array
	{
		if ($post_id <= 0)
		{
			return null;
		}

		$sql = 'SELECT post_id, post_subject, post_text, bbcode_uid, post_time, poster_id, post_username
			FROM ' . POSTS_TABLE . '
			WHERE post_id = ' . $post_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	protected function post_context(array $row, int $max_chars): array
	{
		return [
			'post_id' => (int) $row['post_id'],
			'post_time' => gmdate('c', (int) $row['post_time']),
			'poster_id' => (int) $row['poster_id'],
			'poster_name' => (string) $row['post_username'],
			'subject' => $this->clean_text((string) $row['post_subject'], ''),
			'text' => $this->truncate($this->clean_text((string) $row['post_text'], (string) $row['bbcode_uid']), $max_chars),
		];
	}

	protected function classify_topic(array $topic_context, array $forums): array
	{
		$payload = [
			'model' => (string) ($this->config['topicmover_model'] ?? self::DEFAULT_MODEL),
			'temperature' => 0.1,
			'response_format' => ['type' => 'json_object'],
			'messages' => [
				[
					'role' => 'system',
					'content' => '你是 phpBB 论坛主题分类助手。请从给定的版面列表中，为主题选择唯一最合适的目标版面。只能返回 JSON，不要返回 Markdown 或额外说明。JSON 必须包含 destination_forum_id、confidence、reason 三个键。destination_forum_id 必须是给定 forum_id 之一，无法确定时用 null。confidence 是 0 到 1 的数字。无法明确判断时请用 null 和较低 confidence。',
				],
				[
					'role' => 'user',
					'content' => $this->encode_json([
						'instruction' => '只有当某个公开版面明显比来源版面 ID 2 更适合时，才建议移动该主题。不要选择 forum_id=2。如果不确定，请将 destination_forum_id 设为 null。',
						'allowed_destination_forums' => array_values($forums),
						'topic' => $topic_context,
					]),
				],
			],
		];

		$response = $this->api_request($payload);
		$content = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
		$decoded = json_decode($this->extract_json_object($content), true);
		if (!is_array($decoded))
		{
			throw new \RuntimeException('Classifier response was not valid JSON.');
		}

		return $decoded;
	}

	protected function api_request(array $payload): array
	{
		$body = $this->encode_json($payload);
		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->api_key(),
		];

		if (function_exists('curl_init'))
		{
			$curl = curl_init($this->api_endpoint());
			curl_setopt_array($curl, [
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $body,
				CURLOPT_HTTPHEADER => $headers,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => 8,
				CURLOPT_TIMEOUT => self::API_TIMEOUT_SECONDS,
			]);
			$response_body = curl_exec($curl);
			$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
			$error = curl_error($curl);
			curl_close($curl);

			if ($response_body === false)
			{
				throw new \RuntimeException('Classifier API request failed: ' . $error);
			}

			return $this->decode_api_response((string) $response_body, $status);
		}

		$options = [
			'http' => [
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => $body,
				'timeout' => self::API_TIMEOUT_SECONDS,
				'ignore_errors' => true,
			],
		];
		$response_body = file_get_contents($this->api_endpoint(), false, stream_context_create($options));
		$status = 0;
		foreach (($http_response_header ?? []) as $header)
		{
			if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match))
			{
				$status = (int) $match[1];
				break;
			}
		}
		if ($response_body === false)
		{
			throw new \RuntimeException('Classifier API request failed.');
		}

		return $this->decode_api_response((string) $response_body, $status);
	}

	protected function decode_api_response(string $response_body, int $status): array
	{
		if ($status < 200 || $status >= 300)
		{
			throw new \RuntimeException('Classifier API returned HTTP ' . $status . ': ' . $this->truncate($response_body, 500));
		}

		$data = json_decode($response_body, true);
		if (!is_array($data))
		{
			throw new \RuntimeException('Classifier API returned invalid JSON.');
		}

		return $data;
	}

	protected function move_topic(int $topic_id, int $destination_forum_id): void
	{
		if ($topic_id <= 0 || $destination_forum_id <= 0 || $destination_forum_id === self::SOURCE_FORUM_ID)
		{
			throw new \RuntimeException('Invalid topic move target.');
		}

		if (!function_exists('move_topics'))
		{
			include_once($this->phpbb_root_path . 'includes/functions_admin.' . $this->php_ext);
		}

		move_topics([$topic_id], $destination_forum_id, true);
	}

	protected function excluded_forum_ids(): array
	{
		$ids = [];
		foreach (preg_split('/[,\s]+/', (string) ($this->config['topicmover_excluded_forum_ids'] ?? '')) ?: [] as $part)
		{
			$id = (int) trim($part);
			if ($id > 0)
			{
				$ids[$id] = true;
			}
		}

		return $ids;
	}

	protected function excluded_user_ids(): array
	{
		$ids = [];
		foreach (preg_split('/[,\s]+/', (string) ($this->config['topicmover_excluded_user_ids'] ?? '')) ?: [] as $part)
		{
			$id = (int) trim($part);
			if ($id > 0)
			{
				$ids[$id] = true;
			}
		}

		return $ids;
	}

	protected function api_endpoint(): string
	{
		$endpoint = trim((string) ($this->config['topicmover_api_endpoint'] ?? self::DEFAULT_ENDPOINT));

		return $endpoint !== '' ? $endpoint : self::DEFAULT_ENDPOINT;
	}

	protected function min_latest_reply_age_hours(): int
	{
		return max(0, min(8760, (int) ($this->config['topicmover_min_latest_reply_age_hours'] ?? self::DEFAULT_MIN_LATEST_REPLY_AGE_HOURS)));
	}

	protected function api_key(): string
	{
		return trim((string) ($this->config['topicmover_api_key'] ?? ''));
	}

	protected function clean_text(string $text, string $bbcode_uid): string
	{
		if (!function_exists('decode_message'))
		{
			include_once($this->phpbb_root_path . 'includes/functions_content.' . $this->php_ext);
		}

		decode_message($text, $bbcode_uid);
		$text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace('/\[[^\]]+\]/u', ' ', $text) ?? $text;
		$text = preg_replace('/\s+/u', ' ', $text) ?? $text;

		return trim($text);
	}

	protected function truncate(string $text, int $max_chars): string
	{
		if ($max_chars <= 0)
		{
			return '';
		}
		if (function_exists('mb_strlen') && function_exists('mb_substr'))
		{
			return mb_strlen($text, 'UTF-8') > $max_chars ? mb_substr($text, 0, $max_chars, 'UTF-8') . '...' : $text;
		}

		return strlen($text) > $max_chars ? substr($text, 0, $max_chars) . '...' : $text;
	}

	protected function extract_json_object(string $text): string
	{
		if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $text, $match))
		{
			return trim($match[1]);
		}
		if (preg_match('/\{.*\}/s', $text, $match))
		{
			return trim($match[0]);
		}

		return $text;
	}

	protected function encode_json(array $data): string
	{
		$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return $json === false ? '{}' : $json;
	}
}
