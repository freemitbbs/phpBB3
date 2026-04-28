<?php
/**
*
* @package Announcements on index
* @copyright (c) 2015 david63
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace david63\announceonindex\event;

/**
* @ignore
*/
use phpbb\config\config;
use phpbb\template\template;
use phpbb\user;
use phpbb\db\driver\driver_interface as db;
use Symfony\Component\DependencyInjection\ContainerInterface;
use phpbb\auth\auth;
use phpbb\cache\service as cache;
use phpbb\collapsiblecategories\operator\operator as cc_operator;
use rmcgirr83\nationalflags\core\nationalflags;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var config */
	protected $config;

	/** @var template */
	protected $template;

	/** @var user */
	protected $user;

	/** @var driver_interface */
	protected $db;

	/** @var string phpBB root path */
	protected $root_path;

	/** @var string PHP extension */
	protected $phpEx;

	/** @var phpbb_container */
	protected $phpbb_container;

	/** @var auth */
	protected $auth;

	/** @var cache */
	protected $cache;

	public function __construct(
		config $config,
		template $template,
		user $user,
		db $db,
		string $root_path,
		string $php_ext,
		ContainerInterface $phpbb_container,
		auth $auth,
		cache $cache,
		cc_operator $cc_operator = null,
		nationalflags $nationalflags = null)
	{
		$this->config			= $config;
		$this->template			= $template;
		$this->user				= $user;
		$this->db				= $db;
		$this->root_path		= $root_path;
		$this->phpEx			= $php_ext;
		$this->phpbb_container	= $phpbb_container;
		$this->auth				= $auth;
		$this->cache			= $cache;
		$this->cc_operator 		= $cc_operator;
		$this->nationalflags	= $nationalflags;
	}

	/**
	* Assign functions defined in this class to event listeners in the core
	*
	* @return array
	* @static
	* @access public
	*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.index_modify_page_title'	=> 'add_announcements_to_index',
			'core.page_header_after'		=> 'add_to_header',
		);
	}


	public function add_to_header($event)
	{
		$this->template->assign_vars(array(
				'S_ALLOW_GUESTS' 		=> ($this->config['announce_guest']) ? true : false,
		));
	}

	/**
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function add_announcements_to_index($event)
	{
		if ($this->cc_operator !== null)
		{
			$aoi = 'announceonindex'; // can be any unique string to identify your extension's collapsible element
			$this->template->assign_vars([
				'S_AOI_HIDDEN' => $this->cc_operator->is_collapsed($aoi),
				'U_AOI_COLLAPSE_URL' => $this->cc_operator->get_collapsible_link($aoi),
			]);
		}

		if ($this->config['announce_global_on_index'] || $this->config['announce_announcement_on_index'])
		{
			$phpbb_content_visibility = $this->phpbb_container->get('content.visibility');

			// Grab icons
			$icons = $this->cache->obtain_icons();

			$topic_list = $rowset = array();

			$sql_array = array(
				'SELECT'	=> 't.*',
				'FROM'		=> array(
					TOPICS_TABLE		=> 't'
				),
				'LEFT_JOIN'	=> array(),
			);

			if ($this->user->data['is_registered'])
			{
				if ($this->config['load_db_track'])
				{
					$sql_array['LEFT_JOIN'][] = array('FROM' => array(TOPICS_POSTED_TABLE => 'tp'), 'ON' => 'tp.topic_id = t.topic_id AND tp.user_id = ' . $this->user->data['user_id']);
					$sql_array['SELECT'] .= ', tp.topic_posted';
				}

				if ($this->config['load_db_lastread'])
				{
					$sql_array['LEFT_JOIN'][] = array('FROM' => array(TOPICS_TRACK_TABLE => 'tt'), 'ON' => 'tt.topic_id = t.topic_id AND tt.user_id = ' . $this->user->data['user_id']);
					$sql_array['SELECT'] .= ', tt.mark_time as mark_time';
					$sql_array['LEFT_JOIN'][] = array('FROM' => array(FORUMS_TRACK_TABLE => 'ft'), 'ON' => 'ft.forum_id = t.forum_id AND ft.user_id = ' . $this->user->data['user_id']);
					$sql_array['SELECT'] .= ', ft.mark_time as forum_mark_time';
				}
			}

			$g_forum_ary = $this->auth->acl_getf('f_read', true);
			$g_forum_ary = array_unique(array_keys($g_forum_ary));

			$sql_anounce_array['LEFT_JOIN'] = $sql_array['LEFT_JOIN'];
			$sql_anounce_array['LEFT_JOIN'][] = array('FROM' => array(FORUMS_TABLE => 'f'), 'ON' => 'f.forum_id = t.forum_id');
			$sql_anounce_array['SELECT'] = $sql_array['SELECT'] . ', f.forum_name, f.enable_icons';

			$sql_and = '';
			if ($this->config['announce_announcement_on_index'])
			{
				$sql_and = ' t.topic_type = ' . POST_ANNOUNCE;
			}

			if ($this->config['announce_global_on_index'])
			{
				$sql_and = ' t.topic_type = ' . POST_GLOBAL;
			}

			if ($this->config['announce_global_on_index'] && $this->config['announce_announcement_on_index'])
			{
				$sql_and = ' t.topic_type = ' . POST_ANNOUNCE . ' OR t.topic_type = ' . POST_GLOBAL;
			}

			if ($this->nationalflags !== null)
			{
				$sql_anounce_array['SELECT'] = $sql_anounce_array['SELECT'] . ', u.user_flag';
				$sql_anounce_array['LEFT_JOIN'][] = array('FROM' => array(USERS_TABLE => 'u'), 'ON' => 't.topic_last_poster_id = u.user_id');
			}

			$sql_ary = array(
				'SELECT'	=> $sql_anounce_array['SELECT'],
				'FROM'		=> $sql_array['FROM'],
				'LEFT_JOIN'	=> $sql_anounce_array['LEFT_JOIN'],

				'WHERE'		=> $this->db->sql_in_set('t.forum_id', $g_forum_ary, false, true) . '
					AND ' . $this->db->sql_escape($sql_and),
				'ORDER_BY'	=> 't.topic_last_post_time DESC',
			);

			$sql = $this->db->sql_build_query('SELECT', $sql_ary);
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				if (!$phpbb_content_visibility->is_visible('topic', $row['forum_id'], $row))
				{
					// Do not display announcements that are waiting for approval or soft deleted.
					continue;
				}
				$topic_list[] = $row['topic_id'];
				$rowset[$row['topic_id']] = $row;
			}
			$this->db->sql_freeresult($result);

			// Generate topic forum list...
			$topic_forum_list = array();
			foreach ($rowset as $t_id => $row)
			{
				$topic_forum_list[$row['forum_id']]['forum_mark_time'] = ($this->config['load_db_lastread'] && $this->user->data['is_registered'] && isset($row['forum_mark_time'])) ? $row['forum_mark_time'] : 0;
				$topic_forum_list[$row['forum_id']]['topics'][] = (int) $t_id;
			}

			$topic_tracking_info = array();
			if ($this->config['load_db_lastread'] && $this->user->data['is_registered'])
			{
				foreach ($topic_forum_list as $f_id => $topic_row)
				{
					$topic_tracking_info += get_topic_tracking($f_id, $topic_row['topics'], $rowset, array($f_id => $topic_row['forum_mark_time']));
				}
			}
			else if ($this->config['load_anon_lastread'] || $this->user->data['is_registered'])
			{
				foreach ($topic_forum_list as $f_id => $topic_row)
				{
					$topic_tracking_info += get_complete_topic_tracking($f_id, $topic_row['topics']);
				}
			}

			unset($topic_forum_list);

			foreach ($topic_list as $topic_id)
			{
				$row = $rowset[$topic_id];

				$forum_id = $row['forum_id'];
				$topic_id = $row['topic_id'];

				$unread_topic = (isset($topic_tracking_info[$topic_id]) && $row['topic_last_post_time'] > $topic_tracking_info[$topic_id]) ? true : false;

				$replies = $phpbb_content_visibility->get_count('topic_posts', $row, $forum_id) - 1;

				// Correction for case of unapproved topic visible to poster
				if ($replies < 0)
				{
					$replies = 0;
				}

				// Get folder img, topic status/type related information
				$folder_img = $folder_alt = $topic_type = '';
				topic_status($row, $replies, $unread_topic, $folder_img, $folder_alt, $topic_type);

				$user_flag = '';
				// nationalflags installed?
				if (!empty($row['user_flag']))
				{
					$user_flag = $this->nationalflags->get_user_flag($row['user_flag'], 12);
				}

				$this->template->assign_block_vars('topicrow', array(
					'FIRST_POST_TIME'		=> $this->user->format_date($row['topic_time']),
					'LAST_POST_TIME'		=> $this->user->format_date($row['topic_last_post_time']),
					'LAST_POST_AUTHOR'		=> get_username_string('username', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
					'LAST_POST_AUTHOR_FULL'	=> get_username_string('full', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
					'TOPIC_TITLE'			=> censor_text($row['topic_title']),
					'LAST_POST_TIME_RFC3339'	=> gmdate(DATE_RFC3339, $row['topic_last_post_time']),
					'TOPIC_DESCRIPTION'		=> (!empty($row['topic_desc'])) ? censor_text($row['topic_desc']) : '',
					'REPLIES'				=> $replies,
					'VIEWS'					=> $this->user->lang($row['topic_views']),
					'TOPIC_AUTHOR_FULL'		=> get_username_string('full', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
					'TOPIC_LAST_AUTHOR'		=> get_username_string('full', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
					'TOPIC_IMG_STYLE'		=> $folder_img,
					'TOPIC_FOLDER_IMG'		=> $this->user->img($folder_img, $folder_alt),
					'TOPIC_FOLDER_IMG_ALT'	=> $this->user->lang[$folder_alt],

					'TOPIC_ICON_IMG'		=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['img'] : '',
					'TOPIC_ICON_IMG_WIDTH'	=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['width'] : '',
					'TOPIC_ICON_IMG_HEIGHT'	=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['height'] : '',

					'S_ALLOW_EVENTS'		=> ($this->config['announce_event']) ? true : false,
					'S_UNREAD'				=> $unread_topic,
					'S_TOPIC_ICONS'			=> (!empty($row['enable_icons'])) ? true : false,
					'S_POST_ANNOUNCE'		=> ($row['topic_type'] == POST_ANNOUNCE) ? true : false,
					'S_POST_GLOBAL'			=> ($row['topic_type'] == POST_GLOBAL) ? true : false,
					'USER_FLAG'				=> $user_flag,
					'U_LAST_POST'			=> append_sid("{$this->root_path}viewtopic.$this->phpEx", "f=$forum_id&amp;t=$topic_id&amp;p=" . $row['topic_last_post_id']) . '#p' . $row['topic_last_post_id'],
					'U_NEWEST_POST'			=> append_sid("{$this->root_path}viewtopic.$this->phpEx", "f=$forum_id&amp;t=$topic_id&amp;view=unread") . '#unread',
					'U_VIEW_TOPIC'			=> append_sid("{$this->root_path}viewtopic.$this->phpEx", "f=$forum_id&amp;t=$topic_id"),
				));
			}
		}
	}
}
