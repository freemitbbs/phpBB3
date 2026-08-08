<?php
/**
 *
 * Precise Similar Topics
 *
 * @copyright (c) 2013 Matt Friedman
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace vse\similartopics\core;

use phpbb\auth\auth;
use phpbb\cache\service as cache;
use phpbb\config\config;
use phpbb\config\db_text;
use phpbb\content_visibility;
use phpbb\db\driver\driver_interface as db;
use phpbb\event\dispatcher_interface as dispatcher;
use phpbb\language\language;
use phpbb\pagination;
use phpbb\request\request;
use phpbb\template\template;
use phpbb\user;
use vse\similartopics\driver\driver_interface as similartopics_driver;
use vse\similartopics\driver\manager as similartopics_manager;

class similar_topics
{
	public const SEARCH_TITLE_COLUMN = 'similar_topic_search_title';

	/** @var auth */
	protected $auth;

	/** @var cache */
	protected $cache;

	/** @var config */
	protected $config;

	/** @var db_text */
	protected $config_text;

	/** @var db */
	protected $db;

	/** @var dispatcher */
	protected $dispatcher;

	/** @var language */
	protected $language;

	/** @var pagination */
	protected $pagination;

	/** @var request */
	protected $request;

	/** @var template */
	protected $template;

	/** @var user */
	protected $user;

	/** @var content_visibility */
	protected $content_visibility;

	/** @var stop_word_helper */
	protected $stop_word_helper;

	/** @var similartopics_driver */
	protected $similartopics;

	/** @var string phpBB root path  */
	protected $root_path;

	/** @var string PHP file extension */
	protected $php_ext;

	/** @var string String of custom ignore words */
	protected $ignore_words;

	/** @var bool|null Whether the shadow search-title path is migration-ready */
	protected $search_title_index_available;

	/** @var array|null Password-protected forums for the current user */
	protected $passworded_forums;

	/**
	 * Constructor
	 *
	 * @access public
	 * @param auth                  $auth
	 * @param cache                 $cache
	 * @param config                 $config
	 * @param db_text               $config_text
	 * @param db                    $db
	 * @param dispatcher            $dispatcher
	 * @param language              $language
	 * @param pagination            $pagination
	 * @param request               $request
	 * @param template              $template
	 * @param user                  $user
	 * @param content_visibility    $content_visibility
	 * @param stop_word_helper      $stop_word_helper
	 * @param similartopics_manager $similartopics_manager
	 * @param string                $root_path
	 * @param string                $php_ext
	 */
	public function __construct(auth $auth, cache $cache, config $config, db_text $config_text, db $db, dispatcher $dispatcher, language $language, pagination $pagination, request $request, template $template, user $user, content_visibility $content_visibility, stop_word_helper $stop_word_helper, similartopics_manager $similartopics_manager, $root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->config_text = $config_text;
		$this->db = $db;
		$this->dispatcher = $dispatcher;
		$this->stop_word_helper = $stop_word_helper;
		$this->language = $language;
		$this->pagination = $pagination;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->content_visibility = $content_visibility;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;

		$this->similartopics = $similartopics_manager->get_driver($db->get_sql_layer());
	}

	/**
	 * Is similar topics available?
	 *
	 * @access public
	 * @return bool True if available, false otherwise
	 */
	public function is_available()
	{
		return $this->is_enabled() && $this->is_viewable() && $this->similartopics !== null;
	}

	/**
	 * Is similar topics configured?
	 *
	 * @access public
	 * @return bool True if configured, false otherwise
	 */
	public function is_enabled()
	{
		return !empty($this->config['similar_topics']) && !empty($this->config['similar_topics_limit']);
	}

	/**
	 * Is similar topics viewable by the user?
	 *
	 * @access public
	 * @return bool True if viewable, false otherwise
	 */
	public function is_viewable()
	{
		return !empty($this->user->data['user_similar_topics']) && $this->auth->acl_get('u_similar_topics');
	}

	/**
	 * Is dynamic similar topics available?
	 *
	 * @access public
	 * @return bool True if available, false otherwise
	 */
	public function is_dynamic_available()
	{
		return $this->is_dynamic_enabled() && $this->is_viewable() && $this->similartopics !== null;
	}

	/**
	 * Is dynamic similar topics enabled?
	 *
	 * @access public
	 * @return bool True if enabled, false otherwise
	 */
	public function is_dynamic_enabled()
	{
		return !empty($this->config['similar_topics_dynamic']) && !empty($this->config['similar_topics_limit']);
	}

	/**
	 * Get similar topics by matching topic titles
	 * Loosely based on viewforum.php lines 840-1040
	 *
	 * NOTE: FULLTEXT has built-in English ignore words. We use phpBB's
	 * ignore words for non-English languages. We also remove any
	 * admin-defined special ignore words.
	 *
	 * @access public
	 * @param array $topic_data Array with topic data
	 */
	public function display_similar_topics($topic_data)
	{
		// If the forum should not display similar topics, no need to continue
		if ($topic_data['similar_topics_hide'])
		{
			return;
		}

		$this->configure_stop_word_helper();
		$is_cjk_query = $this->stop_word_helper->has_cjk_characters($topic_data['topic_title']);
		$sql_array = $this->build_search_query((int) $topic_data['topic_id'], $topic_data['topic_title']);

		// If there are no usable search terms, no need to continue
		if (empty($sql_array))
		{
			return;
		}

		$tracking_topics = [];
		$this->apply_tracking_query_modifiers($sql_array, $tracking_topics);

		if (!$this->apply_forum_filters($sql_array, $topic_data['similar_topic_forums']))
		{
			return;
		}

		$rowset = $this->execute_similar_topics_query($this->apply_search_query_event($sql_array), $this->config['similar_topics_limit'], $this->config['similar_topics_cache']);

		if (empty($rowset) && $is_cjk_query && $this->similartopics->is_fulltext(self::SEARCH_TITLE_COLUMN))
		{
			$fallback_sql_array = $this->build_search_query((int) $topic_data['topic_id'], $topic_data['topic_title'], false);
			if (!empty($fallback_sql_array))
			{
				$this->apply_tracking_query_modifiers($fallback_sql_array, $tracking_topics);
				if ($this->apply_forum_filters($fallback_sql_array, $topic_data['similar_topic_forums']))
				{
					$rowset = $this->execute_similar_topics_query($this->apply_search_query_event($fallback_sql_array), $this->config['similar_topics_limit'], $this->config['similar_topics_cache']);
				}
			}
		}

		// Grab icons
		$icons = $this->cache->obtain_icons();

		/**
		 * Modify the rowset data for similar topics
		 *
		 * @event vse.similartopics.modify_rowset
		 * @var	array rowset Array with the search results data
		 * @since 1.4.2
		 */
		$vars = array('rowset');
		extract($this->dispatcher->trigger_event('vse.similartopics.modify_rowset', compact($vars)));

		foreach ($rowset as $row)
		{
			$similar_forum_id = (int) $row['forum_id'];
			$similar_topic_id = (int) $row['topic_id'];

			if ($this->auth->acl_get('f_read', $similar_forum_id))
			{
				// Get topic tracking info
				if ($this->user->data['is_registered'] && $this->config['load_db_lastread'] && !$this->config['similar_topics_cache'])
				{
					$topic_tracking_info = get_topic_tracking($similar_forum_id, $similar_topic_id, $rowset, array($similar_forum_id => $row['f_mark_time']));
				}
				else if ($this->config['load_anon_lastread'] || $this->user->data['is_registered'])
				{
					$topic_tracking_info = get_complete_topic_tracking($similar_forum_id, $similar_topic_id);

					if (!$this->user->data['is_registered'])
					{
						$this->user->data['user_lastmark'] = isset($tracking_topics['l']) ? ((int) base_convert($tracking_topics['l'], 36, 10) + (int) $this->config['board_startdate']) : 0;
					}
				}

				// Replies
				$replies = $this->content_visibility->get_count('topic_posts', $row, $similar_forum_id) - 1;

				// Get folder img, topic status/type related information
				$folder_img = $folder_alt = $topic_type = '';
				$unread_topic = isset($topic_tracking_info[$similar_topic_id]) && $row['topic_last_post_time'] > $topic_tracking_info[$similar_topic_id];
				topic_status($row, $replies, $unread_topic, $folder_img, $folder_alt, $topic_type);

				$view_topic_url_params = 't=' . $similar_topic_id;

				$topic_unapproved = $row['topic_visibility'] == ITEM_UNAPPROVED && $this->auth->acl_get('m_approve', $similar_forum_id);
				$posts_unapproved = $row['topic_visibility'] == ITEM_APPROVED && $row['topic_posts_unapproved'] && $this->auth->acl_get('m_approve', $similar_forum_id);
				$u_mcp_queue = ($topic_unapproved || $posts_unapproved) ? append_sid("{$this->root_path}mcp.$this->php_ext", 'i=queue&amp;mode=' . ($topic_unapproved ? 'approve_details' : 'unapproved_posts') . "&amp;t=$similar_topic_id", true, $this->user->session_id) : '';

				$base_url = append_sid("{$this->root_path}viewtopic.$this->php_ext", $view_topic_url_params);

				$topic_row = array(
					'TOPIC_AUTHOR_FULL'			=> get_topic_list_username_string('full', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
					'FIRST_POST_TIME'			=> $this->user->format_date($row['topic_time']),
					'FIRST_POST_TIME_RFC3339'	=> gmdate(DATE_RFC3339, $row['topic_time']),
					'LAST_POST_TIME'			=> $this->user->format_date($row['topic_last_post_time']),
					'LAST_POST_TIME_RFC3339'	=> gmdate(DATE_RFC3339, $row['topic_last_post_time']),
					'LAST_POST_AUTHOR_FULL'		=> get_topic_list_username_string('full', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),

					'TOPIC_REPLIES'			=> $replies,
					'TOPIC_VIEWS'			=> $row['topic_views'],
					'TOPIC_TITLE'			=> censor_text($row['topic_title']),
					'FORUM_TITLE'			=> $row['forum_name'],

					'TOPIC_IMG_STYLE'		=> $folder_img,
					'TOPIC_FOLDER_IMG'		=> $this->user->img($folder_img, $folder_alt),
					'TOPIC_FOLDER_IMG_ALT'	=> $this->language->lang($folder_alt),

					'TOPIC_ICON_IMG'		=> !empty($icons[$row['icon_id']]) ? $icons[$row['icon_id']]['img'] : '',
					'TOPIC_ICON_IMG_WIDTH'	=> !empty($icons[$row['icon_id']]) ? $icons[$row['icon_id']]['width'] : '',
					'TOPIC_ICON_IMG_HEIGHT'	=> !empty($icons[$row['icon_id']]) ? $icons[$row['icon_id']]['height'] : '',
					'ATTACH_ICON_IMG'		=> ($this->auth->acl_get('u_download') && $this->auth->acl_get('f_download', $similar_forum_id) && $row['topic_attachment']) ? $this->user->img('icon_topic_attach', $this->language->lang('TOTAL_ATTACHMENTS')) : '',
					'UNAPPROVED_IMG'		=> ($topic_unapproved || $posts_unapproved) ? $this->user->img('icon_topic_unapproved', $topic_unapproved ? 'TOPIC_UNAPPROVED' : 'POSTS_UNAPPROVED') : '',

					'S_UNREAD_TOPIC'		=> $unread_topic,
					'S_TOPIC_REPORTED'		=> !empty($row['topic_reported']) && $this->auth->acl_get('m_report', $similar_forum_id),
					'S_TOPIC_UNAPPROVED'	=> $topic_unapproved,
					'S_POSTS_UNAPPROVED'	=> $posts_unapproved,
					'S_HAS_POLL'			=> (bool) $row['poll_start'],

					'U_NEWEST_POST'			=> append_sid("{$this->root_path}viewtopic.$this->php_ext", $view_topic_url_params . '&amp;view=unread') . '#unread',
					'U_LAST_POST'			=> append_sid("{$this->root_path}viewtopic.$this->php_ext", $view_topic_url_params . '&amp;p=' . $row['topic_last_post_id']) . '#p' . $row['topic_last_post_id'],
					'U_VIEW_TOPIC'			=> $base_url,
					'U_VIEW_FORUM'			=> append_sid("{$this->root_path}viewforum.$this->php_ext", 'f=' . $similar_forum_id),
					'U_MCP_REPORT'			=> append_sid("{$this->root_path}mcp.$this->php_ext", 'i=reports&amp;mode=reports&amp;' . $view_topic_url_params, true, $this->user->session_id),
					'U_MCP_QUEUE'			=> $u_mcp_queue,
				);

				/**
				 * Event to modify the similar topics template block
				 *
				 * @event vse.similartopics.modify_topicrow
				 * @var array row       Array with similar topic data
				 * @var array topic_row Template block array
				 * @since 1.3.0
				 */
				$vars = array('row', 'topic_row');
				extract($this->dispatcher->trigger_event('vse.similartopics.modify_topicrow', compact($vars)));

				$this->template->assign_block_vars('similar', $topic_row);

				$this->pagination->generate_template_pagination($base_url, 'similar.pagination', 'start', $replies + 1, $this->config['posts_per_page'], 1, true, true);
			}
		}

		$this->add_language();

		$this->template->assign_vars(array(
			'NEWEST_POST_IMG'	=> $this->user->img('icon_topic_newest', 'VIEW_NEWEST_POST'),
			'LAST_POST_IMG'		=> $this->user->img('icon_topic_latest', 'VIEW_LATEST_POST'),
			'REPORTED_IMG'		=> $this->user->img('icon_topic_reported', 'TOPIC_REPORTED'),
			'POLL_IMG'			=> $this->user->img('icon_topic_poll', 'TOPIC_POLL'),
		));
	}

	/**
	 * Add lang files for similar topics
	 *
	 * @return void
	 */
	public function add_language()
	{
		$this->language->add_lang('similar_topics', 'vse/similartopics');
	}

	/**
	 * Check if we should load localized ignore words
	 *
	 * @access protected
	 * @return bool True if non-English language or using a dbms with no stop-words
	 */
	protected function get_localized_ignore_words()
	{
		return !in_array($this->user->lang_name, ['en', 'en_us'], true) || !$this->similartopics->has_stopword_support();
	}

	/**
	 * Search for similar topics via AJAX for dynamic suggestions
	 *
	 * @param string $query The search query
	 * @param int $forum_id The forum ID to search from
	 * @return array Array of similar topics
	 */
	public function search_similar_topics_ajax($query, $forum_id = 0)
	{
		$this->configure_stop_word_helper();
		$is_cjk_query = $this->stop_word_helper->has_cjk_characters($query);
		$sql_array = $this->build_search_query(0, $query);

		if (empty($sql_array))
		{
			return [];
		}

		$similar_topic_forums = null;
		if ($forum_id > 0)
		{
			$sql = 'SELECT similar_topic_forums
				FROM ' . FORUMS_TABLE . '
				WHERE forum_id = ' . (int) $forum_id;
			$result = $this->db->sql_query($sql, 3600);
			$similar_topic_forums = $this->db->sql_fetchfield('similar_topic_forums');
			$this->db->sql_freeresult($result);
		}

		if (!$this->apply_forum_filters($sql_array, $similar_topic_forums))
		{
			return [];
		}

		$topics = [];
		$rowset = $this->execute_similar_topics_query($sql_array, 5);

		if (empty($rowset) && $is_cjk_query && $this->similartopics->is_fulltext(self::SEARCH_TITLE_COLUMN))
		{
			$fallback_sql_array = $this->build_search_query(0, $query, false);
			if (!empty($fallback_sql_array) && $this->apply_forum_filters($fallback_sql_array, $similar_topic_forums))
			{
				$rowset = $this->execute_similar_topics_query($fallback_sql_array, 5);
			}
		}

		foreach ($rowset as $row)
		{
			if ($this->auth->acl_get('f_read', (int) $row['forum_id']))
			{
				$topics[] = [
					'id' => (int) $row['topic_id'],
					'title' => censor_text($row['topic_title']),
					'url' => append_sid("{$this->root_path}viewtopic.$this->php_ext", 't=' . $row['topic_id'])
				];
			}
		}

		return $topics;
	}

	/**
	 * Build the SQL query array for a title search.
	 *
	 * Uses a token-based query path when the input contains CJK characters,
	 * because the default database full-text tokenizers are often not usable
	 * for Chinese/Japanese/Korean titles without server-side parser support.
	 *
	 * @param int $topic_id
	 * @param string $text
	 * @return array
	 */
	protected function build_search_query($topic_id, $text, $prefer_fulltext = true)
	{
		$sensitivity = $this->config->offsetExists('similar_topics_sense')
			? number_format($this->config['similar_topics_sense'] / 10, 1, '.', '')
			: '0.5';

		if ($this->stop_word_helper->has_cjk_characters($text))
		{
			$search_text = $this->stop_word_helper->build_index_text($text);
			if ($search_text === '')
			{
				return [];
			}

			$search_column = $this->can_use_search_title_index() ? self::SEARCH_TITLE_COLUMN : 'topic_title';

			if ($prefer_fulltext && $search_column === self::SEARCH_TITLE_COLUMN && $this->similartopics->is_fulltext(self::SEARCH_TITLE_COLUMN))
			{
				return $this->similartopics->get_query($topic_id, $search_text, $this->config['similar_topics_time'], $sensitivity, self::SEARCH_TITLE_COLUMN);
			}

			return $this->similartopics->get_term_query($topic_id, explode(' ', $search_text), $this->config['similar_topics_time'], $sensitivity, $search_column);
		}

		$terms = $this->stop_word_helper->get_search_terms($text, true);
		if (empty($terms))
		{
			return [];
		}

		return $this->similartopics->get_query($topic_id, implode(' ', $terms), $this->config['similar_topics_time'], $sensitivity);
	}

	/**
	 * Sync the shadow full-text search title for a topic.
	 *
	 * @param int $topic_id
	 * @param string $title
	 * @return void
	 */
	public function update_search_title_index($topic_id, $title)
	{
		$this->configure_stop_word_helper();

		$topic_id = (int) $topic_id;
		if ($topic_id <= 0 || !$this->can_use_search_title_index())
		{
			return;
		}

		$search_title = $this->stop_word_helper->build_index_text($title);

		$sql = 'UPDATE ' . TOPICS_TABLE . "
			SET " . self::SEARCH_TITLE_COLUMN . " = '" . $this->db->sql_escape($search_title) . "'
			WHERE topic_id = $topic_id";
		$this->db->sql_query($sql);
	}

	protected function can_use_search_title_index()
	{
		if ($this->search_title_index_available !== null)
		{
			return $this->search_title_index_available;
		}

		$this->search_title_index_available = $this->config->offsetExists('similar_topics_search_title_ready')
			&& !empty($this->config['similar_topics_search_title_ready']);
		return $this->search_title_index_available;
	}

	/**
	 * Configure the stop-word helper using the active localized and custom ignore words.
	 *
	 * @return void
	 */
	protected function configure_stop_word_helper()
	{
		$this->stop_word_helper->set_use_localized($this->get_localized_ignore_words());
		$this->stop_word_helper->set_additional_ignore_words($this->get_additional_ignore_words());
	}

	/**
	 * Apply topic tracking joins to a similar-topics query.
	 *
	 * @param array $sql_array
	 * @param array $tracking_topics
	 * @return void
	 */
	protected function apply_tracking_query_modifiers(array &$sql_array, array &$tracking_topics)
	{
		if ($this->user->data['is_registered'] && $this->config['load_db_lastread'] && !$this->config['similar_topics_cache'])
		{
			$sql_array['LEFT_JOIN'][] = array('FROM' => array(TOPICS_TRACK_TABLE => 'tt'), 'ON' => 'tt.topic_id = t.topic_id AND tt.user_id = ' . $this->user->data['user_id']);
			$sql_array['LEFT_JOIN'][] = array('FROM' => array(FORUMS_TRACK_TABLE => 'ft'), 'ON' => 'ft.forum_id = f.forum_id AND ft.user_id = ' . $this->user->data['user_id']);
			$sql_array['SELECT'] .= ', tt.mark_time, ft.mark_time as f_mark_time';
		}
		else if ($this->config['load_anon_lastread'] || $this->user->data['is_registered'])
		{
			// Cookie based tracking copied from search.php
			$tracking_topics = $this->request->variable($this->config['cookie_name'] . '_track', '', true, \phpbb\request\request_interface::COOKIE);
			$tracking_topics = $tracking_topics ? tracking_unserialize($tracking_topics) : array();
		}
	}

	/**
	 * Apply forum visibility filters to a similar-topics query.
	 *
	 * @param array $sql_array
	 * @param string|null $similar_topic_forums
	 * @return bool
	 */
	protected function apply_forum_filters(array &$sql_array, $similar_topic_forums = null)
	{
		$passworded_forums = $this->get_passworded_forums();
		$excluded_forums = $passworded_forums;
		$news_digest_forum_id = (int) ($this->config['newsscraper_digest_forum_id'] ?? 0);
		if ($news_digest_forum_id > 0)
		{
			$excluded_forums[] = $news_digest_forum_id;
			$excluded_forums = array_values(array_unique($excluded_forums));
		}

		if (!empty($similar_topic_forums))
		{
			$included_forums = array_diff(json_decode($similar_topic_forums, true), $excluded_forums);
			if (empty($included_forums))
			{
				return false;
			}

			$sql_array['WHERE'] .= ' AND ' . $this->db->sql_in_set('f.forum_id', $included_forums);
			return true;
		}

		if (count($excluded_forums))
		{
			$sql_array['WHERE'] .= ' AND ' . $this->db->sql_in_set('f.forum_id', $excluded_forums, true);
		}

		$sql_array['WHERE'] .= ' AND f.similar_topics_ignore = 0';
		return true;
	}

	protected function get_passworded_forums()
	{
		if ($this->passworded_forums === null)
		{
			$this->passworded_forums = $this->user->get_passworded_forums();
		}

		return $this->passworded_forums;
	}

	/**
	 * Allow extensions to modify a similar-topics search query.
	 *
	 * @param array $sql_array
	 * @return array
	 */
	protected function apply_search_query_event(array $sql_array)
	{
		$vars = array('sql_array');
		extract($this->dispatcher->trigger_event('vse.similartopics.get_topic_data', compact($vars)));

		return $sql_array;
	}

	/**
	 * Execute a similar-topics search query and return a rowset.
	 *
	 * @param array $sql_array
	 * @param int $limit
	 * @param int $cache
	 * @return array
	 */
	protected function execute_similar_topics_query(array $sql_array, $limit, $cache = 0)
	{
		$rowset = [];
		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query_limit($sql, (int) $limit, 0, (int) $cache);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$rowset[(int) $row['topic_id']] = $row;
		}

		$this->db->sql_freeresult($result);

		return $rowset;
	}

	/**
	 * Get custom ignore words if any were defined for similar topics
	 *
	 * @access protected
	 * @return string|null String of ignore words or null if there are none defined
	 */
	protected function get_additional_ignore_words()
	{
		$key = 'similar_topics_words';

		$cache = $this->cache->get_driver();

		if ($this->ignore_words === null && (($this->ignore_words = $cache->get("_$key")) === false))
		{
			$this->ignore_words = $this->config_text->get($key);

			$cache->put("_$key", $this->ignore_words);
		}

		return $this->ignore_words;
	}
}
