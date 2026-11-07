<?php

namespace freemitbbs\anonymousposts\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	// Keep the stored name independent of the submitting user's language.
	private const PUBLIC_NAME = '匿名';
	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\language\language $language;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;
	protected string $root_path;
	protected string $php_ext;
	protected array $post_cache = [];

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		string $root_path,
		string $php_ext
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language',
			'core.posting_modify_message_text' => 'prepare_form_data',
			'core.posting_modify_submission_errors' => 'validate_choice',
			'core.posting_modify_template_vars' => 'posting_form',
			'core.viewtopic_assign_template_vars_before' => 'quick_reply',
			'core.modify_submit_post_data' => ['prepare_submission', -1000],
			'core.submit_post_modify_sql_data' => ['store_anonymous_author', -1000],
			'core.notification_manager_add_notifications_before' => ['mention_identity', -1000],
			'core.posting_modify_cannot_edit_conditions' => 'allow_owner_edit',
			'core.modify_posting_auth' => 'allow_owner_delete',
			'core.handle_post_delete_conditions' => 'delete_conditions',
			'core.viewtopic_post_rowset_data' => 'remember_post',
			'core.viewtopic_modify_post_row' => ['post_buttons', -1000],
			'core.mcp_post_template_data' => 'moderator_author',
			'core.mcp_queue_approve_details_template' => 'moderator_author',
		];
	}

	public function load_language(): void
	{
		$this->language->add_lang('common', 'freemitbbs/anonymousposts');
	}

	protected function allowed(int $forum_id): bool
	{
		return !empty($this->user->data['is_registered'])
			&& empty($this->user->data['is_bot'])
			&& $forum_id > 0
			&& in_array($forum_id, array_map('intval', explode(',', $this->config['anonymousposts_forum_ids'] ?? '')), true);
	}

	protected function requested(): bool
	{
		return $this->request->variable('anonymous_post', false, false, \phpbb\request\request_interface::POST);
	}

	protected function post(int $post_id): array
	{
		if ($post_id <= 0)
		{
			return [];
		}
		if (!array_key_exists($post_id, $this->post_cache))
		{
			$result = $this->db->sql_query('SELECT * FROM ' . POSTS_TABLE . ' WHERE post_id = ' . $post_id);
			$this->post_cache[$post_id] = $this->db->sql_fetchrow($result) ?: [];
			$this->db->sql_freeresult($result);
		}
		return $this->post_cache[$post_id];
	}

	protected function is_owner(array $post): bool
	{
		return !empty($this->user->data['is_registered'])
			&& (int) ($post['anonymous_user_id'] ?? 0) > ANONYMOUS
			&& (int) $post['anonymous_user_id'] === (int) $this->user->data['user_id'];
	}

	protected function anonymous_submission(string $mode, int $forum_id, int $post_id): bool
	{
		if ($mode === 'edit')
		{
			return !empty($this->post($post_id)['anonymous_user_id']);
		}
		return in_array($mode, ['post', 'reply', 'quote'], true) && $this->allowed($forum_id) && $this->requested();
	}

	public function validate_choice($event): void
	{
		if ($this->requested() && !$this->anonymous_submission($event['mode'], (int) $event['forum_id'], (int) $event['post_id']))
		{
			$error = $event['error'];
			$error[] = $this->language->lang('ANONPOST_UNAVAILABLE');
			$event['error'] = $error;
		}
	}

	public function prepare_form_data($event): void
	{
		if (!$this->anonymous_submission($event['mode'], (int) $event['forum_id'], (int) $event['post_id']))
		{
			return;
		}
		$post_data = $event['post_data'];
		$post_data['enable_sig'] = false;
		if ($event['mode'] === 'edit')
		{
			$post_data['username'] = self::PUBLIC_NAME;
		}
		$event['post_data'] = $post_data;
		$attachment_ids = array_map('intval', array_column($event['message_parser']->attachment_data, 'attach_id'));
		if (!$attachment_ids)
		{
			return;
		}
		$result = $this->db->sql_query('SELECT physical_filename FROM ' . ATTACHMENTS_TABLE . ' WHERE ' . $this->db->sql_in_set('attach_id', $attachment_ids));
		while ($attachment = $this->db->sql_fetchrow($result))
		{
			// Old orphan uploads may still contain an account ID in their storage URL.
			if (preg_match('/^\d+_/', $attachment['physical_filename']))
			{
				$error = $event['error'];
				$error[] = $this->language->lang('ANONPOST_REUPLOAD');
				$event['error'] = $error;
				break;
			}
		}
		$this->db->sql_freeresult($result);
	}

	public function posting_form($event): void
	{
		$locked = $event['mode'] === 'edit' && !empty($this->post((int) $event['post_id'])['anonymous_user_id']);
		$page_data = $event['page_data'];
		$page_data['S_ANONPOST_ALLOWED'] = in_array($event['mode'], ['post', 'reply', 'quote'], true) && $this->allowed((int) $event['forum_id']);
		// Core saved/recovery drafts contain text only. Restore them conservatively;
		// the author can explicitly uncheck anonymity before publishing.
		$page_data['S_ANONPOST_CHECKED'] = $this->requested() || (!empty($event['draft_id']) && !$this->request->is_set_post('message'));
		$page_data['S_ANONPOST_LOCKED'] = $locked;
		if ($locked || ($page_data['S_ANONPOST_ALLOWED'] && $page_data['S_ANONPOST_CHECKED']))
		{
			$page_data['PREVIEW_SIGNATURE'] = '';
			$page_data['S_SIGNATURE_CHECKED'] = '';
		}
		if ($locked)
		{
			$page_data['S_DISPLAY_USERNAME'] = false;
		}
		$event['page_data'] = $page_data;
	}

	public function quick_reply($event): void
	{
		$this->template->assign_var('S_ANONPOST_ALLOWED', $this->allowed((int) $event['forum_id']));
	}

	public function prepare_submission($event): void
	{
		$data = $event['data'];
		if (!$this->anonymous_submission($event['mode'], (int) $data['forum_id'], (int) ($data['post_id'] ?? 0)))
		{
			return;
		}
		$data['enable_sig'] = false;
		if ($event['mode'] === 'edit')
		{
			// Editors cannot change the public identity, including through a forged username.
			$data['poster_id'] = ANONYMOUS;
			if ($this->is_owner($this->post((int) $data['post_id'])))
			{
				$data['post_edit_user'] = ANONYMOUS;
			}
		}
		$event['data'] = $data;
		$event['username'] = self::PUBLIC_NAME;
	}

	public function store_anonymous_author($event): void
	{
		$data = $event['data'];
		$new_post = in_array($event['post_mode'], ['post', 'reply'], true);
		$mode = $new_post ? $event['post_mode'] : 'edit';
		if (!$this->anonymous_submission($mode, (int) $data['forum_id'], (int) ($data['post_id'] ?? 0)))
		{
			return;
		}
		$sql_data = $event['sql_data'];
		$post =& $sql_data[POSTS_TABLE]['sql'];
		$post['poster_id'] = ANONYMOUS;
		$post['post_username'] = self::PUBLIC_NAME;
		$post['enable_sig'] = 0;
		$post['post_postcount'] = 0;
		if ($new_post)
		{
			$post['anonymous_user_id'] = (int) $this->user->data['user_id'];
			// Keep account flood control; don't expose anonymous activity in public post counts.
			$sql_data[USERS_TABLE]['stat'] = ['user_lastpost_time = ' . (int) $post['post_time']];
		}
		if (isset($sql_data[TOPICS_TABLE]['sql']['topic_first_poster_name']))
		{
			$sql_data[TOPICS_TABLE]['sql']['topic_poster'] = ANONYMOUS;
			$sql_data[TOPICS_TABLE]['sql']['topic_first_poster_name'] = self::PUBLIC_NAME;
			$sql_data[TOPICS_TABLE]['sql']['topic_first_poster_colour'] = '';
		}
		$event['sql_data'] = $sql_data;
	}

	public function allow_owner_edit($event): void
	{
		if ($this->is_owner($event['post_data']))
		{
			// Preserve the ordinary edit time, lock and forum permission checks.
			$event['s_cannot_edit'] = false;
		}
	}

	public function mention_identity($event): void
	{
		// The mentions extension builds notifications from the session user after
		// submit_post(), so it must also use the post's public identity.
		if (!in_array('paul999.mention.notification.type.mention', (array) $event['notification_type_name'], true))
		{
			return;
		}
		$data = $event['data'];
		if (!empty($this->post((int) ($data['post_id'] ?? 0))['anonymous_user_id']))
		{
			$data['poster_id'] = ANONYMOUS;
			$data['username'] = self::PUBLIC_NAME;
			$event['data'] = $data;
		}
	}

	protected function can_delete(array $post, int $last_post_id, bool $soft): bool
	{
		return $this->is_owner($post)
			&& $this->auth->acl_get($soft ? 'f_softdelete' : 'f_delete', (int) $post['forum_id'])
			&& (int) $post['post_id'] === $last_post_id
			&& empty($post['post_edit_locked'])
			&& (!$this->config['delete_time'] || (int) $post['post_time'] > time() - (int) $this->config['delete_time'] * 60);
	}

	public function allow_owner_delete($event): void
	{
		if (in_array($event['mode'], ['delete', 'soft_delete'], true)
			&& $this->can_delete($event['post_data'], (int) $event['post_data']['topic_last_post_id'], $event['mode'] === 'soft_delete'))
		{
			$event['is_authed'] = true;
		}
	}

	public function delete_conditions($event): void
	{
		if ($this->can_delete($event['post_data'], (int) $event['post_data']['topic_last_post_id'], (bool) $event['is_soft']))
		{
			$event[$event['is_soft'] ? 'force_softdelete_allowed' : 'force_delete_allowed'] = true;
		}
	}

	public function remember_post($event): void
	{
		$row = $event['row'];
		$this->post_cache[(int) $row['post_id']] = $row;
		if (!empty($row['anonymous_user_id']) && (int) $row['post_delete_user'] === (int) $row['anonymous_user_id'])
		{
			$rowset = $event['rowset_data'];
			$rowset['post_delete_user'] = ANONYMOUS;
			$event['rowset_data'] = $rowset;
		}
	}

	public function post_buttons($event): void
	{
		$post = $this->post((int) $event['row']['post_id']);
		if (empty($post['anonymous_user_id']))
		{
			return;
		}
		$row = $event['post_row'];
		// All anonymous posts share phpBB's public guest ID; that does not make them all the OP.
		$first = $this->post((int) $event['topic_data']['topic_first_post_id']);
		$row['S_TOPIC_POSTER'] = !empty($first['anonymous_user_id']) && (int) $first['anonymous_user_id'] === (int) $post['anonymous_user_id'];
		if ($this->is_owner($post))
		{
			$url = $this->root_path . 'posting.' . $this->php_ext;
			if ($this->auth->acl_get('f_edit', (int) $post['forum_id']) && empty($post['post_edit_locked'])
				&& (!$this->config['edit_time'] || (int) $post['post_time'] > time() - (int) $this->config['edit_time'] * 60))
			{
				$row['U_EDIT'] = append_sid($url, 'mode=edit&amp;p=' . (int) $post['post_id']);
			}
			$last_id = (int) $event['topic_data']['topic_last_post_id'];
			$soft = $this->can_delete($post, $last_id, true);
			if ($soft || $this->can_delete($post, $last_id, false))
			{
				$row['U_DELETE'] = append_sid($url, 'mode=' . ($soft ? 'soft_delete' : 'delete') . '&amp;p=' . (int) $post['post_id']);
			}
		}
		$event['post_row'] = $row;
	}

	public function moderator_author($event): void
	{
		$post = $this->post((int) ($event['post_id'] ?? $event['post_info']['post_id'] ?? 0));
		if (empty($post['anonymous_user_id']) || !$this->auth->acl_get('m_', (int) $post['forum_id']))
		{
			return;
		}
		$result = $this->db->sql_query('SELECT username FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int) $post['anonymous_user_id']);
		$name = $this->db->sql_fetchfield('username');
		$this->db->sql_freeresult($result);
		$this->template->assign_var('ANONPOST_REAL_AUTHOR', $name ?: '#' . (int) $post['anonymous_user_id']);
	}
}
