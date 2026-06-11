<?php

namespace freemitbbs\newsscraper\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	private const DEFAULT_DIGEST_FORUM_NAME = '新闻摘要';
	private const INDEX_DIGEST_COLLAPSE_ID = 'freemitbbs_newsscraper_index_digest';
	private const DISCUSS_HASH_PREFIX = 'freemitbbs_newsscraper_discuss_';
	private const DISCUSS_FORUM_CACHE_KEY = '_freemitbbs_newsscraper_discuss_forum_rows';
	private const DISCUSS_FORUM_CACHE_TTL = 3600;

	protected \phpbb\auth\auth $auth;
	protected \phpbb\cache\service $cache;
	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\language\language $language;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\template\template $template;
	protected $collapsible_operator;
	protected array $digest_discussion_links = [];
	protected array $digest_topic_meta = [];
	protected ?array $discussion_forum_rows = null;
	protected array $discussion_forum_templates = [];
	protected string $seen_table;
	protected string $discussion_table;
	protected string $phpbb_root_path;
	protected string $php_ext;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\cache\service $cache,
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		string $seen_table,
		string $discussion_table,
		string $phpbb_root_path,
		string $php_ext,
		$collapsible_operator = null
	)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->db = $db;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->collapsible_operator = $collapsible_operator;
		$this->seen_table = $seen_table;
		$this->discussion_table = $discussion_table;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language',
			'core.page_header' => 'assign_header_link',
			'core.index_modify_page_title' => 'assign_index_digest',
			'core.viewforum_modify_page_title' => 'force_digest_forum_topic_list_view',
			'core.viewtopic_modify_page_title' => 'assign_discuss_form',
			'core.viewtopic_modify_post_row' => 'customise_digest_post_row',
			'core.viewforum_modify_topics_data' => 'prepare_digest_forum_topic_rows',
			'core.viewforum_modify_topicrow' => ['customise_digest_forum_topic_row', -100],
			'core.posting_modify_post_data' => 'prefill_discussion_post',
			'core.submit_post_end' => 'record_submitted_discussion',
			'freemitbbs.toptopics.modify_topic_list' => 'filter_digest_top_topics',
		];
	}

	public function load_language(): void
	{
		$this->language->add_lang('common', 'freemitbbs/newsscraper');
	}

	public function assign_header_link(): void
	{
		$forum_id = $this->digest_forum_id();
		if ($forum_id <= 0 || !$this->auth->acl_get('f_read', $forum_id))
		{
			return;
		}

		$this->template->assign_vars([
			'S_FREEMITBBS_NEWSSCRAPER_NAV' => true,
			'U_NEWSSCRAPER_DIGEST_FORUM' => append_sid("{$this->phpbb_root_path}viewforum.{$this->php_ext}", 'f=' . $forum_id),
		]);
	}

	public function force_digest_forum_topic_list_view($event): void
	{
		$forum_id = (int) ($event['forum_id'] ?? 0);
		if ($forum_id <= 0 || $forum_id !== $this->digest_forum_id())
		{
			return;
		}

		$this->template->assign_vars([
			'S_NEWSSCRAPER_DIGEST_FORUM' => true,
			'S_TOPTOPICS_ENHANCED_TOPIC_LIST_VIEW' => true,
			'S_TOPTOPICS_CLASSIC_TOPIC_LIST_VIEW' => false,
		]);
	}

	public function assign_index_digest(): void
	{
		$forum_id = $this->digest_forum_id();
		$limit = max(0, min(50, (int) ($this->config['newsscraper_frontpage_count'] ?? 20)));
		if ($forum_id <= 0 || $limit <= 0 || !$this->auth->acl_get('f_read', $forum_id))
		{
			return;
		}

		$sql = 'SELECT topic_id, topic_title, topic_time
			FROM ' . TOPICS_TABLE . '
			WHERE forum_id = ' . $forum_id . '
				AND topic_visibility = ' . ITEM_APPROVED . '
				AND topic_moved_id = 0
			ORDER BY topic_time DESC, topic_id DESC';
		$result = $this->db->sql_query_limit($sql, $limit);
		$digest_items = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$title = censor_text((string) $row['topic_title']);
			$digest_items[] = [
				'TITLE' => $this->escape($title),
				'FULL_TITLE' => $this->escape($title),
				'U_TOPIC' => append_sid("{$this->phpbb_root_path}viewtopic.{$this->php_ext}", 't=' . (int) $row['topic_id']),
			];
		}
		$this->db->sql_freeresult($result);

		$count = count($digest_items);
		if ($count <= 0)
		{
			return;
		}

		foreach ($digest_items as $index => $item)
		{
			$item['ROW_CLASS'] = ((int) floor($index / 2) % 2 === 0) ? 'bg1' : 'bg2';
			$item['S_ROW_START'] = $index % 2 === 0;
			$item['S_ROW_END'] = $index % 2 === 1 || $index === ($count - 1);
			$this->template->assign_block_vars('newsscraper_digest', $item);
		}

		$collapsible = $this->has_collapsible_categories();
		$hidden = $collapsible ? (bool) $this->collapsible_operator->is_collapsed(self::INDEX_DIGEST_COLLAPSE_ID) : false;

		$this->template->assign_vars([
			'S_NEWSSCRAPER_INDEX_DIGEST' => true,
			'S_NEWSSCRAPER_INDEX_COLLAPSIBLE' => $collapsible,
			'S_NEWSSCRAPER_INDEX_HIDDEN' => $hidden,
			'NEWSSCRAPER_INDEX_TITLE' => $this->language->lang('NEWSSCRAPER_INDEX_TITLE'),
			'U_NEWSSCRAPER_DIGEST_FORUM' => append_sid("{$this->phpbb_root_path}viewforum.{$this->php_ext}", 'f=' . $forum_id),
			'U_NEWSSCRAPER_INDEX_COLLAPSE_URL' => $collapsible ? $this->collapsible_operator->get_collapsible_link(self::INDEX_DIGEST_COLLAPSE_ID) : '',
			'NEWSSCRAPER_INDEX_BLOCK_ID' => self::INDEX_DIGEST_COLLAPSE_ID,
			'NEWSSCRAPER_INDEX_COLLAPSE_HIDDEN_DATA' => $hidden ? '1' : '',
			'NEWSSCRAPER_INDEX_COLLAPSE_TITLE' => $hidden ? $this->language->lang('NEWSSCRAPER_INDEX_COLLAPSE_SHOW') : $this->language->lang('NEWSSCRAPER_INDEX_COLLAPSE_HIDE'),
			'NEWSSCRAPER_INDEX_COLLAPSE_ALT_TITLE' => $hidden ? $this->language->lang('NEWSSCRAPER_INDEX_COLLAPSE_HIDE') : $this->language->lang('NEWSSCRAPER_INDEX_COLLAPSE_SHOW'),
			'NEWSSCRAPER_INDEX_COLLAPSE_ICON' => $hidden ? 'fa-plus-square' : 'fa-minus-square',
		]);
	}

	public function assign_discuss_form($event): void
	{
		$topic_data = $event['topic_data'] ?? [];
		$forum_id = (int) ($event['forum_id'] ?? $topic_data['forum_id'] ?? 0);
		$topic_id = (int) ($topic_data['topic_id'] ?? 0);
		if ($topic_id <= 0 || $forum_id <= 0 || $forum_id !== $this->digest_forum_id())
		{
			return;
		}

		$discussion_links = $this->discussion_links_by_digest_topic_ids([$topic_id], $forum_id);
		if (isset($discussion_links[$topic_id]))
		{
			$this->template->assign_vars([
				'S_NEWSSCRAPER_DIGEST_TOPIC' => true,
				'U_NEWSSCRAPER_DISCUSSION' => $discussion_links[$topic_id]['url'],
				'NEWSSCRAPER_DISCUSSION_TITLE' => $this->escape((string) $discussion_links[$topic_id]['title']),
			]);
			return;
		}

		$hash = generate_link_hash(self::DISCUSS_HASH_PREFIX . $topic_id);
		$forums = $this->discussion_target_forums($forum_id, $topic_id, $hash);
		if (!$forums)
		{
			return;
		}

		foreach ($forums as $forum)
		{
			$this->template->assign_block_vars('newsscraper_discuss_forums', [
				'FORUM_ID' => (int) $forum['forum_id'],
				'FORUM_NAME' => $this->escape((string) $forum['forum_name']),
				'U_DISCUSS' => $forum['u_discuss'],
				'S_IS_CAT' => (bool) $forum['is_cat'],
				'LEVEL' => (int) $forum['level'],
			]);
		}

		$this->template->assign_vars([
			'S_NEWSSCRAPER_DIGEST_TOPIC' => true,
			'NEWSSCRAPER_DISCUSS_TOPIC_ID' => $topic_id,
			'NEWSSCRAPER_DISCUSS_HASH' => $hash,
		]);
	}

	public function customise_digest_post_row($event): void
	{
		$topic_data = $event['topic_data'] ?? [];
		$row = $event['row'] ?? [];
		$forum_id = (int) ($topic_data['forum_id'] ?? $row['forum_id'] ?? 0);
		if ($forum_id <= 0 || $forum_id !== $this->digest_forum_id())
		{
			return;
		}

		$post_row = $event['post_row'];
		foreach (['U_EDIT', 'U_DELETE', 'U_REPORT', 'U_WARN', 'U_INFO', 'U_QUOTE'] as $button_url)
		{
			$post_row[$button_url] = '';
		}

		$post_id = (int) ($row['post_id'] ?? 0);
		$topic_id = (int) ($topic_data['topic_id'] ?? $row['topic_id'] ?? 0);
		$topic_first_post_id = (int) ($topic_data['topic_first_post_id'] ?? 0);
		$is_discussion_slot = $topic_first_post_id <= 0 || $post_id === $topic_first_post_id;
		$post_row['S_NEWSSCRAPER_DIGEST_DISCUSSION'] = $is_discussion_slot;
		if ($is_discussion_slot && $topic_id > 0)
		{
			$discussion_links = $this->discussion_links_by_digest_topic_ids([$topic_id], $forum_id);
			if (isset($discussion_links[$topic_id]))
			{
				$post_row['U_NEWSSCRAPER_DISCUSSION'] = $discussion_links[$topic_id]['url'];
				$post_row['NEWSSCRAPER_DISCUSSION_TITLE'] = $this->escape((string) $discussion_links[$topic_id]['title']);
			}
		}
		$event['post_row'] = $post_row;
	}

	public function prefill_discussion_post($event): void
	{
		$mode = (string) ($event['mode'] ?? '');
		$target_forum_id = (int) ($event['forum_id'] ?? 0);
		$digest_topic_id = $this->request->variable('newsdigest_topic', 0);
		$hash = $this->request->variable('hash', '');
		if ($mode !== 'post'
			|| $target_forum_id <= 0
			|| $digest_topic_id <= 0
			|| !check_link_hash($hash, self::DISCUSS_HASH_PREFIX . $digest_topic_id)
			|| !$this->auth->acl_get('f_list', $target_forum_id)
			|| !$this->auth->acl_get('f_read', $target_forum_id))
		{
			return;
		}

		$digest = $this->digest_topic_first_post($digest_topic_id);
		if (!$digest || (int) $digest['forum_id'] !== $this->digest_forum_id())
		{
			return;
		}

		$title = censor_text((string) $digest['topic_title']);
		$subject = $this->truncate($title, $this->topic_title_max_chars(), '');
		$post_data = $event['post_data'];
		$post_data['post_subject'] = $subject;
		$post_data['topic_title'] = $subject;
		$digest_url = generate_board_url() . "/viewtopic.{$this->php_ext}?t=" . $digest_topic_id;
		$post_data['post_text'] = $this->language->lang('NEWSSCRAPER_ORIGINAL_NEWS_LINK') . "\n"
			. '[url=' . $digest_url . ']' . $this->bbcode_text($title) . "[/url]\n\n";
		$event['post_data'] = $post_data;

		$this->template->assign_vars([
			'S_NEWSSCRAPER_PREFILLED_DISCUSSION' => true,
			'NEWSSCRAPER_DISCUSS_TOPIC_ID' => $digest_topic_id,
			'NEWSSCRAPER_DISCUSS_HASH' => $hash,
		]);
	}

	public function record_submitted_discussion($event): void
	{
		$mode = (string) ($event['mode'] ?? '');
		$data = $event['data'] ?? [];
		$digest_topic_id = $this->request->variable('newsdigest_topic', 0);
		$hash = $this->request->variable('hash', '');
		$discussion_topic_id = (int) ($data['topic_id'] ?? 0);
		$discussion_post_id = (int) ($data['post_id'] ?? 0);
		$forum_id = (int) ($data['forum_id'] ?? 0);
		$post_visibility = (int) ($event['post_visibility'] ?? ITEM_UNAPPROVED);
		$digest_forum_id = $this->digest_forum_id();

		if ($mode !== 'post'
			|| $digest_topic_id <= 0
			|| $discussion_topic_id <= 0
			|| $discussion_post_id <= 0
			|| $forum_id <= 0
			|| $digest_forum_id <= 0
			|| $forum_id === $digest_forum_id
			|| $post_visibility !== ITEM_APPROVED
			|| !check_link_hash($hash, self::DISCUSS_HASH_PREFIX . $digest_topic_id)
			|| !$this->is_discussion_target_forum($forum_id, $digest_forum_id))
		{
			return;
		}

		$digest = $this->digest_topic_first_post($digest_topic_id);
		if (!$digest || (int) $digest['forum_id'] !== $digest_forum_id)
		{
			return;
		}

		$sql_ary = [
			'digest_topic_id' => $digest_topic_id,
			'discussion_topic_id' => $discussion_topic_id,
			'discussion_post_id' => $discussion_post_id,
			'forum_id' => $forum_id,
			'created_time' => time(),
		];

		$this->db->sql_return_on_error(true);
		try
		{
			$this->db->sql_query('INSERT INTO ' . $this->discussion_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
		}
		finally
		{
			$this->db->sql_return_on_error(false);
		}
	}

	public function prepare_digest_forum_topic_rows($event): void
	{
		$forum_id = (int) ($event['forum_id'] ?? 0);
		if ($forum_id <= 0 || $forum_id !== $this->digest_forum_id())
		{
			$this->digest_discussion_links = [];
			$this->digest_topic_meta = [];
			return;
		}

		$topic_ids = [];
		foreach ((array) ($event['topic_list'] ?? []) as $topic_id)
		{
			$topic_id = (int) $topic_id;
			if ($topic_id > 0)
			{
				$topic_ids[] = $topic_id;
			}
		}

		$topic_ids = array_values(array_unique($topic_ids));
		$this->digest_discussion_links = $this->discussion_links_by_digest_topic_ids($topic_ids, $forum_id);
		$this->digest_topic_meta = $this->digest_topic_meta_by_topic_ids($topic_ids);
	}

	public function customise_digest_forum_topic_row($event): void
	{
		$row = $event['row'] ?? [];
		$topic_row = $event['topic_row'];
		$forum_id = (int) ($row['forum_id'] ?? $topic_row['FORUM_ID'] ?? 0);
		$topic_id = (int) ($row['topic_id'] ?? $topic_row['TOPIC_ID'] ?? 0);
		if ($forum_id <= 0 || $topic_id <= 0 || $forum_id !== $this->digest_forum_id())
		{
			return;
		}

		$topic_row['S_NEWSSCRAPER_DIGEST_TOPIC_ROW'] = true;
		$topic_meta = $this->digest_topic_meta[$topic_id] ?? [];
		if (!empty($topic_meta['source_label']))
		{
			$topic_row['S_NEWSSCRAPER_SOURCE'] = true;
			$topic_row['NEWSSCRAPER_SOURCE_LABEL'] = $this->escape((string) $topic_meta['source_label']);
		}
		if (!empty($topic_meta['preview_text']))
		{
			$topic_row['S_TOPTOPICS_INLINE_LAZY_PREVIEW'] = false;
			$topic_row['S_TOPTOPICS_INLINE_SERVER_PREVIEW'] = false;
			$topic_row['S_TOPTOPICS_INLINE_IMAGE_PREVIEW'] = false;
			$topic_row['S_TOPTOPICS_INLINE_EXCERPT_PREVIEW'] = true;
			$topic_row['S_TOPTOPICS_INLINE_RICH_PREVIEW'] = false;
			$topic_row['U_TOPTOPICS_INLINE_PREVIEW'] = '';
			$topic_row['TOPTOPICS_INLINE_PREVIEW_HTML'] = '';
			$topic_row['TOPTOPICS_INLINE_EXCERPT'] = $this->escape(censor_text((string) $topic_meta['preview_text']));
		}
		if (isset($this->digest_discussion_links[$topic_id]))
		{
			$topic_row['U_NEWSSCRAPER_DISCUSSION'] = $this->digest_discussion_links[$topic_id]['url'];
			$topic_row['NEWSSCRAPER_DISCUSSION_TITLE'] = $this->escape((string) $this->digest_discussion_links[$topic_id]['title']);
		}
		else
		{
			$hash = generate_link_hash(self::DISCUSS_HASH_PREFIX . $topic_id);
			$forums = [];
			foreach ($this->discussion_target_forums($forum_id, $topic_id, $hash) as $forum)
			{
				$forums[] = [
					'FORUM_ID' => (int) $forum['forum_id'],
					'FORUM_NAME' => $this->escape((string) $forum['forum_name']),
					'U_DISCUSS' => $forum['u_discuss'],
					'S_IS_CAT' => (bool) $forum['is_cat'],
					'LEVEL' => (int) $forum['level'],
				];
			}

			$topic_row['S_NEWSSCRAPER_CAN_DISCUSS'] = !empty($forums);
			$topic_row['NEWSSCRAPER_DISCUSS_FORUMS'] = $forums;
		}

		$event['topic_row'] = $topic_row;
	}

	public function filter_digest_top_topics($event): void
	{
		$topics = $event['topics'] ?? [];
		if (empty($topics) || !is_array($topics))
		{
			return;
		}

		$event['topics'] = array_values($this->filter_digest_forum_rowset($topics));
	}

	protected function discussion_target_forums(int $digest_forum_id, int $digest_topic_id, string $hash): array
	{
		$forums = [];
		foreach ($this->discussion_target_forum_templates($digest_forum_id) as $forum)
		{
			$forum_id = (int) $forum['forum_id'];
			$is_cat = (bool) $forum['is_cat'];
			$forums[] = [
				'forum_id' => $forum_id,
				'forum_name' => (string) $forum['forum_name'],
				'u_discuss' => $is_cat ? '' : append_sid("{$this->phpbb_root_path}posting.{$this->php_ext}", 'mode=post&amp;f=' . $forum_id . '&amp;newsdigest_topic=' . $digest_topic_id . '&amp;hash=' . $hash),
				'is_cat' => $is_cat,
				'level' => (int) $forum['level'],
			];
		}

		return $forums;
	}

	protected function digest_topic_meta_by_topic_ids(array $digest_topic_ids): array
	{
		$digest_topic_ids = array_values(array_unique(array_filter(array_map('intval', $digest_topic_ids), static function ($topic_id) {
			return $topic_id > 0;
		})));
		if (!$digest_topic_ids)
		{
			return [];
		}

		$sql = 'SELECT s.topic_id, s.source_key, p.post_text
			FROM ' . $this->seen_table . ' s
			LEFT JOIN ' . TOPICS_TABLE . ' t
				ON t.topic_id = s.topic_id
			LEFT JOIN ' . POSTS_TABLE . ' p
				ON p.post_id = t.topic_first_post_id
			WHERE s.status = ' . "'" . $this->db->sql_escape('posted') . "'" . '
				AND ' . $this->db->sql_in_set('s.topic_id', $digest_topic_ids) . '
			ORDER BY s.updated_time DESC';
		$result = $this->db->sql_query($sql);
		$meta = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topic_id = (int) $row['topic_id'];
			if ($topic_id <= 0 || isset($meta[$topic_id]))
			{
				continue;
			}

			$source_key = (string) $row['source_key'];
			$source_label = $this->source_label($source_key);
			if ($source_label === '')
			{
				$source_label = $source_key;
			}

			$meta[$topic_id] = [
				'source_label' => $source_label,
				'preview_text' => $this->digest_preview_text((string) ($row['post_text'] ?? '')),
			];
		}
		$this->db->sql_freeresult($result);

		return $meta;
	}

	protected function source_label(string $source_key): string
	{
		$labels = [
			'guardian' => 'The Guardian',
			'bbc' => 'BBC',
			'dw' => 'DW',
			'cnbc' => 'CNBC',
			'dailymail' => 'Daily Mail',
			'ars' => 'Ars Technica',
			'zerohedge' => 'ZeroHedge',
			'foxnews' => 'Fox News',
			'wenxuecity' => 'Wenxuecity',
			'zaobao' => 'Zaobao',
			'sina_world' => 'Sina World',
			'sohu' => 'Sohu',
			'xinhua_world' => 'Xinhua World',
		];

		return $labels[$source_key] ?? '';
	}

	protected function digest_preview_text(string $post_text): string
	{
		$text = preg_replace('#<br\s*/?>#iu', "\n", $post_text) ?? $post_text;
		$text = preg_replace('#<s>.*?</s>#isu', '', $text) ?? $text;
		$text = preg_replace('#<e>.*?</e>#isu', '', $text) ?? $text;
		$text = preg_replace('#</?[^>]+>#u', '', $text) ?? $text;
		$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
		$text = preg_replace('/\[\/?b(?::[a-z0-9]+)?\]/iu', '', $text) ?? $text;
		$text = preg_replace('/\[url(?:=[^\]]*)?(?::[a-z0-9]+)?\](.*?)\[\/url(?::[a-z0-9]+)?\]/isu', '$1', $text) ?? $text;

		$lines = preg_split('/\R+/u', $text) ?: [];
		$body_lines = [];
		foreach ($lines as $line)
		{
			$line = trim((string) preg_replace('/\s+/u', ' ', $line));
			if ($line === ''
				|| preg_match('/^(来源|原标题)\s*[:：]/u', $line)
				|| $line === '阅读原文')
			{
				continue;
			}

			$body_lines[] = $line;
		}

		return trim(implode("\n", $body_lines));
	}

	protected function discussion_target_forum_templates(int $digest_forum_id): array
	{
		if (isset($this->discussion_forum_templates[$digest_forum_id]))
		{
			return $this->discussion_forum_templates[$digest_forum_id];
		}

		$rowset = $this->discussion_forum_rows();
		$blog_forum_id = (int) ($this->config['freemitbbs_blog_forum_id'] ?? 0);
		$eligible_post_ids = [];
		foreach ($rowset as $row)
		{
			$forum_id = (int) $row['forum_id'];
			if ((int) $row['forum_type'] === FORUM_POST
				&& $forum_id !== $digest_forum_id
				&& ($blog_forum_id <= 0 || $forum_id !== $blog_forum_id)
				&& (int) $row['forum_status'] === ITEM_UNLOCKED
				&& (string) $row['forum_password'] === ''
				&& (int) $row['display_on_index'] === 1
				&& $this->auth->acl_get('f_list', $forum_id)
				&& $this->auth->acl_get('f_read', $forum_id)
			)
			{
				$eligible_post_ids[$forum_id] = true;
			}
		}

		$visible_category_ids = [];
		foreach ($rowset as $row)
		{
			$forum_id = (int) $row['forum_id'];
			if ((int) $row['forum_type'] !== FORUM_CAT
				|| (int) $row['display_on_index'] !== 1
				|| !$this->auth->acl_get('f_list', $forum_id))
			{
				continue;
			}

			foreach ($rowset as $candidate)
			{
				$candidate_id = (int) $candidate['forum_id'];
				if (!isset($eligible_post_ids[$candidate_id]))
				{
					continue;
				}

				if ((int) $candidate['left_id'] > (int) $row['left_id']
					&& (int) $candidate['right_id'] < (int) $row['right_id'])
				{
					$visible_category_ids[$forum_id] = true;
					break;
				}
			}
		}

		$forums = [];
		foreach ($rowset as $row)
		{
			$forum_id = (int) $row['forum_id'];
			$is_cat = (int) $row['forum_type'] === FORUM_CAT;
			if (!$is_cat && !isset($eligible_post_ids[$forum_id]))
			{
				continue;
			}
			if ($is_cat && !isset($visible_category_ids[$forum_id]))
			{
				continue;
			}

			$level = 0;
			foreach ($rowset as $ancestor)
			{
				$ancestor_id = (int) $ancestor['forum_id'];
				if (!isset($visible_category_ids[$ancestor_id]))
				{
					continue;
				}
				if ((int) $ancestor['left_id'] < (int) $row['left_id']
					&& (int) $ancestor['right_id'] > (int) $row['right_id'])
				{
					$level++;
				}
			}

			$forums[] = [
				'forum_id' => $forum_id,
				'forum_name' => (string) $row['forum_name'],
				'is_cat' => $is_cat,
				'level' => $level,
			];
		}

		$this->discussion_forum_templates[$digest_forum_id] = $forums;

		return $forums;
	}

	protected function discussion_forum_rows(): array
	{
		if ($this->discussion_forum_rows !== null)
		{
			return $this->discussion_forum_rows;
		}

		$cached = $this->cache->get(self::DISCUSS_FORUM_CACHE_KEY);
		if (is_array($cached))
		{
			$this->discussion_forum_rows = $cached;
			return $this->discussion_forum_rows;
		}

		$sql = 'SELECT forum_id, forum_name, parent_id, forum_type, forum_status, forum_password, display_on_index, left_id, right_id
			FROM ' . FORUMS_TABLE . '
			WHERE ' . $this->db->sql_in_set('forum_type', [FORUM_CAT, FORUM_POST]) . '
			ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql);
		$rowset = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rowset[(int) $row['forum_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		$this->discussion_forum_rows = $rowset;
		$this->cache->put(self::DISCUSS_FORUM_CACHE_KEY, $rowset, self::DISCUSS_FORUM_CACHE_TTL);

		return $this->discussion_forum_rows;
	}

	protected function is_discussion_target_forum(int $forum_id, int $digest_forum_id): bool
	{
		foreach ($this->discussion_target_forum_templates($digest_forum_id) as $forum)
		{
			if (!(bool) $forum['is_cat'] && (int) $forum['forum_id'] === $forum_id)
			{
				return true;
			}
		}

		return false;
	}

	protected function discussion_links_by_digest_topic_ids(array $digest_topic_ids, int $digest_forum_id): array
	{
		$digest_topic_ids = array_values(array_unique(array_filter(array_map('intval', $digest_topic_ids), static function ($topic_id) {
			return $topic_id > 0;
		})));
		if (!$digest_topic_ids)
		{
			return [];
		}

		$sql = 'SELECT d.digest_topic_id, d.discussion_post_id, d.forum_id, t.topic_title
			FROM ' . $this->discussion_table . ' d
			INNER JOIN ' . POSTS_TABLE . ' p
				ON p.post_id = d.discussion_post_id
			INNER JOIN ' . TOPICS_TABLE . ' t
				ON t.topic_id = d.discussion_topic_id
			WHERE ' . $this->db->sql_in_set('d.digest_topic_id', $digest_topic_ids) . '
				AND p.post_visibility = ' . ITEM_APPROVED . '
				AND t.topic_visibility = ' . ITEM_APPROVED . '
				AND d.forum_id <> ' . $digest_forum_id . '
			ORDER BY d.created_time ASC, d.discussion_post_id ASC';
		$result = $this->db->sql_query($sql);
		$links = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$forum_id = (int) $row['forum_id'];
			if (!$this->auth->acl_get('f_read', $forum_id))
			{
				continue;
			}

			$digest_topic_id = (int) $row['digest_topic_id'];
			if (isset($links[$digest_topic_id]))
			{
				continue;
			}

			$post_id = (int) $row['discussion_post_id'];
			$links[$digest_topic_id] = [
				'title' => censor_text((string) $row['topic_title']),
				'url' => append_sid("{$this->phpbb_root_path}viewtopic.{$this->php_ext}", 'p=' . $post_id) . '#p' . $post_id,
			];
		}
		$this->db->sql_freeresult($result);

		return $links;
	}

	protected function digest_topic_first_post(int $topic_id): ?array
	{
		$sql = 'SELECT t.topic_id, t.forum_id, t.topic_title, p.post_text, p.bbcode_uid
			FROM ' . TOPICS_TABLE . ' t
			INNER JOIN ' . POSTS_TABLE . ' p
				ON p.post_id = t.topic_first_post_id
			WHERE t.topic_id = ' . $topic_id . '
				AND t.topic_visibility = ' . ITEM_APPROVED . '
				AND p.post_visibility = ' . ITEM_APPROVED;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return is_array($row) ? $row : null;
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

	protected function topic_title_max_chars(): int
	{
		$value = (int) ($this->config['max_topic_title_chars'] ?? 0);

		return $value > 0 ? $value : 50;
	}

	protected function filter_digest_forum_rowset(array $rowset): array
	{
		$forum_id = $this->digest_forum_id();
		if ($forum_id <= 0)
		{
			return $rowset;
		}

		$filtered = [];
		foreach ($rowset as $key => $row)
		{
			if ((int) ($row['forum_id'] ?? 0) === $forum_id)
			{
				continue;
			}

			$filtered[$key] = $row;
		}

		return $filtered;
	}

	protected function has_collapsible_categories(): bool
	{
		return $this->collapsible_operator
			&& method_exists($this->collapsible_operator, 'is_collapsed')
			&& method_exists($this->collapsible_operator, 'get_collapsible_link');
	}

	protected function truncate(string $text, int $max_chars, string $suffix = '...'): string
	{
		if ($max_chars <= 0)
		{
			return '';
		}
		$length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
		if ($length <= $max_chars)
		{
			return $text;
		}
		if (function_exists('mb_substr'))
		{
			return mb_substr($text, 0, max(0, $max_chars - strlen($suffix)), 'UTF-8') . $suffix;
		}

		return substr($text, 0, max(0, $max_chars - strlen($suffix))) . $suffix;
	}

	protected function bbcode_text(string $text): string
	{
		$text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

		return str_replace(['[', ']'], ['&#91;', '&#93;'], $text);
	}

	protected function escape(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}
}
