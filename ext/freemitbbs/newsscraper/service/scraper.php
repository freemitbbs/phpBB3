<?php

namespace freemitbbs\newsscraper\service;

class scraper
{
	private const DEFAULT_ENDPOINT = 'https://api.deepseek.com/chat/completions';
	private const DEFAULT_MODEL = 'deepseek-v4-flash';
	private const DEPRECATED_MODELS = ['deepseek-chat', 'deepseek-reasoner'];
	private const DEFAULT_DIGEST_FORUM_NAME = '新闻摘要';
	private const GUEST_POSTER_NAME = '新闻摘要';
	private const HTTP_CONNECT_TIMEOUT_SECONDS = 10;
	private const HTTP_TIMEOUT_SECONDS = 20;
	private const API_TIMEOUT_SECONDS = 60;
	private const MAX_SOURCE_BYTES = 2097152;
	private const MAX_ARTICLE_CHARS = 6500;
	private const MAX_DIGEST_CHARS = 1200;
	private const MIN_ARTICLE_CHARS = 300;
	private const DIGEST_CONTEXT_LIMIT = 100;
	private const RTNG_DEFAULT_TPL_LOOP = 'rtng_topics';
	private const RTNG_RECENT_CONTEXT_LIMIT = 30;
	private const RTNG_JUNBAN_TPL_LOOP = 'rtng_junban_topics';
	private const RTNG_JUNBAN_CONTEXT_LIMIT = 10;

	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\log\log_interface $log;
	protected string $seen_table;
	protected string $phpbb_root_path;
	protected string $php_ext;
	protected $recenttopicsng_functions = false;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\log\log_interface $log,
		string $seen_table,
		string $phpbb_root_path,
		string $php_ext
	)
	{
		$this->config = $config;
		$this->db = $db;
		$this->log = $log;
		$this->seen_table = $seen_table;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	public function is_configured(): bool
	{
		return (int) ($this->config['newsscraper_enabled'] ?? 0) === 1
			&& $this->digest_forum_id() > 0
			&& $this->api_key() !== ''
			&& $this->api_endpoint() !== ''
			&& !empty($this->enabled_sources());
	}

	public function process(): array
	{
		$result = [
			'discovered' => 0,
			'evaluated' => 0,
			'selected' => 0,
			'posted' => 0,
			'rejected' => 0,
			'failed' => 0,
		];

		if (!$this->is_configured())
		{
			return $result;
		}

		$this->purge_old_seen_rows();
		$candidates = $this->discover_candidates();
		$result['discovered'] = count($candidates);
		if (!$candidates)
		{
			return $result;
		}

		$fresh_candidates = $this->filter_unseen_candidates($candidates);
		$fresh_candidates = array_slice($fresh_candidates, 0, $this->candidate_limit());
		if (!$fresh_candidates)
		{
			return $result;
		}

		$fresh_candidates = $this->record_candidates($fresh_candidates);
		if (!$fresh_candidates)
		{
			return $result;
		}
		$result['evaluated'] = count($fresh_candidates);

		try
		{
			$selected = $this->select_interesting_candidates($fresh_candidates);
		}
		catch (\Throwable $e)
		{
			$this->delete_seen_candidates($fresh_candidates);
			$this->log->add('admin', ANONYMOUS, '', 'LOG_NEWSSCRAPER_FAILED', false, [
				'title_filter',
				'',
				$e->getMessage(),
			]);
			$result['failed'] = count($fresh_candidates);

			return $result;
		}
		$result['selected'] = count($selected);
		$selected_hashes = [];
		foreach ($selected as $candidate)
		{
			$selected_hashes[$candidate['url_hash']] = true;
		}

		foreach ($fresh_candidates as $candidate)
		{
			if (!isset($selected_hashes[$candidate['url_hash']]))
			{
				$this->update_seen($candidate, 'rejected', (int) ($candidate['score'] ?? 0), (string) ($candidate['reason'] ?? 'not selected'));
				$result['rejected']++;
			}
		}

		$posted_by_source = [];
		$posted_digest_titles = $this->recent_posted_titles_for_dedupe();
		$forum_id = $this->digest_forum_id();
		$post_target = $this->max_selected_per_run();
		foreach ($selected as $candidate)
		{
			if ($result['posted'] >= $post_target)
			{
				$this->update_seen($candidate, 'rejected', (int) ($candidate['score'] ?? 0), 'backup not needed');
				$result['rejected']++;
				continue;
			}

			$source_key = (string) $candidate['source_key'];
			$posted_by_source[$source_key] = $posted_by_source[$source_key] ?? 0;
			if ($posted_by_source[$source_key] >= $this->per_source_cap())
			{
				$this->update_seen($candidate, 'rejected', (int) ($candidate['score'] ?? 0), 'per-source cap');
				$result['rejected']++;
				continue;
			}

			if ($this->title_matches_any((string) ($candidate['title'] ?? ''), $posted_digest_titles))
			{
				$this->update_seen($candidate, 'rejected', (int) ($candidate['score'] ?? 0), 'duplicate source title');
				$result['rejected']++;
				continue;
			}

			try
			{
				$article_text = $this->fetch_article_text($candidate);
				$article_length = $this->strlen($article_text);
				if ($article_length < self::MIN_ARTICLE_CHARS)
				{
					throw new \RuntimeException('Article text too short (' . $article_length . ' chars).');
				}

				$digest = $this->generate_digest($candidate, $article_text);
				if ($this->title_matches_any((string) ($digest['title'] ?? ''), $posted_digest_titles)
					|| $this->title_matches_any((string) ($candidate['title'] ?? ''), $posted_digest_titles))
				{
					$this->update_seen($candidate, 'rejected', (int) ($candidate['score'] ?? 0), 'duplicate digest title');
					$result['rejected']++;
					continue;
				}
				$topic_id = $this->post_digest($forum_id, $candidate, $digest);
				$candidate['topic_id'] = $topic_id;
				$this->update_seen($candidate, 'posted', (int) ($candidate['score'] ?? 0), '', $topic_id);
				$posted_by_source[$source_key]++;
				$posted_digest_titles[] = (string) ($digest['title'] ?? '');
				$posted_digest_titles[] = (string) ($candidate['title'] ?? '');
				$result['posted']++;
			}
			catch (\Throwable $e)
			{
				$this->update_seen($candidate, 'failed', (int) ($candidate['score'] ?? 0), $this->truncate($e->getMessage(), 250));
				$this->log->add('admin', ANONYMOUS, '', 'LOG_NEWSSCRAPER_FAILED', false, [
					(string) ($candidate['source_key'] ?? ''),
					(string) ($candidate['url'] ?? ''),
					$e->getMessage(),
				]);
				$result['failed']++;
			}
		}

		return $result;
	}

	protected function discover_candidates(): array
	{
		$candidates_by_source = [];
		$seen_hashes = [];
		$sources = $this->source_configs();
		$enabled_sources = $this->enabled_sources();
		$limit = max($this->candidate_limit(), $this->candidate_limit() * 2);

		foreach ($enabled_sources as $source_key)
		{
			if (!isset($sources[$source_key]))
			{
				continue;
			}

			try
			{
				$source = $sources[$source_key] + ['key' => $source_key];
				$html = $this->fetch_url((string) $source['url']);
				$source_candidates = (($source['type'] ?? '') === 'feed')
					? $this->parse_feed($source, $html)
					: $this->parse_listing($source, $html);

				$candidates_by_source[$source_key] = [];
				foreach ($source_candidates as $candidate)
				{
					$hash = (string) ($candidate['url_hash'] ?? '');
					if ($hash === '' || isset($seen_hashes[$hash]))
					{
						continue;
					}
					$seen_hashes[$hash] = true;
					$candidates_by_source[$source_key][] = $candidate;
				}
			}
			catch (\Throwable $e)
			{
				$this->log->add('admin', ANONYMOUS, '', 'LOG_NEWSSCRAPER_SOURCE_FAILED', false, [
					$source_key,
					$e->getMessage(),
				]);
			}
		}

		return $this->interleave_source_candidates($candidates_by_source, $limit);
	}

	protected function interleave_source_candidates(array $candidates_by_source, int $limit): array
	{
		$candidates = [];
		$offset = 0;
		do
		{
			$added = false;
			foreach ($candidates_by_source as $source_candidates)
			{
				if (!isset($source_candidates[$offset]))
				{
					continue;
				}

				$candidates[] = $source_candidates[$offset];
				$added = true;
				if (count($candidates) >= $limit)
				{
					return $candidates;
				}
			}
			$offset++;
		}
		while ($added);

		return $candidates;
	}

	protected function parse_feed(array $source, string $xml): array
	{
		$items = [];
		$parsed = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
		if (!$parsed)
		{
			return $items;
		}

		if (isset($parsed->channel->item))
		{
			foreach ($parsed->channel->item as $item)
			{
				$title = $this->normalize_text((string) $item->title);
				$link = $this->canonicalize_url((string) $item->link, (string) $source['url']);
				$published = strtotime((string) $item->pubDate) ?: 0;
				$this->add_candidate($items, $source, $title, $link, $published);
			}
		}

		if (isset($parsed->entry))
		{
			foreach ($parsed->entry as $entry)
			{
				$title = $this->normalize_text((string) $entry->title);
				$link = '';
				foreach ($entry->link as $link_node)
				{
					$attrs = $link_node->attributes();
					$rel = (string) ($attrs['rel'] ?? '');
					if ($rel === '' || $rel === 'alternate')
					{
						$link = (string) ($attrs['href'] ?? $link_node);
						break;
					}
				}
				$link = $this->canonicalize_url($link, (string) $source['url']);
				$published = strtotime((string) ($entry->published ?: $entry->updated)) ?: 0;
				$this->add_candidate($items, $source, $title, $link, $published);
			}
		}

		return $items;
	}

	protected function parse_listing(array $source, string $html): array
	{
		$items = [];
		$dom = $this->load_html($html);
		if (!$dom)
		{
			return $items;
		}

		$xpath = new \DOMXPath($dom);
		foreach ($xpath->query('//a[@href]') ?: [] as $node)
		{
			if (!$node instanceof \DOMElement)
			{
				continue;
			}
			$title = $this->normalize_text($node->textContent);
			$link = $this->canonicalize_url((string) $node->getAttribute('href'), (string) $source['url']);
			if (!$this->is_listing_article_link($source, $title, $link))
			{
				continue;
			}
			$this->add_candidate($items, $source, $title, $link, 0);
		}

		return $items;
	}

	protected function add_candidate(array &$items, array $source, string $title, string $url, int $published_time): void
	{
		$title = $this->normalize_text($title);
		$url = $this->canonicalize_url($url, (string) $source['url']);
		if ($title === '' || $url === '' || $this->strlen($title) < 8)
		{
			return;
		}

		$items[] = [
			'id' => count($items) + 1,
			'source_key' => (string) $source['key'],
			'source_label' => (string) $source['label'],
			'title' => $this->truncate($title, 250),
			'url' => $url,
			'url_hash' => hash('sha256', $url),
			'published_time' => $published_time,
		];
	}

	protected function is_listing_article_link(array $source, string $title, string $url): bool
	{
		if ($title === '' || $url === '' || $this->strlen($title) < 8)
		{
			return false;
		}

		if (isset($source['article_url_regex']) && !preg_match((string) $source['article_url_regex'], $url))
		{
			return false;
		}

		if (!isset($source['article_url_regex']) && !$this->same_host_family($url, (string) $source['url']))
		{
			return false;
		}

		return !preg_match('/^(更多|首页|新闻|视频|图片|登录|注册|广告|专题)$/u', $title);
	}

	protected function filter_unseen_candidates(array $candidates): array
	{
		$hashes = array_values(array_unique(array_map(static fn (array $candidate): string => (string) $candidate['url_hash'], $candidates)));
		if (!$hashes)
		{
			return [];
		}

		$seen = [];
		foreach (array_chunk($hashes, 200) as $chunk)
		{
			$sql = 'SELECT url_hash
				FROM ' . $this->seen_table . '
				WHERE ' . $this->db->sql_in_set('url_hash', array_map([$this->db, 'sql_escape'], $chunk));
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$seen[(string) $row['url_hash']] = true;
			}
			$this->db->sql_freeresult($result);
		}

		return array_values(array_filter($candidates, static fn (array $candidate): bool => !isset($seen[(string) $candidate['url_hash']])));
	}

	protected function record_candidates(array $candidates): array
	{
		$now = time();
		$claimed = [];
		foreach ($candidates as $candidate)
		{
			$sql_ary = [
				'url_hash' => (string) $candidate['url_hash'],
				'source_key' => (string) $candidate['source_key'],
				'status' => 'candidate',
				'score' => 0,
				'first_seen' => $now,
				'updated_time' => $now,
				'topic_id' => 0,
				'url' => $this->truncate((string) $candidate['url'], 1020),
				'title' => $this->truncate((string) $candidate['title'], 250),
				'reason' => '',
			];
			$this->db->sql_return_on_error(true);
			try
			{
				$inserted = $this->db->sql_query('INSERT INTO ' . $this->seen_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
				if ($inserted !== false)
				{
					$claimed[] = $candidate;
				}
			}
			finally
			{
				$this->db->sql_return_on_error(false);
			}
		}

		return $claimed;
	}

	protected function delete_seen_candidates(array $candidates): void
	{
		$hashes = array_values(array_unique(array_filter(array_map(static fn (array $candidate): string => (string) ($candidate['url_hash'] ?? ''), $candidates))));
		if (!$hashes)
		{
			return;
		}

		foreach (array_chunk($hashes, 200) as $chunk)
		{
			$this->db->sql_query('DELETE FROM ' . $this->seen_table . '
				WHERE ' . $this->db->sql_in_set('url_hash', $chunk));
		}
	}

	protected function select_interesting_candidates(array $candidates): array
	{
		$recent_digest_titles = $this->recent_digest_titles_for_selection();
		$recent_posted_titles = $this->recent_posted_titles_for_dedupe($recent_digest_titles);
		$recent_topic_titles = $this->recent_topic_titles_for_selection();
		$recent_junban_titles = $this->recent_junban_topic_titles_for_selection();
		$selection_limit = $this->selection_limit(count($candidates));
		$payload_candidates = [];
		foreach ($candidates as $index => $candidate)
		{
			$candidates[$index]['id'] = $index + 1;
			$payload_candidates[] = [
				'id' => $index + 1,
				'source' => (string) $candidate['source_label'],
				'title' => (string) $candidate['title'],
				'published_at' => !empty($candidate['published_time']) ? gmdate('c', (int) $candidate['published_time']) : '',
			];
		}

		$payload = [
			'model' => $this->api_model(),
			'temperature' => 0.1,
			'response_format' => ['type' => 'json_object'],
			'messages' => [
				[
					'role' => 'system',
					'content' => '你是 mitbbs（买买提）的新闻编辑。本站用户多有欧美留学背景，学历至少硕士以上，大多事业有成。请只根据标题和来源挑选可能引发本站用户兴趣的新闻。偏好：中美关系、美国政治社会、华人相关、科技/AI、中国科技、中国军事、中国社会新闻、战争与国际局势、经济金融、重大公共事件。候选中若有非重复、质量尚可的中文来源新闻或中国相关新闻，优先纳入，目标每轮至少 1 到 2 条；除非明显低质、重复或标题党。娱乐八卦和重大体育赛况不必一律过滤，若可能引发讨论可入选。过滤：软文、地方小新闻、重复/标题党。不要选择与 recent_topics 或 junban_recent_topics 已有话题语义重复的候选；同一事件的不同来源或不同措辞也算重复。候选之间若是同一事件，只选一个。按优先级返回最多 max_selected 条。只能返回 JSON，不要 Markdown。格式：{"selected":[{"id":数字,"score":0到100,"reason":"简短中文理由"}]}。',
				],
				[
					'role' => 'user',
					'content' => $this->encode_json([
						'max_selected' => $selection_limit,
						'min_score' => $this->min_interest_score(),
						'recent_topics' => $recent_topic_titles,
						'junban_recent_topics' => $recent_junban_titles,
						'candidates' => $payload_candidates,
					]),
				],
			],
		];

		$response = $this->api_request($payload);
		$content = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
		$decoded = json_decode($this->extract_json_object($content), true);
		if (!is_array($decoded) || !isset($decoded['selected']) || !is_array($decoded['selected']))
		{
			throw new \RuntimeException('Title filter response was not valid JSON.');
		}

		$by_id = [];
		foreach ($candidates as $candidate)
		{
			$by_id[(int) $candidate['id']] = $candidate;
		}

		$selected = [];
		foreach ($decoded['selected'] as $item)
		{
			if (!is_array($item))
			{
				continue;
			}
			$id = (int) ($item['id'] ?? 0);
			if (!isset($by_id[$id]))
			{
				continue;
			}
			$score = $this->normalize_score($item['score'] ?? 0);
			if ($score < $this->min_interest_score())
			{
				continue;
			}
			$candidate = $by_id[$id];
			$candidate['score'] = $score;
			$candidate['reason'] = $this->truncate($this->normalize_text((string) ($item['reason'] ?? '')), 250);
			$selected[] = $candidate;
		}

		usort($selected, static fn (array $a, array $b): int => ((int) ($b['score'] ?? 0)) <=> ((int) ($a['score'] ?? 0)));
		$selected = $this->filter_duplicate_selected_candidates($selected, array_merge($recent_posted_titles, $recent_topic_titles, $recent_junban_titles));

		return array_slice($selected, 0, $selection_limit);
	}

	protected function selection_limit(int $candidate_count): int
	{
		$post_target = $this->max_selected_per_run();
		$backup_limit = min(50, max($post_target, $post_target * 2));

		return max(1, min($candidate_count, $backup_limit));
	}

	protected function recent_digest_titles_for_selection(): array
	{
		$forum_id = $this->digest_forum_id();
		if ($forum_id <= 0)
		{
			return [];
		}

		$sql = 'SELECT topic_title
			FROM ' . TOPICS_TABLE . '
			WHERE forum_id = ' . $forum_id . '
				AND topic_visibility = ' . ITEM_APPROVED . '
				AND topic_moved_id = 0
			ORDER BY topic_time DESC, topic_id DESC';

		return $this->fetch_topic_titles_from_sql($sql, self::DIGEST_CONTEXT_LIMIT);
	}

	protected function recent_posted_titles_for_dedupe(?array $digest_titles = null): array
	{
		$titles = $digest_titles ?? $this->recent_digest_titles_for_selection();

		$sql = "SELECT title
			FROM {$this->seen_table}
			WHERE status = 'posted'
				AND topic_id > 0
			ORDER BY updated_time DESC";

		return $this->unique_title_context(array_merge($titles, $this->fetch_seen_titles_from_sql($sql, self::DIGEST_CONTEXT_LIMIT)));
	}

	protected function recent_topic_titles_for_selection(): array
	{
		$topic_ids = $this->recenttopicsng_topic_ids_for_selection(self::RTNG_DEFAULT_TPL_LOOP, self::RTNG_RECENT_CONTEXT_LIMIT);
		if (!$topic_ids)
		{
			return [];
		}

		$sql = 'SELECT topic_title
			FROM ' . TOPICS_TABLE . '
			WHERE ' . $this->db->sql_in_set('topic_id', $topic_ids) . '
			ORDER BY topic_last_post_time DESC, topic_id DESC';

		return $this->fetch_topic_titles_from_sql($sql, self::RTNG_RECENT_CONTEXT_LIMIT);
	}

	protected function recent_junban_topic_titles_for_selection(): array
	{
		$topic_ids = $this->recenttopicsng_topic_ids_for_selection(self::RTNG_JUNBAN_TPL_LOOP, self::RTNG_JUNBAN_CONTEXT_LIMIT);
		if (!$topic_ids)
		{
			return [];
		}

		$sql = 'SELECT topic_title
			FROM ' . TOPICS_TABLE . '
			WHERE ' . $this->db->sql_in_set('topic_id', $topic_ids) . '
			ORDER BY topic_last_post_time DESC, topic_id DESC';

		return $this->fetch_topic_titles_from_sql($sql, self::RTNG_JUNBAN_CONTEXT_LIMIT);
	}

	protected function recenttopicsng_topic_ids_for_selection(string $tpl_loopname, int $limit): array
	{
		$functions = $this->recenttopicsng_functions();
		if (!$functions)
		{
			return [];
		}

		try
		{
			if (method_exists($functions, 'get_displayed_index_topic_ids_for_dedupe')
				&& (!method_exists($functions, 'has_displayed_index_topic_ids_for_dedupe')
					|| $functions->has_displayed_index_topic_ids_for_dedupe($tpl_loopname)))
			{
				$topic_ids = $functions->get_displayed_index_topic_ids_for_dedupe($tpl_loopname);
			}
			else if (method_exists($functions, 'get_index_topic_ids_for_dedupe'))
			{
				$topic_ids = $functions->get_index_topic_ids_for_dedupe($tpl_loopname);
			}
			else
			{
				return [];
			}
		}
		catch (\Throwable $e)
		{
			return [];
		}

		if (!is_array($topic_ids))
		{
			return [];
		}

		$topic_ids = array_values(array_unique(array_filter(array_map('intval', $topic_ids), static function (int $topic_id): bool {
			return $topic_id > 0;
		})));

		return array_slice($topic_ids, 0, max(0, $limit));
	}

	protected function fetch_topic_titles_from_sql(string $sql, int $limit): array
	{
		if ($limit <= 0)
		{
			return [];
		}

		$titles = [];
		$result = $this->db->sql_query_limit($sql, $limit);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$title = (string) ($row['topic_title'] ?? '');
			$title = function_exists('censor_text') ? censor_text($title) : $title;
			$title = $this->normalize_text($title);
			if ($title !== '')
			{
				$titles[] = $this->truncate($title, 120, '');
			}
		}
		$this->db->sql_freeresult($result);

		return array_values(array_unique($titles));
	}

	protected function fetch_seen_titles_from_sql(string $sql, int $limit): array
	{
		if ($limit <= 0)
		{
			return [];
		}

		$titles = [];
		$result = $this->db->sql_query_limit($sql, $limit);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$title = $this->normalize_text((string) ($row['title'] ?? ''));
			if ($title !== '')
			{
				$titles[] = $this->truncate($title, 120, '');
			}
		}
		$this->db->sql_freeresult($result);

		return $this->unique_title_context($titles);
	}

	protected function unique_title_context(array $titles): array
	{
		$unique = [];
		foreach ($titles as $title)
		{
			$title = $this->normalize_text((string) $title);
			if ($title !== '')
			{
				$unique[$title] = $title;
			}
		}

		return array_values($unique);
	}

	protected function recenttopicsng_functions()
	{
		if ($this->recenttopicsng_functions !== false)
		{
			return $this->recenttopicsng_functions;
		}

		global $phpbb_container;
		$this->recenttopicsng_functions = null;
		if (empty($phpbb_container)
			|| !method_exists($phpbb_container, 'has')
			|| !$phpbb_container->has('imcger.recenttopicsng.functions'))
		{
			return null;
		}

		try
		{
			$this->recenttopicsng_functions = $phpbb_container->get('imcger.recenttopicsng.functions');
		}
		catch (\Throwable $e)
		{
			$this->recenttopicsng_functions = null;
		}

		return $this->recenttopicsng_functions;
	}

	protected function filter_duplicate_selected_candidates(array $selected, array $context_titles): array
	{
		$filtered = [];
		$seen_titles = array_values(array_filter(array_map([$this, 'normalize_text'], $context_titles), static function (string $title): bool {
			return $title !== '';
		}));

		foreach ($selected as $candidate)
		{
			$title = $this->normalize_text((string) ($candidate['title'] ?? ''));
			if ($title === '')
			{
				continue;
			}

			$is_duplicate = false;
			foreach ($seen_titles as $seen_title)
			{
				if ($this->titles_are_near_duplicates($title, $seen_title))
				{
					$is_duplicate = true;
					break;
				}
			}

			if ($is_duplicate)
			{
				continue;
			}

			$filtered[] = $candidate;
			$seen_titles[] = $title;
		}

		return $filtered;
	}

	protected function title_matches_any(string $title, array $context_titles): bool
	{
		$title = $this->normalize_text($title);
		if ($title === '')
		{
			return false;
		}

		foreach ($context_titles as $context_title)
		{
			if ($this->titles_are_near_duplicates($title, (string) $context_title))
			{
				return true;
			}
		}

		return false;
	}

	protected function titles_are_near_duplicates(string $left, string $right): bool
	{
		$left = $this->normalize_title_for_similarity($left);
		$right = $this->normalize_title_for_similarity($right);
		if ($left === '' || $right === '')
		{
			return false;
		}

		if ($left === $right)
		{
			return true;
		}

		$min_length = min($this->strlen($left), $this->strlen($right));
		if ($min_length >= 8 && (strpos($left, $right) !== false || strpos($right, $left) !== false))
		{
			return true;
		}

		$word_score = $this->word_similarity_score($left, $right);
		if ($word_score >= 0.7)
		{
			return true;
		}

		$common_bigrams = count(array_intersect_key($this->cjk_bigrams($left), $this->cjk_bigrams($right)));
		$char_score = $this->character_similarity_score($left, $right);

		if ($common_bigrams >= 4 && $char_score >= 0.5)
		{
			return true;
		}

		return $this->titles_share_event_signature($left, $right, $common_bigrams, $char_score);
	}

	protected function normalize_title_for_similarity(string $title): string
	{
		$title = $this->normalize_text($title);
		$title = preg_replace('#https?://\S+#iu', ' ', $title) ?? $title;
		$title = preg_replace('/[\p{P}\p{S}\s]+/u', ' ', $title) ?? $title;
		$title = preg_replace('/\b(?:breaking|update|video|photos?)\b/iu', ' ', $title) ?? $title;
		$title = preg_replace('/(?:快讯|视频|组图|图集|更新|最新消息)/u', ' ', $title) ?? $title;
		$title = preg_replace('/\s+/u', ' ', $title) ?? $title;
		$title = trim($title);

		return function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
	}

	protected function word_similarity_score(string $left, string $right): float
	{
		$left_words = $this->word_set($left);
		$right_words = $this->word_set($right);
		if (!$left_words || !$right_words)
		{
			return 0.0;
		}

		$intersection = count(array_intersect_key($left_words, $right_words));

		return (2.0 * $intersection) / (count($left_words) + count($right_words));
	}

	protected function word_set(string $text): array
	{
		if (!preg_match_all('/[a-z0-9]{3,}/iu', $text, $matches))
		{
			return [];
		}

		return array_fill_keys(array_map(static function (string $word): string {
			return function_exists('mb_strtolower') ? mb_strtolower($word, 'UTF-8') : strtolower($word);
		}, $matches[0]), true);
	}

	protected function cjk_bigrams(string $text): array
	{
		return $this->cjk_ngrams($text, 2);
	}

	protected function cjk_trigrams(string $text): array
	{
		return $this->cjk_ngrams($text, 3);
	}

	protected function cjk_ngrams(string $text, int $size): array
	{
		$chars = $this->cjk_chars($text);
		if ($size <= 0 || count($chars) < $size)
		{
			return [];
		}

		$ngrams = [];
		for ($i = 0, $max = count($chars) - $size; $i <= $max; $i++)
		{
			$ngrams[implode('', array_slice($chars, $i, $size))] = true;
		}

		return $ngrams;
	}

	protected function titles_share_event_signature(string $left, string $right, int $common_bigrams, float $char_score): bool
	{
		if ($common_bigrams < 5 || $char_score < 0.4)
		{
			return false;
		}

		$common_trigrams = count(array_intersect_key($this->cjk_trigrams($left), $this->cjk_trigrams($right)));
		$shared_event_terms = $this->shared_event_term_count($left, $right);

		return ($common_trigrams >= 3 && $shared_event_terms >= 1)
			|| ($common_bigrams >= 6 && $shared_event_terms >= 2 && $char_score >= 0.45);
	}

	protected function shared_event_term_count(string $left, string $right): int
	{
		$terms = [
			'取消',
			'打击',
			'军事',
			'空袭',
			'袭击',
			'开火',
			'击中',
			'攻击',
			'轰炸',
			'爆炸',
			'失踪',
			'死亡',
			'遇难',
			'受伤',
			'召见',
			'抗议',
			'制裁',
			'关税',
			'谈判',
			'批准',
			'降价',
			'价格战',
			'竞争',
			'起诉',
			'判决',
			'逮捕',
			'获释',
			'辞职',
			'罢免',
			'裁员',
			'收购',
			'出售',
			'发射',
			'坠毁',
			'禁令',
			'封锁',
			'泄露',
		];

		$count = 0;
		foreach ($terms as $term)
		{
			if (strpos($left, $term) !== false && strpos($right, $term) !== false)
			{
				$count++;
			}
		}

		return $count;
	}

	protected function character_similarity_score(string $left, string $right): float
	{
		$left_chars = array_fill_keys($this->cjk_chars($left), true);
		$right_chars = array_fill_keys($this->cjk_chars($right), true);
		if (!$left_chars || !$right_chars)
		{
			return 0.0;
		}

		$intersection = count(array_intersect_key($left_chars, $right_chars));

		return (2.0 * $intersection) / (count($left_chars) + count($right_chars));
	}

	protected function cjk_chars(string $text): array
	{
		if (!preg_match_all('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $text, $matches))
		{
			return [];
		}

		return $matches[0];
	}

	protected function fetch_article_text(array $candidate): string
	{
		$sources = $this->source_configs();
		$source = $sources[(string) $candidate['source_key']] ?? [];
		$html = $this->fetch_url((string) $candidate['url']);

		return $this->extract_article_text($html, $source);
	}

	protected function extract_article_text(string $html, array $source): string
	{
		$text = $this->extract_json_ld_article_body($html);
		if ($this->strlen($text) >= self::MIN_ARTICLE_CHARS)
		{
			return $text;
		}

		$dom = $this->load_html($html);
		if (!$dom)
		{
			return '';
		}

		$xpath = new \DOMXPath($dom);
		$queries = $this->content_xpath_queries($source);
		$queries[] = '//*[@id="articleContent" or @id="article_content" or @id="artibody" or @id="mp-editor" or @id="detailContent" or contains(@class, "article-content") or contains(@class, "article_body") or contains(@class, "article-body") or contains(@class, "articleContent") or contains(@class, "text-content")]//p';
		$queries[] = '//article//p';
		$queries[] = '//main//p';
		$queries[] = '//p';

		foreach ($queries as $query)
		{
			$paragraphs = [];
			foreach ($xpath->query($query) ?: [] as $node)
			{
				$text = $this->normalize_text($node->textContent);
				if ($this->strlen($text) < 25 || $this->is_boilerplate_paragraph($text))
				{
					continue;
				}
				$paragraphs[] = $text;
			}
			$text = $this->truncate_article(implode("\n\n", $paragraphs));
			if ($this->strlen($text) >= self::MIN_ARTICLE_CHARS)
			{
				return $text;
			}
		}

		foreach ($this->article_container_xpath_queries() as $query)
		{
			$parts = [];
			foreach ($xpath->query($query) ?: [] as $node)
			{
				$text = $this->normalize_text($node->textContent);
				if ($this->strlen($text) < self::MIN_ARTICLE_CHARS
					|| ($this->strlen($text) < 500 && $this->is_boilerplate_paragraph($text)))
				{
					continue;
				}
				$parts[] = $text;
			}
			$text = $this->truncate_article(implode("\n\n", $parts));
			if ($this->strlen($text) >= self::MIN_ARTICLE_CHARS)
			{
				return $text;
			}
		}

		return '';
	}

	protected function extract_json_ld_article_body(string $html): string
	{
		$parts = [];
		if (preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#isu', $html, $matches))
		{
			foreach ($matches[1] as $json)
			{
				$decoded = json_decode(trim(html_entity_decode($json, ENT_QUOTES | ENT_HTML5, 'UTF-8')), true);
				$this->collect_article_body_values($decoded, $parts);
			}
		}

		if (!$parts && preg_match_all('/"articleBody"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/iu', $html, $matches))
		{
			foreach ($matches[1] as $match)
			{
				$decoded = json_decode('"' . $match . '"');
				if (is_string($decoded))
				{
					$parts[] = $this->normalize_text($decoded);
				}
			}
		}

		return $this->truncate_article(implode("\n\n", array_filter($parts)));
	}

	protected function collect_article_body_values($value, array &$parts): void
	{
		if (!is_array($value))
		{
			return;
		}

		if (isset($value['articleBody']) && is_string($value['articleBody']))
		{
			$parts[] = $this->normalize_text($value['articleBody']);
		}

		foreach ($value as $item)
		{
			if (is_array($item))
			{
				$this->collect_article_body_values($item, $parts);
			}
		}
	}

	protected function content_xpath_queries(array $source): array
	{
		$queries = $source['content_xpath'] ?? [];
		if (!is_array($queries))
		{
			$queries = [(string) $queries];
		}

		return array_values(array_filter(array_map('strval', $queries)));
	}

	protected function article_container_xpath_queries(): array
	{
		return [
			'//*[@id="articleContent" or @id="article_content" or @id="artibody" or @id="mp-editor" or @id="detailContent"]',
			'//*[contains(@class, "article-content") or contains(@class, "article_body") or contains(@class, "article-body") or contains(@class, "articleContent") or contains(@class, "text-content")]',
			'//article',
			'//main',
		];
	}

	protected function generate_digest(array $candidate, string $article_text): array
	{
		$title_max_chars = $this->title_max_chars();
		$payload = [
			'model' => $this->api_model(),
			'temperature' => 0.2,
			'response_format' => ['type' => 'json_object'],
			'messages' => [
				[
					'role' => 'system',
					'content' => '你是新闻摘要编辑。根据原文生成中文摘要，要求准确、中性、适合论坛讨论。只能返回 JSON，不要 Markdown。格式：{"title":"中文短标题","content":"中文摘要正文"}。title 必须不超过给定字数，content 控制在 300 到 700 个中文字符，不要编造原文没有的信息。',
				],
				[
					'role' => 'user',
					'content' => $this->encode_json([
						'title_max_chars' => $title_max_chars,
						'source' => (string) $candidate['source_label'],
						'original_title' => (string) $candidate['title'],
						'url' => (string) $candidate['url'],
						'article_text' => $article_text,
					]),
				],
			],
		];

		$response = $this->api_request($payload);
		$content = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
		$decoded = json_decode($this->extract_json_object($content), true);
		if (!is_array($decoded))
		{
			throw new \RuntimeException('Digest response was not valid JSON.');
		}

		$title = $this->truncate($this->normalize_text((string) ($decoded['title'] ?? $candidate['title'])), $title_max_chars, '');
		$body = $this->truncate($this->normalize_text((string) ($decoded['content'] ?? '')), self::MAX_DIGEST_CHARS, '');
		if ($title === '' || $body === '')
		{
			throw new \RuntimeException('Digest response was empty.');
		}

		return [
			'title' => $title,
			'content' => $body,
		];
	}

	protected function post_digest(int $forum_id, array $candidate, array $digest): int
	{
		if (!function_exists('generate_text_for_storage'))
		{
			include_once($this->phpbb_root_path . 'includes/functions_content.' . $this->php_ext);
		}
		if (!function_exists('submit_post'))
		{
			include_once($this->phpbb_root_path . 'includes/functions_posting.' . $this->php_ext);
		}

		$restore_user_context = $this->apply_cron_user_context_defaults();
		try
		{
			$subject = $this->truncate((string) $digest['title'], $this->title_max_chars(), '');
			$message = $this->build_digest_message($candidate, $digest);
			$uid = $bitfield = $flags = '';
			generate_text_for_storage($message, $uid, $bitfield, $flags, true, true, false);

			$data = [
				'forum_id' => $forum_id,
				'topic_id' => 0,
				'icon_id' => 0,
				'topic_title' => $subject,
				'topic_time_limit' => 0,
				'enable_bbcode' => true,
				'enable_smilies' => false,
				'enable_urls' => true,
				'enable_sig' => false,
				'enable_markdown' => false,
				'enable_indexing' => true,
				'message' => $message,
				'message_md5' => md5($message),
				'bbcode_bitfield' => $bitfield,
				'bbcode_uid' => $uid,
				'post_edit_locked' => 0,
				'notify_set' => false,
				'notify' => false,
				'post_time' => time(),
				'force_visibility' => ITEM_APPROVED,
				'force_approved_state' => ITEM_APPROVED,
				'attachment_data' => [],
				'filename_data' => [],
			];
			$poll = [];

			submit_post('post', $subject, self::GUEST_POSTER_NAME, POST_NORMAL, $poll, $data, true, true);

			return (int) ($data['topic_id'] ?? 0);
		}
		finally
		{
			$restore_user_context();
		}
	}

	protected function apply_cron_user_context_defaults(): callable
	{
		global $user;

		if (!$user instanceof \phpbb\user)
		{
			return static function (): void {
			};
		}

		$data_defaults = [
			'user_id' => ANONYMOUS,
			'is_registered' => false,
			'username' => '',
			'user_colour' => '',
			'user_options' => 230271,
		];
		$previous_data = [];
		foreach ($data_defaults as $key => $default)
		{
			if (!array_key_exists($key, $user->data) || $user->data[$key] === null)
			{
				$previous_data[$key] = [
					'exists' => array_key_exists($key, $user->data),
					'value' => $user->data[$key] ?? null,
				];
				$user->data[$key] = $default;
			}
		}

		$page_defaults = [
			'page_name' => '',
			'page_dir' => '',
			'query_string' => '',
		];
		$previous_page = [];
		foreach ($page_defaults as $key => $default)
		{
			if (!array_key_exists($key, $user->page) || $user->page[$key] === null)
			{
				$previous_page[$key] = [
					'exists' => array_key_exists($key, $user->page),
					'value' => $user->page[$key] ?? null,
				];
				$user->page[$key] = $default;
			}
		}

		return static function () use ($user, $previous_data, $previous_page): void {
			foreach ($previous_data as $key => $previous)
			{
				if ($previous['exists'])
				{
					$user->data[$key] = $previous['value'];
				}
				else
				{
					unset($user->data[$key]);
				}
			}
			foreach ($previous_page as $key => $previous)
			{
				if ($previous['exists'])
				{
					$user->page[$key] = $previous['value'];
				}
				else
				{
					unset($user->page[$key]);
				}
			}
		};
	}

	protected function build_digest_message(array $candidate, array $digest): string
	{
		$source = $this->escape_bbcode_text((string) $candidate['source_label']);
		$original_title = $this->escape_bbcode_text((string) $candidate['title']);
		$url = $this->escape_url((string) $candidate['url']);
		$content = $this->escape_bbcode_text((string) $digest['content']);

		return '[b]来源：[/b] ' . $source . "\n"
			. '[b]原标题：[/b] [url=' . $url . ']' . $original_title . "[/url]\n\n"
			. $content . "\n\n"
			. '[url=' . $url . ']阅读原文[/url]';
	}

	protected function update_seen(array $candidate, string $status, int $score = 0, string $reason = '', int $topic_id = 0): void
	{
		$sql_ary = [
			'status' => $status,
			'score' => max(0, min(100, $score)),
			'updated_time' => time(),
			'topic_id' => $topic_id,
			'reason' => $this->truncate($reason, 250),
		];
		$this->db->sql_query('UPDATE ' . $this->seen_table . '
			SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . "
			WHERE url_hash = '" . $this->db->sql_escape((string) $candidate['url_hash']) . "'");
	}

	protected function purge_old_seen_rows(): void
	{
		$cutoff = time() - ($this->seen_retention_days() * 86400);
		$this->db->sql_query('DELETE FROM ' . $this->seen_table . "
			WHERE status <> 'posted'
				AND updated_time < " . (int) $cutoff);
	}

	protected function fetch_url(string $url): string
	{
		if ($url === '')
		{
			throw new \RuntimeException('Empty URL.');
		}

		$headers = [
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'User-Agent: Mozilla/5.0 (compatible; FreeMITBBS-NewsDigest/1.0)',
		];

		if (function_exists('curl_init'))
		{
			$data = '';
			$too_large = false;
			$curl = curl_init($url);
			curl_setopt_array($curl, [
				CURLOPT_HTTPGET => true,
				CURLOPT_HTTPHEADER => $headers,
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS => 3,
				CURLOPT_CONNECTTIMEOUT => self::HTTP_CONNECT_TIMEOUT_SECONDS,
				CURLOPT_TIMEOUT => self::HTTP_TIMEOUT_SECONDS,
				CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$data, &$too_large): int {
					$data .= $chunk;
					if (strlen($data) > self::MAX_SOURCE_BYTES)
					{
						$too_large = true;
						return 0;
					}

					return strlen($chunk);
				},
			]);
			if (defined('CURLOPT_PROTOCOLS'))
			{
				curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
			}
			$response = curl_exec($curl);
			$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
			$error = curl_error($curl);
			curl_close($curl);

			if ($response === false && !$too_large)
			{
				throw new \RuntimeException('Fetch failed: ' . $error);
			}
			if ($status < 200 || $status >= 400)
			{
				throw new \RuntimeException('Fetch returned HTTP ' . $status);
			}

			return $data;
		}

		$options = [
			'http' => [
				'method' => 'GET',
				'header' => implode("\r\n", $headers),
				'timeout' => self::HTTP_TIMEOUT_SECONDS,
				'ignore_errors' => true,
			],
		];
		$data = file_get_contents($url, false, stream_context_create($options));
		if ($data === false)
		{
			throw new \RuntimeException('Fetch failed.');
		}

		return substr((string) $data, 0, self::MAX_SOURCE_BYTES);
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
				throw new \RuntimeException('AI API request failed: ' . $error);
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
			throw new \RuntimeException('AI API request failed.');
		}

		return $this->decode_api_response((string) $response_body, $status);
	}

	protected function decode_api_response(string $response_body, int $status): array
	{
		if ($status < 200 || $status >= 300)
		{
			throw new \RuntimeException('AI API returned HTTP ' . $status . ': ' . $this->truncate($response_body, 500));
		}

		$data = json_decode($response_body, true);
		if (!is_array($data))
		{
			throw new \RuntimeException('AI API returned invalid JSON.');
		}

		return $data;
	}

	protected function source_configs(): array
	{
		return [
			'guardian' => [
				'label' => 'The Guardian',
				'type' => 'feed',
				'url' => 'https://www.theguardian.com/world/rss',
			],
			'bbc' => [
				'label' => 'BBC',
				'type' => 'feed',
				'url' => 'https://feeds.bbci.co.uk/news/world/rss.xml',
			],
			'dw' => [
				'label' => 'DW',
				'type' => 'feed',
				'url' => 'https://rss.dw.com/xml/rss-en-world',
			],
			'cnbc' => [
				'label' => 'CNBC',
				'type' => 'feed',
				'url' => 'https://www.cnbc.com/id/100003114/device/rss/rss.html',
			],
			'dailymail' => [
				'label' => 'Daily Mail',
				'type' => 'feed',
				'url' => 'https://www.dailymail.co.uk/news/index.rss',
			],
			'ars' => [
				'label' => 'Ars Technica',
				'type' => 'feed',
				'url' => 'https://feeds.arstechnica.com/arstechnica/index',
			],
			'zerohedge' => [
				'label' => 'ZeroHedge',
				'type' => 'feed',
				'url' => 'https://feeds.feedburner.com/zerohedge/feed',
			],
			'foxnews' => [
				'label' => 'Fox News',
				'type' => 'feed',
				'url' => 'https://moxie.foxnews.com/google-publisher/latest.xml',
			],
			'wenxuecity' => [
				'label' => 'Wenxuecity',
				'type' => 'listing',
				'url' => 'https://www.wenxuecity.com/news/',
				'article_url_regex' => '#^https?://(?:www\.)?wenxuecity\.com/news/\d{4}/\d{2}/\d{2}/#i',
				'content_xpath' => [
					'//*[@id="articleContent"]//p',
					'//*[@id="article_content"]//p',
				],
			],
			'zaobao' => [
				'label' => 'Zaobao',
				'type' => 'listing',
				'url' => 'https://www.zaobao.com.sg/realtime/world',
				'article_url_regex' => '#^https?://(?:www\.)?zaobao\.com\.sg/#i',
				'content_xpath' => [
					'//*[contains(@class, "article-content") or contains(@class, "field--name-body") or contains(@class, "article-body")]//p',
				],
			],
			'sina_world' => [
				'label' => 'Sina World',
				'type' => 'listing',
				'url' => 'https://news.sina.com.cn/world/',
				'article_url_regex' => '#^https?://(?:[a-z0-9-]+\.)?news\.sina\.com\.cn/.+\.shtml#i',
				'content_xpath' => [
					'//*[@id="article_content"]//p',
					'//*[@id="artibody"]//p',
				],
			],
			'sohu' => [
				'label' => 'Sohu',
				'type' => 'listing',
				'url' => 'https://news.sohu.com/',
				'article_url_regex' => '#^https?://www\.sohu\.com/a/\d+_#i',
				'content_xpath' => [
					'//*[@id="mp-editor"]//p',
					'//*[contains(@class, "article") or contains(@class, "text-content")]//p',
				],
			],
			'xinhua_world' => [
				'label' => 'Xinhua World',
				'type' => 'listing',
				'url' => 'https://www.news.cn/world/index.htm',
				'article_url_regex' => '#^https?://www\.news\.cn/world/.+\.htm#i',
				'content_xpath' => [
					'//*[@id="detailContent"]//p',
					'//*[contains(@class, "detailContent") or contains(@class, "article-content")]//p',
				],
			],
		];
	}

	protected function digest_forum_id(): int
	{
		$configured = (int) ($this->config['newsscraper_digest_forum_id'] ?? 0);
		if ($configured > 0)
		{
			return $configured;
		}

		$sql = 'SELECT forum_id
			FROM ' . FORUMS_TABLE . "
			WHERE forum_name = '" . $this->db->sql_escape(self::DEFAULT_DIGEST_FORUM_NAME) . "'
				AND forum_type = " . FORUM_POST;
		$result = $this->db->sql_query_limit($sql, 1);
		$forum_id = (int) $this->db->sql_fetchfield('forum_id');
		$this->db->sql_freeresult($result);

		return $forum_id;
	}

	protected function enabled_sources(): array
	{
		$raw = trim((string) ($this->config['newsscraper_enabled_sources'] ?? ''));
		$sources = [];
		foreach (preg_split('/[,\s]+/', $raw) ?: [] as $source)
		{
			$source = trim((string) $source);
			if ($source !== '')
			{
				$sources[] = $source;
			}
		}

		return array_values(array_unique($sources));
	}

	protected function api_endpoint(): string
	{
		$endpoint = trim((string) ($this->config['newsscraper_api_endpoint'] ?? ''));
		if ($endpoint !== '')
		{
			return $endpoint;
		}
		$endpoint = trim((string) ($this->config['topicmover_api_endpoint'] ?? ''));

		return $endpoint !== '' ? $endpoint : self::DEFAULT_ENDPOINT;
	}

	protected function api_model(): string
	{
		$model = trim((string) ($this->config['newsscraper_model'] ?? ''));
		if ($model !== '')
		{
			return $this->normalize_api_model($model);
		}
		$model = trim((string) ($this->config['topicmover_model'] ?? ''));

		return $this->normalize_api_model($model);
	}

	protected function normalize_api_model(string $model): string
	{
		$model = trim($model);

		return $model === '' || in_array($model, self::DEPRECATED_MODELS, true)
			? self::DEFAULT_MODEL
			: $model;
	}

	protected function api_key(): string
	{
		$key = trim((string) ($this->config['newsscraper_api_key'] ?? ''));
		if ($key !== '')
		{
			return $key;
		}

		return trim((string) ($this->config['topicmover_api_key'] ?? ''));
	}

	protected function candidate_limit(): int
	{
		return max(1, min(200, (int) ($this->config['newsscraper_candidates_per_run'] ?? 60)));
	}

	protected function max_selected_per_run(): int
	{
		return max(1, min(50, (int) ($this->config['newsscraper_max_selected_per_run'] ?? 4)));
	}

	protected function min_interest_score(): int
	{
		return max(0, min(100, (int) ($this->config['newsscraper_min_interest_score'] ?? 65)));
	}

	protected function per_source_cap(): int
	{
		return max(1, min(20, (int) ($this->config['newsscraper_per_source_cap'] ?? 2)));
	}

	protected function title_max_chars(): int
	{
		return max(8, min(60, (int) ($this->config['newsscraper_title_max_chars'] ?? 30)));
	}

	protected function seen_retention_days(): int
	{
		return max(1, min(365, (int) ($this->config['newsscraper_seen_retention_days'] ?? 30)));
	}

	protected function normalize_score($score): int
	{
		$value = is_numeric($score) ? (float) $score : 0.0;
		if ($value > 0 && $value <= 1)
		{
			$value *= 100;
		}

		return max(0, min(100, (int) round($value)));
	}

	protected function load_html(string $html): ?\DOMDocument
	{
		if ($html === '')
		{
			return null;
		}

		$dom = new \DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		return $loaded ? $dom : null;
	}

	protected function normalize_text(string $text): string
	{
		$text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace('/\s+/u', ' ', $text) ?? $text;

		return trim($text);
	}

	protected function canonicalize_url(string $url, string $base_url = ''): string
	{
		$url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		if ($url === '' || preg_match('#^(javascript|mailto|tel):#i', $url))
		{
			return '';
		}

		$url = $this->absolute_url($url, $base_url);
		$parts = parse_url($url);
		if ($parts === false || empty($parts['scheme']) || empty($parts['host']))
		{
			return '';
		}

		$scheme = strtolower((string) $parts['scheme']);
		if ($scheme !== 'http' && $scheme !== 'https')
		{
			return '';
		}

		$host = strtolower((string) $parts['host']);
		$path = (string) ($parts['path'] ?? '/');
		$query = [];
		parse_str((string) ($parts['query'] ?? ''), $query);
		foreach (array_keys($query) as $key)
		{
			if (stripos($key, 'utm_') === 0 || in_array($key, ['fbclid', 'gclid', 'mc_cid', 'mc_eid'], true))
			{
				unset($query[$key]);
			}
		}
		$query_string = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

		$port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

		return $scheme . '://' . $host . $port . $path . ($query_string !== '' ? '?' . $query_string : '');
	}

	protected function absolute_url(string $url, string $base_url): string
	{
		if (preg_match('#^https?://#i', $url))
		{
			return $url;
		}
		if (strpos($url, '//') === 0)
		{
			return 'https:' . $url;
		}

		$base = parse_url($base_url);
		if ($base === false || empty($base['scheme']) || empty($base['host']))
		{
			return $url;
		}

		$prefix = strtolower((string) $base['scheme']) . '://' . strtolower((string) $base['host']);
		if (strpos($url, '/') === 0)
		{
			return $prefix . $url;
		}

		$path = (string) ($base['path'] ?? '/');
		$dir = preg_replace('#/[^/]*$#', '/', $path) ?: '/';

		return $prefix . $dir . $url;
	}

	protected function same_host_family(string $left_url, string $right_url): bool
	{
		$left = parse_url($left_url, PHP_URL_HOST);
		$right = parse_url($right_url, PHP_URL_HOST);
		if (!is_string($left) || !is_string($right))
		{
			return false;
		}

		$left = preg_replace('/^(www|m|amp)\./i', '', strtolower($left)) ?? strtolower($left);
		$right = preg_replace('/^(www|m|amp)\./i', '', strtolower($right)) ?? strtolower($right);

		return $left === $right || substr($left, -strlen('.' . $right)) === '.' . $right;
	}

	protected function is_boilerplate_paragraph(string $text): bool
	{
		return (bool) preg_match('/cookies?|subscribe|newsletter|sign up|all rights reserved|广告|扫码|责任编辑|免责声明|版权所有|登录|注册/iu', $text);
	}

	protected function truncate_article(string $text): string
	{
		return $this->truncate($this->normalize_text($text), self::MAX_ARTICLE_CHARS, '');
	}

	protected function truncate(string $text, int $max_chars, string $suffix = '...'): string
	{
		if ($max_chars <= 0)
		{
			return '';
		}
		if ($this->strlen($text) <= $max_chars)
		{
			return $text;
		}
		if (function_exists('mb_substr'))
		{
			return mb_substr($text, 0, max(0, $max_chars - $this->strlen($suffix)), 'UTF-8') . $suffix;
		}

		return substr($text, 0, max(0, $max_chars - strlen($suffix))) . $suffix;
	}

	protected function strlen(string $text): int
	{
		return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
	}

	protected function escape_bbcode_text(string $text): string
	{
		return str_replace(['[', ']'], ['&#91;', '&#93;'], $text);
	}

	protected function escape_url(string $url): string
	{
		return str_replace([']', "\n", "\r"], ['%5D', '', ''], $url);
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
