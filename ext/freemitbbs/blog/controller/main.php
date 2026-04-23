<?php

namespace freemitbbs\blog\controller;

class main
{
	private const PAGE_SIZE = 20;
	private const TOP_BLOG_SIZE = 5;
	private const EXCERPT_LENGTH = 100;
	private const COMMENT_PAGE_SIZE = 50;
	private const TOP_BLOG_COLLAPSE_ID = 'freemitbbs_blog_top';
	private const BLOG_HEADER_IMAGE_EXTENSIONS = ['gif', 'jpg', 'jpeg', 'png', 'webp'];
	private const BLOG_TITLE_MAX_LENGTH = 80;
	private const BLOG_SUBTITLE_MAX_LENGTH = 140;

	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\content_visibility $content_visibility;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\language\language $language;
	protected \phpbb\pagination $pagination;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;
	protected \phpbb\attachment\manager $attachment_manager;
	protected $toptopics_ranker;
	protected $attachment_storage;
	protected $public_object_store;
	protected $shared_storage_config_provider;
	protected $collapsible_operator;
	protected string $blog_topics_table;
	protected string $root_path;
	protected string $php_ext;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\content_visibility $content_visibility,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\controller\helper $helper,
		\phpbb\language\language $language,
		\phpbb\pagination $pagination,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\attachment\manager $attachment_manager,
		string $table_prefix,
		string $root_path,
		string $php_ext,
		$toptopics_ranker = null,
		$attachment_storage = null,
		$public_object_store = null,
		$shared_storage_config_provider = null,
		$collapsible_operator = null
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->content_visibility = $content_visibility;
		$this->db = $db;
		$this->helper = $helper;
		$this->language = $language;
		$this->pagination = $pagination;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->attachment_manager = $attachment_manager;
		$this->toptopics_ranker = $toptopics_ranker;
		$this->attachment_storage = $attachment_storage;
		$this->public_object_store = $public_object_store;
		$this->shared_storage_config_provider = $shared_storage_config_provider;
		$this->collapsible_operator = $collapsible_operator;
		$this->blog_topics_table = $table_prefix . 'blog_topics';
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public function index()
	{
		$this->boot();

		$forum = $this->get_blog_forum();
		$this->assign_common_vars($forum);

		if (!$forum)
		{
			$this->assign_not_configured();
			return $this->helper->render('@freemitbbs_blog/blog_index.html', $this->language->lang('BLOGS'));
		}

		$forum_id = (int) $forum['forum_id'];
		$this->require_read_blog_forum($forum_id);

		$total = $this->count_blog_topics($forum_id);
		$start = max(0, $this->request->variable('start', 0));
		$start = $this->pagination->validate_start($start, self::PAGE_SIZE, $total);
		$rows = $this->get_blog_topics($forum_id, self::PAGE_SIZE, $start);
		$this->assign_entry_list($rows, false);
		$this->assign_top_blog_list($forum_id);

		$this->pagination->generate_template_pagination(
			$this->helper->route('freemitbbs_blog_index'),
			'pagination',
			'start',
			$total,
			self::PAGE_SIZE,
			$start
		);

		$this->template->assign_vars([
			'BLOG_PAGE_TITLE' => $this->language->lang('BLOGS'),
			'PAGE_NUMBER' => $this->pagination->on_page($total, self::PAGE_SIZE, $start),
			'S_BLOG_INDEX' => true,
		]);

		return $this->helper->render('@freemitbbs_blog/blog_index.html', $this->language->lang('BLOGS'));
	}

	public function user_blog(int $user_id)
	{
		$this->boot();

		$forum = $this->require_blog_forum();
		$forum_id = (int) $forum['forum_id'];
		$this->require_read_blog_forum($forum_id);

		$blog_user = $this->get_user($user_id);
		if (!$blog_user)
		{
			throw new \phpbb\exception\http_exception(404, 'NO_USER');
		}

		$total = $this->count_blog_topics($forum_id, $user_id);
		$start = max(0, $this->request->variable('start', 0));
		$start = $this->pagination->validate_start($start, self::PAGE_SIZE, $total);
		$rows = $this->get_blog_topics($forum_id, self::PAGE_SIZE, $start, $user_id);
		$this->assign_entry_list($rows, false);
		$this->assign_common_vars($forum);

		$this->pagination->generate_template_pagination(
			$this->helper->route('freemitbbs_blog_user', ['user_id' => $user_id]),
			'pagination',
			'start',
			$total,
			self::PAGE_SIZE,
			$start
		);

		$blog_title = $this->blog_user_title($blog_user);
		$blog_subtitle = $this->blog_user_subtitle($blog_user, $user_id);

		$this->template->assign_vars([
			'BLOG_PAGE_TITLE' => $blog_title,
			'BLOG_USER_SUBTITLE' => $blog_subtitle,
			'U_BLOG_HEADER_IMAGE' => $this->get_blog_header_image_url($blog_user),
			'PAGE_NUMBER' => $this->pagination->on_page($total, self::PAGE_SIZE, $start),
			'S_BLOG_USER' => true,
		]);

		return $this->helper->render('@freemitbbs_blog/blog_index.html', $blog_title);
	}

	public function header_image(int $user_id): void
	{
		$this->boot();

		$forum = $this->require_blog_forum();
		$this->require_read_blog_forum((int) $forum['forum_id']);

		$blog_user = $this->get_user($user_id);
		if (!$blog_user)
		{
			throw new \phpbb\exception\http_exception(404, 'ERROR_NO_ATTACHMENT');
		}

		$object_key = trim((string) ($blog_user['user_blog_header_object_key'] ?? ''));
		if ($object_key !== '')
		{
			$public_header_url = $this->get_blog_header_image_url($blog_user);
			if ($public_header_url !== '')
			{
				redirect($public_header_url, false, true);
			}
		}

		$attach_id = (int) ($blog_user['user_blog_header_attachment_id'] ?? 0);
		$attachment = $attach_id > 0 ? $this->get_blog_header_attachment($attach_id, $user_id) : null;
		if (!$attachment)
		{
			throw new \phpbb\exception\http_exception(404, 'ERROR_NO_ATTACHMENT');
		}

		if ($this->attachment_storage && method_exists($this->attachment_storage, 'build_download_url'))
		{
			$url = $this->attachment_storage->build_download_url($attachment, ATTACHMENT_CATEGORY_IMAGE, 'view', false);
			if ($url !== null)
			{
				redirect($url, false, true);
			}
		}

		if (!function_exists('send_file_to_browser'))
		{
			require_once $this->root_path . 'includes/functions_download.' . $this->php_ext;
		}

		$attachment['physical_filename'] = utf8_basename((string) $attachment['physical_filename']);
		send_file_to_browser($attachment, (string) $this->config['upload_path'], ATTACHMENT_CATEGORY_IMAGE);
		file_gc();
	}

	public function entry(int $entry_id)
	{
		$this->boot();

		$forum = $this->require_blog_forum();
		$entry = $this->get_topic_entry((int) $entry_id, (int) $forum['forum_id']);
		if (!$entry || !$this->can_view_topic($entry))
		{
			throw new \phpbb\exception\http_exception(404, 'BLOG_ENTRY_NOT_FOUND');
		}

		$this->increment_blog_topic_views($entry);
		$this->assign_entry($entry);
		$this->assign_common_vars($forum);

		return $this->helper->render('@freemitbbs_blog/blog_entry.html', censor_text($entry['topic_title']));
	}

	public function manage()
	{
		$this->boot();
		$forum = $this->require_blog_permission($this->helper->route('freemitbbs_blog_manage'));

		$user_id = (int) $this->user->data['user_id'];
		$forum_id = (int) $forum['forum_id'];
		$total = $this->count_blog_topics($forum_id, $user_id, true);
		$start = max(0, $this->request->variable('start', 0));
		$start = $this->pagination->validate_start($start, self::PAGE_SIZE, $total);
		$rows = $this->get_blog_topics($forum_id, self::PAGE_SIZE, $start, $user_id, true);
		$this->assign_entry_list($rows, true, $start);
		$this->assign_common_vars($forum);

		$this->pagination->generate_template_pagination(
			$this->helper->route('freemitbbs_blog_manage'),
			'pagination',
			'start',
			$total,
			self::PAGE_SIZE,
			$start
		);

		$this->template->assign_vars([
			'BLOG_PAGE_TITLE' => $this->language->lang('BLOG_MANAGE'),
			'U_BLOG_NEW' => $this->posting_new_url((int) $forum['forum_id']),
			'U_BLOG_PUBLIC' => $this->helper->route('freemitbbs_blog_user', ['user_id' => $user_id]),
			'PAGE_NUMBER' => $this->pagination->on_page($total, self::PAGE_SIZE, $start),
			'S_BLOG_MANAGE' => true,
		]);

		return $this->helper->render('@freemitbbs_blog/blog_manage.html', $this->language->lang('BLOG_MANAGE'));
	}

	public function edit(int $entry_id = 0)
	{
		$this->boot();
		$forum = $this->require_blog_permission($entry_id ? $this->helper->route('freemitbbs_blog_edit', ['entry_id' => $entry_id]) : $this->helper->route('freemitbbs_blog_new'));

		if (!$entry_id)
		{
			redirect($this->posting_new_url((int) $forum['forum_id']));
		}

		$entry = $this->get_topic_entry((int) $entry_id, (int) $forum['forum_id']);
		if (!$entry || !$this->can_edit_topic($entry))
		{
			throw new \phpbb\exception\http_exception(404, 'BLOG_ENTRY_NOT_FOUND');
		}

		redirect($this->posting_edit_url((int) $entry['topic_first_post_id']));
	}

	public function delete(int $entry_id)
	{
		$this->boot();
		$forum = $this->require_blog_permission($this->helper->route('freemitbbs_blog_manage'));

		$entry = $this->get_topic_entry((int) $entry_id, (int) $forum['forum_id']);
		if (!$entry || !$this->can_edit_topic($entry))
		{
			throw new \phpbb\exception\http_exception(404, 'BLOG_ENTRY_NOT_FOUND');
		}

		redirect($this->posting_delete_url((int) $entry['topic_first_post_id']));
	}

	public function toggle(int $entry_id)
	{
		$this->boot();
		$forum = $this->require_blog_permission($this->helper->route('freemitbbs_blog_manage'));
		$hash = $this->request->variable('hash', '');
		$return = $this->request->variable('return', '');
		$start = max(0, $this->request->variable('start', 0));

		if (!check_link_hash($hash, 'freemitbbs_blog_toggle_' . $entry_id))
		{
			throw new \phpbb\exception\http_exception(403, 'FORM_INVALID');
		}

		$entry = $this->get_topic_entry((int) $entry_id, (int) $forum['forum_id']);
		if (!$entry || !$this->can_edit_topic($entry))
		{
			throw new \phpbb\exception\http_exception(404, 'BLOG_ENTRY_NOT_FOUND');
		}

		$topic_id = (int) $entry['topic_id'];
		$forum_id = (int) $entry['forum_id'];
		$user_id = (int) $this->user->data['user_id'];
		$make_draft = empty($entry['is_draft']);

		if ($make_draft)
		{
			$this->set_blog_topic_draft($topic_id, (int) $entry['topic_poster'], 1);
			$this->content_visibility->set_topic_visibility(ITEM_DELETED, $topic_id, $forum_id, $user_id, time(), '');
		}
		else
		{
			$this->content_visibility->set_topic_visibility(ITEM_APPROVED, $topic_id, $forum_id, $user_id, time(), '');
			$this->set_blog_topic_draft($topic_id, (int) $entry['topic_poster'], 0);
		}

		redirect($return === 'entry'
			? $this->helper->route('freemitbbs_blog_entry', ['entry_id' => $topic_id])
			: $this->helper->route('freemitbbs_blog_manage', $start > 0 ? ['start' => $start] : [])
		);
	}

	public function send_post(int $post_id)
	{
		$this->boot();
		$forum = $this->require_blog_permission($this->helper->route('freemitbbs_blog_manage'));
		$blog_forum_id = (int) $forum['forum_id'];
		$hash = $this->request->variable('hash', '');

		if (!check_link_hash($hash, 'freemitbbs_blog_send_' . $post_id))
		{
			throw new \phpbb\exception\http_exception(403, 'FORM_INVALID');
		}

		$post = $this->get_source_post($post_id);
		if (!$post || (int) $post['forum_id'] === $blog_forum_id || !$this->can_copy_post($post))
		{
			throw new \phpbb\exception\http_exception(403, 'BLOG_CANNOT_SEND_POST');
		}

		if ($this->request->is_set_post('cancel'))
		{
			redirect($this->source_post_url($post));
		}

		if (!$this->request->is_set_post('send') && !$this->request->is_set_post('confirm'))
		{
			throw new \phpbb\exception\http_exception(403, 'FORM_INVALID');
		}

		$existing = $this->get_existing_source_topic((int) $this->user->data['user_id'], $post_id, $blog_forum_id);
		if (!confirm_box(true))
		{
			confirm_box(
				false,
				$existing ? 'BLOG_SEND_EXISTING' : 'BLOG_SEND_POST',
				build_hidden_fields([
					'hash' => $hash,
					'send' => 1,
				]),
				'confirm_body.html',
				$this->helper->route('freemitbbs_blog_send_post', ['post_id' => $post_id])
			);

			throw new \phpbb\exception\http_exception(403, 'FORM_INVALID');
		}

		if ($existing)
		{
			redirect($this->posting_edit_url((int) $existing['topic_first_post_id']));
		}

		$topic = $this->create_topic_from_post($post, $forum);

		redirect($this->posting_edit_url((int) $topic['post_id']));
	}

	public function ucp(string $u_action): void
	{
		$this->boot();
		$forum = $this->require_blog_permission($u_action);

		$user_id = (int) $this->user->data['user_id'];
		add_form_key('freemitbbs_blog_ucp');
		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key('freemitbbs_blog_ucp'))
			{
				trigger_error($this->language->lang('FORM_INVALID'));
			}

			$comments_enabled = $this->request->variable('blog_comments_enabled', 0) ? 1 : 0;
			$blog_title = $this->clean_blog_header_text('blog_title', self::BLOG_TITLE_MAX_LENGTH);
			$blog_subtitle = $this->clean_blog_header_text('blog_subtitle', self::BLOG_SUBTITLE_MAX_LENGTH);
			$old_header_attachment_id = (int) ($this->user->data['user_blog_header_attachment_id'] ?? 0);
			$old_header_object_key = trim((string) ($this->user->data['user_blog_header_object_key'] ?? ''));
			$new_header_attachment_id = $old_header_attachment_id;
			$new_header_object_key = $old_header_object_key;
			$delete_header_image = (bool) $this->request->variable('blog_header_image_delete', 0);

			if ($this->has_uploaded_file('blog_header_image'))
			{
				$upload_result = $this->upload_blog_header_image((int) $forum['forum_id'], $user_id);
				if (!empty($upload_result['error']))
				{
					$this->trigger_ucp_error($upload_result['error'], $u_action);
				}

				$new_header_attachment_id = (int) $upload_result['attach_id'];
				$new_header_object_key = trim((string) ($upload_result['object_key'] ?? ''));
			}
			else if ($delete_header_image)
			{
				$new_header_attachment_id = 0;
				$new_header_object_key = '';
			}

			$sql_ary = [
				'user_blog_comments_enabled' => $comments_enabled,
				'user_blog_title' => $blog_title,
				'user_blog_subtitle' => $blog_subtitle,
				'user_blog_header_attachment_id' => $new_header_attachment_id,
				'user_blog_header_object_key' => $new_header_object_key,
			];
			$sql = 'UPDATE ' . USERS_TABLE . '
				SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
				WHERE user_id = ' . $user_id;
			$this->db->sql_query($sql);
			$this->user->data['user_blog_comments_enabled'] = $comments_enabled;
			$this->user->data['user_blog_title'] = $blog_title;
			$this->user->data['user_blog_subtitle'] = $blog_subtitle;
			$this->user->data['user_blog_header_attachment_id'] = $new_header_attachment_id;
			$this->user->data['user_blog_header_object_key'] = $new_header_object_key;

			if ($old_header_attachment_id > 0 && $old_header_attachment_id !== $new_header_attachment_id)
			{
				$this->delete_blog_header_attachment($old_header_attachment_id, $user_id);
			}

			if ($old_header_object_key !== '' && $old_header_object_key !== $new_header_object_key)
			{
				$this->delete_blog_header_media_object($old_header_object_key);
			}

			meta_refresh(3, $u_action);
			trigger_error($this->language->lang('BLOG_SETTINGS_SAVED') . '<br /><br />' . sprintf(
				$this->language->lang('RETURN_UCP'),
				'<a href="' . $u_action . '">',
				'</a>'
			));
		}

		$this->assign_common_vars($forum);

		$this->template->assign_vars([
			'U_ACTION' => $u_action,
			'U_BLOG_NEW' => $this->posting_new_url((int) $forum['forum_id']),
			'U_BLOG_MANAGE' => $this->helper->route('freemitbbs_blog_manage'),
			'U_BLOG_PUBLIC' => $this->helper->route('freemitbbs_blog_user', ['user_id' => $user_id]),
			'U_BLOG_HEADER_IMAGE' => $this->get_blog_header_image_url($this->user->data),
			'BLOG_CUSTOM_TITLE' => (string) ($this->user->data['user_blog_title'] ?? ''),
			'BLOG_CUSTOM_SUBTITLE' => (string) ($this->user->data['user_blog_subtitle'] ?? ''),
			'BLOG_TITLE_MAX_LENGTH' => self::BLOG_TITLE_MAX_LENGTH,
			'BLOG_SUBTITLE_MAX_LENGTH' => self::BLOG_SUBTITLE_MAX_LENGTH,
			'S_BLOG_COMMENTS_ENABLED' => !isset($this->user->data['user_blog_comments_enabled']) || (bool) $this->user->data['user_blog_comments_enabled'],
			'S_BLOG_HAS_HEADER_IMAGE' => $this->has_blog_header_image($this->user->data),
		]);
	}

	protected function clean_blog_header_text(string $field, int $max_length): string
	{
		$value = (string) $this->request->variable($field, '', true);
		$value = trim((string) preg_replace('#\s+#u', ' ', str_replace("\n", ' ', $value)));

		return truncate_string($value, $max_length, 255, false);
	}

	protected function has_uploaded_file(string $field): bool
	{
		$file = $this->request->file($field);

		return !empty($file)
			&& !empty($file['name'])
			&& (string) $file['name'] !== 'none'
			&& (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_NO_FILE);
	}

	protected function upload_blog_header_image(int $forum_id, int $user_id): array
	{
		$filedata = $this->attachment_manager->upload('blog_header_image', $forum_id, false, '', false);
		$errors = $filedata['error'] ?? [];
		if (empty($filedata['post_attach']) || !empty($errors))
		{
			return [
				'attach_id' => 0,
				'object_key' => '',
				'error' => $errors ?: [$this->language->lang('NO_UPLOAD_FORM_FOUND')],
			];
		}

		if (!$this->is_blog_header_image($filedata))
		{
			$this->cleanup_copied_attachment_files([(string) $filedata['physical_filename']]);

			return [
				'attach_id' => 0,
				'object_key' => '',
				'error' => [$this->language->lang('BLOG_HEADER_IMAGE_INVALID')],
			];
		}

		if ($this->has_blog_header_media_storage())
		{
			try
			{
				$object_key = $this->upload_blog_header_media_object($filedata);
			}
			catch (\RuntimeException $e)
			{
				$this->cleanup_copied_attachment_files([(string) $filedata['physical_filename']]);

				return [
					'attach_id' => 0,
					'object_key' => '',
					'error' => [$e->getMessage()],
				];
			}

			$this->cleanup_copied_attachment_files([(string) $filedata['physical_filename']]);

			return [
				'attach_id' => 0,
				'object_key' => $object_key,
				'error' => [],
			];
		}

		$sql_ary = [
			'post_msg_id' => 0,
			'topic_id' => 0,
			'in_message' => 0,
			'poster_id' => $user_id,
			'is_orphan' => 0,
			'physical_filename' => (string) $filedata['physical_filename'],
			'real_filename' => utf8_basename((string) $filedata['real_filename']),
			'attach_comment' => '',
			'extension' => strtolower((string) $filedata['extension']),
			'mimetype' => (string) $filedata['mimetype'],
			'filesize' => (int) $filedata['filesize'],
			'filetime' => (int) $filedata['filetime'],
			'thumbnail' => (int) $filedata['thumbnail'],
		];

		$this->db->sql_query('INSERT INTO ' . ATTACHMENTS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
		$attach_id = (int) $this->db->sql_nextid();
		$this->config->increment('upload_dir_size', (int) $filedata['filesize'], false);
		$this->config->increment('num_files', 1, false);

		if ($this->attachment_storage && method_exists($this->attachment_storage, 'upload_attachments'))
		{
			$this->attachment_storage->upload_attachments([$attach_id]);
		}

		return [
			'attach_id' => $attach_id,
			'object_key' => '',
			'error' => [],
		];
	}

	protected function upload_blog_header_media_object(array $filedata): string
	{
		$storage_config = $this->get_blog_header_storage_config();
		if ($storage_config === null
			|| !$this->public_object_store
			|| !method_exists($this->public_object_store, 'put_object'))
		{
			throw new \RuntimeException($this->language->lang('BLOG_HEADER_IMAGE_STORAGE_UNAVAILABLE'));
		}

		$physical_filename = utf8_basename((string) ($filedata['physical_filename'] ?? ''));
		$source_path = $this->attachment_path($physical_filename, false);
		if (!is_file($source_path) || !is_readable($source_path))
		{
			throw new \RuntimeException($this->language->lang('BLOG_HEADER_IMAGE_STORAGE_UNAVAILABLE'));
		}

		$object_key = $this->build_blog_header_object_key((string) ($filedata['extension'] ?? ''));
		$this->public_object_store->put_object(
			$storage_config,
			$source_path,
			(int) ($filedata['filesize'] ?? 0),
			(string) ($filedata['mimetype'] ?? 'application/octet-stream'),
			$object_key
		);

		return $object_key;
	}

	protected function get_blog_header_attachment(int $attach_id, int $user_id): ?array
	{
		$sql = 'SELECT *
			FROM ' . ATTACHMENTS_TABLE . '
			WHERE attach_id = ' . $attach_id . '
				AND poster_id = ' . $user_id . '
				AND is_orphan = 0';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result) ?: null;
		$this->db->sql_freeresult($result);

		return $row && $this->is_blog_header_image($row) ? $row : null;
	}

	protected function delete_blog_header_attachment(int $attach_id, int $user_id): void
	{
		if ($this->get_blog_header_attachment($attach_id, $user_id))
		{
			$this->attachment_manager->delete('attach', [$attach_id], false);
		}
	}

	protected function delete_blog_header_media_object(string $object_key): void
	{
		$object_key = trim($object_key, '/');
		$storage_config = $this->get_blog_header_storage_config();
		if ($object_key === ''
			|| $storage_config === null
			|| !$this->public_object_store
			|| !method_exists($this->public_object_store, 'delete_object'))
		{
			return;
		}

		try
		{
			$this->public_object_store->delete_object($storage_config, $object_key);
		}
		catch (\Exception $e)
		{
		}
	}

	protected function is_blog_header_image(array $filedata): bool
	{
		$extension = strtolower((string) ($filedata['extension'] ?? ''));
		$mimetype = strtolower((string) ($filedata['mimetype'] ?? ''));

		return in_array($extension, self::BLOG_HEADER_IMAGE_EXTENSIONS, true)
			&& str_starts_with($mimetype, 'image/');
	}

	protected function has_blog_header_media_storage(): bool
	{
		return $this->get_blog_header_storage_config() !== null;
	}

	protected function get_blog_header_storage_config(): ?array
	{
		if (!$this->public_object_store)
		{
			return null;
		}

		$shared_config = [];
		if ($this->shared_storage_config_provider
			&& method_exists($this->shared_storage_config_provider, 'has_shared_storage_config')
			&& $this->shared_storage_config_provider->has_shared_storage_config()
			&& method_exists($this->shared_storage_config_provider, 'get_shared_storage_config'))
		{
			$shared_config = (array) $this->shared_storage_config_provider->get_shared_storage_config();
		}

		$config = [
			'endpoint' => trim((string) (($this->config['videoupload_s3_endpoint'] ?? '') ?: ($shared_config['endpoint'] ?? ''))),
			'region' => trim((string) (($this->config['videoupload_s3_region'] ?? '') ?: ($shared_config['region'] ?? 'us-east-1'))),
			'bucket' => trim((string) (($this->config['videoupload_s3_bucket'] ?? '') ?: ($shared_config['bucket'] ?? ''))),
			'access_key' => trim((string) (($this->config['videoupload_s3_access_key'] ?? '') ?: ($shared_config['access_key'] ?? ''))),
			'secret_key' => trim((string) (($this->config['videoupload_s3_secret_key'] ?? '') ?: ($shared_config['secret_key'] ?? ($this->config['s3storage_secret_key'] ?? '')))),
			'public_base_url' => rtrim(trim((string) (($this->config['videoupload_s3_public_base_url'] ?? '') ?: ($shared_config['public_base_url'] ?? ''))), '/'),
			'use_path_style' => (int) ($this->config['videoupload_s3_use_path_style'] ?? ($shared_config['use_path_style'] ?? 0)),
			'acl' => trim((string) ($this->config['videoupload_s3_acl'] ?? 'public-read')),
		];

		return $this->is_complete_blog_header_storage_config($config) ? $config : null;
	}

	protected function is_complete_blog_header_storage_config(array $config): bool
	{
		return trim((string) ($config['endpoint'] ?? '')) !== ''
			&& trim((string) ($config['region'] ?? '')) !== ''
			&& trim((string) ($config['bucket'] ?? '')) !== ''
			&& trim((string) ($config['access_key'] ?? '')) !== ''
			&& trim((string) ($config['secret_key'] ?? '')) !== '';
	}

	protected function build_blog_header_object_key(string $extension): string
	{
		$extension = strtolower(trim($extension));
		if ($extension === '' || !in_array($extension, self::BLOG_HEADER_IMAGE_EXTENSIONS, true))
		{
			$extension = 'png';
		}

		$prefix = trim((string) ($this->config['videoupload_s3_path_prefix'] ?? 'videos'), " \t\n\r\0\x0B/");
		$path = 'blog-headers/' . gmdate('Y/m') . '/' . $this->random_hex(16) . '.' . $extension;

		return ($prefix !== '') ? ($prefix . '/' . $path) : $path;
	}

	protected function random_hex(int $bytes): string
	{
		try
		{
			return bin2hex(random_bytes($bytes));
		}
		catch (\Exception $e)
		{
			return sha1(uniqid((string) mt_rand(), true));
		}
	}

	protected function get_blog_header_image_url(array $blog_user): string
	{
		$object_key = trim((string) ($blog_user['user_blog_header_object_key'] ?? ''));
		if ($object_key !== '')
		{
			$storage_config = $this->get_blog_header_storage_config();
			if ($storage_config !== null
				&& $this->public_object_store
				&& method_exists($this->public_object_store, 'build_public_url'))
			{
				try
				{
					return $this->public_object_store->build_public_url($storage_config, $object_key);
				}
				catch (\Exception $e)
				{
				}
			}
		}

		return !empty($blog_user['user_blog_header_attachment_id'])
			? $this->helper->route('freemitbbs_blog_header_image', ['user_id' => (int) ($blog_user['user_id'] ?? 0)])
			: '';
	}

	protected function has_blog_header_image(array $blog_user): bool
	{
		return trim((string) ($blog_user['user_blog_header_object_key'] ?? '')) !== ''
			|| !empty($blog_user['user_blog_header_attachment_id']);
	}

	protected function trigger_ucp_error(array $errors, string $u_action): void
	{
		$errors = array_filter(array_map('strval', $errors));
		$message = implode('<br />', array_map(static function (string $error): string {
			return htmlspecialchars($error, ENT_COMPAT);
		}, $errors));

		trigger_error($message . '<br /><br />' . sprintf(
			$this->language->lang('RETURN_UCP'),
			'<a href="' . $u_action . '">',
			'</a>'
		), E_USER_WARNING);
	}

	protected function boot(): void
	{
		$this->language->add_lang('common', 'freemitbbs/blog');
		$this->language->add_lang('posting');
		if (!function_exists('generate_text_for_display'))
		{
			require_once $this->root_path . 'includes/functions_content.' . $this->php_ext;
		}
	}

	protected function require_blog_permission(string $redirect_url): array
	{
		if ((int) $this->user->data['user_id'] === ANONYMOUS)
		{
			login_box($redirect_url);
		}

		if (!$this->auth->acl_get('u_blog_create'))
		{
			throw new \phpbb\exception\http_exception(403, 'NO_AUTH_OPERATION');
		}

		$forum = $this->require_blog_forum();
		if (!$this->auth->acl_get('f_post', (int) $forum['forum_id']))
		{
			throw new \phpbb\exception\http_exception(403, 'NO_AUTH_OPERATION');
		}

		return $forum;
	}

	protected function require_blog_forum(): array
	{
		$forum = $this->get_blog_forum();
		if (!$forum)
		{
			throw new \phpbb\exception\http_exception(404, 'BLOG_FORUM_NOT_CONFIGURED');
		}

		return $forum;
	}

	protected function require_read_blog_forum(int $forum_id): void
	{
		if (!$this->auth->acl_get('f_read', $forum_id))
		{
			throw new \phpbb\exception\http_exception(403, 'NO_AUTH_OPERATION');
		}
	}

	protected function get_blog_forum(): ?array
	{
		$forum_id = (int) ($this->config['freemitbbs_blog_forum_id'] ?? 0);
		if ($forum_id <= 0)
		{
			return null;
		}

		$sql = 'SELECT *
			FROM ' . FORUMS_TABLE . '
			WHERE forum_id = ' . $forum_id . '
				AND forum_type = ' . FORUM_POST;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result) ?: null;
		$this->db->sql_freeresult($result);

		return $row;
	}

	protected function count_blog_topics(int $forum_id, int $user_id = 0, bool $manage = false): int
	{
		$sql = 'SELECT COUNT(*) AS topic_count
			FROM ' . TOPICS_TABLE . ' t
			LEFT JOIN ' . $this->blog_topics_table . ' bt
				ON bt.topic_id = t.topic_id
			WHERE t.forum_id = ' . (int) $forum_id . '
				AND t.topic_moved_id = 0' .
				(!$manage ? ' AND t.topic_visibility = ' . ITEM_APPROVED . ' AND (bt.is_draft IS NULL OR bt.is_draft = 0)' : '') .
				($user_id ? ' AND t.topic_poster = ' . (int) $user_id : '');
		$result = $this->db->sql_query($sql);
		$count = (int) $this->db->sql_fetchfield('topic_count');
		$this->db->sql_freeresult($result);

		return $count;
	}

	protected function public_blog_topic_sql(int $forum_id): string
	{
		return 't.forum_id = ' . (int) $forum_id . '
				AND t.topic_moved_id = 0
				AND t.topic_visibility = ' . ITEM_APPROVED . '
				AND (bt.is_draft IS NULL OR bt.is_draft = 0)';
	}

	protected function get_blog_topics(int $forum_id, int $limit, int $start, int $user_id = 0, bool $manage = false): array
	{
		$sql = 'SELECT t.*, p.post_text, p.post_attachment, p.bbcode_uid, p.bbcode_bitfield, p.enable_bbcode, p.enable_smilies,
				p.enable_magic_url, u.username, u.user_colour, bt.source_post_id, bt.source_topic_id, bt.is_draft
			FROM ' . TOPICS_TABLE . ' t
			INNER JOIN ' . POSTS_TABLE . ' p
				ON p.post_id = t.topic_first_post_id
			INNER JOIN ' . USERS_TABLE . ' u
				ON u.user_id = t.topic_poster
			LEFT JOIN ' . $this->blog_topics_table . ' bt
				ON bt.topic_id = t.topic_id
			WHERE t.forum_id = ' . (int) $forum_id . '
				AND t.topic_moved_id = 0' .
				(!$manage ? ' AND t.topic_visibility = ' . ITEM_APPROVED . ' AND (bt.is_draft IS NULL OR bt.is_draft = 0)' : '') .
				($user_id ? ' AND t.topic_poster = ' . (int) $user_id : '') . '
			ORDER BY t.topic_time DESC, t.topic_id DESC';

		return $this->fetch_rows($sql, $limit, $start);
	}

	protected function get_topic_entry(int $topic_id, int $forum_id): ?array
	{
		$sql = 'SELECT t.*, p.post_text, p.post_attachment, p.bbcode_uid, p.bbcode_bitfield, p.enable_bbcode, p.enable_smilies,
				p.enable_magic_url, u.username, u.user_colour, u.user_blog_comments_enabled,
				bt.source_post_id, bt.source_topic_id, bt.is_draft
			FROM ' . TOPICS_TABLE . ' t
			INNER JOIN ' . POSTS_TABLE . ' p
				ON p.post_id = t.topic_first_post_id
			INNER JOIN ' . USERS_TABLE . ' u
				ON u.user_id = t.topic_poster
			LEFT JOIN ' . $this->blog_topics_table . ' bt
				ON bt.topic_id = t.topic_id
			WHERE t.topic_id = ' . (int) $topic_id . '
				AND t.forum_id = ' . (int) $forum_id . '
				AND t.topic_moved_id = 0';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result) ?: null;
		$this->db->sql_freeresult($result);

		return $row;
	}

	protected function get_user(int $user_id): ?array
	{
		$sql = 'SELECT user_id, username, user_colour, user_blog_header_attachment_id, user_blog_header_object_key, user_blog_title, user_blog_subtitle
			FROM ' . USERS_TABLE . '
			WHERE user_id = ' . (int) $user_id . '
				AND user_id <> ' . ANONYMOUS;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result) ?: null;
		$this->db->sql_freeresult($result);

		return $row;
	}

	protected function blog_user_title(array $blog_user): string
	{
		$title = trim((string) ($blog_user['user_blog_title'] ?? ''));

		return $title !== '' ? $title : $this->language->lang('BLOG_USER_TITLE', $blog_user['username']);
	}

	protected function blog_user_subtitle(array $blog_user, int $user_id): string
	{
		$subtitle = trim((string) ($blog_user['user_blog_subtitle'] ?? ''));

		return $subtitle !== '' ? $subtitle : get_username_string('full', $user_id, $blog_user['username'], $blog_user['user_colour']);
	}

	protected function get_source_post(int $post_id): ?array
	{
		$sql = 'SELECT p.post_id, p.topic_id, p.forum_id, p.poster_id, p.post_subject, p.post_text, p.post_attachment,
				p.bbcode_uid, p.bbcode_bitfield, p.enable_bbcode, p.enable_smilies, p.enable_magic_url,
				p.post_visibility, t.topic_title, t.topic_visibility, t.topic_poster
			FROM ' . POSTS_TABLE . ' p
			INNER JOIN ' . TOPICS_TABLE . ' t
				ON t.topic_id = p.topic_id
			WHERE p.post_id = ' . (int) $post_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result) ?: null;
		$this->db->sql_freeresult($result);

		return $row;
	}

	protected function can_copy_post(array $post): bool
	{
		$forum_id = (int) $post['forum_id'];
		return (int) $post['poster_id'] === (int) $this->user->data['user_id']
			&& $this->auth->acl_get('f_read', $forum_id)
			&& $this->content_visibility->is_visible('post', $forum_id, $post)
			&& $this->content_visibility->is_visible('topic', $forum_id, $post);
	}

	protected function get_existing_source_topic(int $user_id, int $post_id, int $blog_forum_id): ?array
	{
		$sql = 'SELECT t.topic_id, t.topic_first_post_id
			FROM ' . $this->blog_topics_table . ' bt
			INNER JOIN ' . TOPICS_TABLE . ' t
				ON t.topic_id = bt.topic_id
			WHERE bt.user_id = ' . (int) $user_id . '
				AND bt.source_post_id = ' . (int) $post_id . '
				AND t.forum_id = ' . (int) $blog_forum_id . '
			ORDER BY bt.topic_id ASC';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result) ?: null;
		$this->db->sql_freeresult($result);

		return $row;
	}

	protected function create_topic_from_post(array $post, array $forum): array
	{
		$title = trim((string) ($post['post_subject'] ?: $post['topic_title']));
		$title = $title !== '' ? $title : $this->language->lang('BLOG_UNTITLED');
		$title = truncate_string($title, isset($this->config['max_topic_title_chars']) && (int) $this->config['max_topic_title_chars'] > 0 ? (int) $this->config['max_topic_title_chars'] : 50);
		$message = (string) $post['post_text'];
		$forum_id = (int) $forum['forum_id'];
		$user_id = (int) $this->user->data['user_id'];
		$current_time = time();

		$copied_attachments = [];
		$this->db->sql_transaction('begin');

		try
		{
			$topic_sql = [
				'topic_poster' => $user_id,
				'topic_time' => $current_time,
				'topic_last_view_time' => $current_time,
				'forum_id' => $forum_id,
				'icon_id' => 0,
				'topic_posts_approved' => 0,
				'topic_posts_softdeleted' => 0,
				'topic_posts_unapproved' => 1,
				'topic_visibility' => ITEM_UNAPPROVED,
				'topic_delete_user' => $user_id,
				'topic_title' => $title,
				'topic_first_poster_name' => (string) $this->user->data['username'],
				'topic_first_poster_colour' => (string) $this->user->data['user_colour'],
				'topic_type' => POST_NORMAL,
				'topic_time_limit' => 0,
				'topic_attachment' => 0,
				'topic_status' => ITEM_UNLOCKED,
			];

			$this->db->sql_query('INSERT INTO ' . TOPICS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $topic_sql));
			$topic_id = (int) $this->db->sql_nextid();

			$post_sql = [
				'forum_id' => $forum_id,
				'poster_id' => $user_id,
				'icon_id' => 0,
				'poster_ip' => $this->user->ip,
				'post_time' => $current_time,
				'post_visibility' => ITEM_UNAPPROVED,
				'enable_bbcode' => (int) $post['enable_bbcode'],
				'enable_smilies' => (int) $post['enable_smilies'],
				'enable_magic_url' => (int) $post['enable_magic_url'],
				'enable_sig' => 0,
				'post_username' => '',
				'post_subject' => $title,
				'post_text' => $message,
				'post_checksum' => md5($message),
				'post_attachment' => 0,
				'bbcode_bitfield' => (string) $post['bbcode_bitfield'],
				'bbcode_uid' => (string) $post['bbcode_uid'],
				'post_postcount' => $this->auth->acl_get('f_postcount', $forum_id) ? 1 : 0,
				'post_edit_locked' => 0,
				'topic_id' => $topic_id,
			];

			$this->db->sql_query('INSERT INTO ' . POSTS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $post_sql));
			$post_id = (int) $this->db->sql_nextid();

			$topic_update = [
				'topic_first_post_id' => $post_id,
				'topic_last_post_id' => $post_id,
				'topic_last_post_time' => $current_time,
				'topic_last_poster_id' => $user_id,
				'topic_last_poster_name' => (string) $this->user->data['username'],
				'topic_last_poster_colour' => (string) $this->user->data['user_colour'],
				'topic_last_post_subject' => $title,
			];
			$this->db->sql_query('UPDATE ' . TOPICS_TABLE . '
				SET ' . $this->db->sql_build_array('UPDATE', $topic_update) . '
				WHERE topic_id = ' . $topic_id);

			$this->db->sql_query('UPDATE ' . FORUMS_TABLE . '
				SET forum_topics_unapproved = forum_topics_unapproved + 1,
					forum_posts_unapproved = forum_posts_unapproved + 1
				WHERE forum_id = ' . $forum_id);

			$this->db->sql_query('UPDATE ' . USERS_TABLE . '
				SET user_lastpost_time = ' . $current_time . '
				WHERE user_id = ' . $user_id);

			$copied_attachments = $this->copy_post_attachments($post, $topic_id, $post_id);
			$this->insert_source_topic($topic_id, $user_id, $post);

			$this->db->sql_transaction('commit');
		}
		catch (\Throwable $e)
		{
			$this->db->sql_transaction('rollback');
			$this->cleanup_copied_attachment_files($copied_attachments);
			throw $e;
		}

		return [
			'topic_id' => $topic_id,
			'post_id' => $post_id,
		];
	}

	protected function copy_post_attachments(array $source_post, int $topic_id, int $post_id): array
	{
		$sql = 'SELECT *
			FROM ' . ATTACHMENTS_TABLE . '
			WHERE post_msg_id = ' . (int) $source_post['post_id'] . '
				AND topic_id = ' . (int) $source_post['topic_id'] . '
				AND in_message = 0
			ORDER BY attach_id ASC';
		$result = $this->db->sql_query($sql);

		$attachment_rows = [];
		$copied_physical_filenames = [];
		$space_taken = 0;
		$result_freed = false;
		try
		{
			while ($row = $this->db->sql_fetchrow($result))
			{
				$target_physical_filename = $this->new_attachment_physical_filename();
				$copied_physical_filenames[] = $target_physical_filename;
				$s3_object_keys = $this->copy_attachment_storage($row, $target_physical_filename);

				unset($row['attach_id']);
				$row['post_msg_id'] = $post_id;
				$row['topic_id'] = $topic_id;
				$row['in_message'] = 0;
				$row['is_orphan'] = 0;
				$row['poster_id'] = (int) $this->user->data['user_id'];
				$row['physical_filename'] = $target_physical_filename;
				$row['real_filename'] = utf8_basename((string) $row['real_filename']);
				$row['download_count'] = 0;
				if (array_key_exists('s3_object_key', $row))
				{
					$row['s3_object_key'] = $s3_object_keys['s3_object_key'] ?? '';
				}
				if (array_key_exists('s3_thumb_object_key', $row))
				{
					$row['s3_thumb_object_key'] = $s3_object_keys['s3_thumb_object_key'] ?? '';
				}
				$attachment_rows[] = $row;
				$space_taken += (int) ($row['filesize'] ?? 0);
			}
			$this->db->sql_freeresult($result);
			$result_freed = true;

			if (empty($attachment_rows))
			{
				return [];
			}

			$this->db->sql_multi_insert(ATTACHMENTS_TABLE, $attachment_rows);
			$this->config->increment('upload_dir_size', $space_taken, false);
			$this->config->increment('num_files', count($attachment_rows), false);

			$this->db->sql_query('UPDATE ' . POSTS_TABLE . '
				SET post_attachment = 1
				WHERE post_id = ' . $post_id);
			$this->db->sql_query('UPDATE ' . TOPICS_TABLE . '
				SET topic_attachment = 1
				WHERE topic_id = ' . $topic_id);
		}
		catch (\Throwable $e)
		{
			if (!$result_freed)
			{
				$this->db->sql_freeresult($result);
			}
			$this->cleanup_copied_attachment_files($copied_physical_filenames);
			throw $e;
		}

		return $copied_physical_filenames;
	}

	protected function copy_attachment_storage(array $source_attachment, string $target_physical_filename): array
	{
		$source_physical_filename = utf8_basename((string) $source_attachment['physical_filename']);
		$source_file_is_local = $this->attachment_local_file_is_usable($source_physical_filename, false);
		$source_thumb_is_local = !$source_attachment['thumbnail'] || $this->attachment_local_file_is_usable($source_physical_filename, true);
		$needs_remote_copy = $this->attachment_has_s3_object($source_attachment) || !$source_file_is_local || !$source_thumb_is_local;

		if ($needs_remote_copy)
		{
			if (!$this->attachment_storage || !method_exists($this->attachment_storage, 'clone_attachment_storage'))
			{
				throw new \RuntimeException('Unable to copy remote attachment storage for blog post.');
			}

			$s3_object_keys = $this->attachment_storage->clone_attachment_storage($source_attachment, $target_physical_filename);
			if (empty($s3_object_keys['s3_object_key']) && !$source_file_is_local)
			{
				throw new \RuntimeException('Unable to copy remote attachment object for blog post.');
			}

			if (!empty($s3_object_keys))
			{
				return $s3_object_keys;
			}
		}

		$this->copy_local_attachment_file($source_physical_filename, $target_physical_filename, false);
		if ((int) $source_attachment['thumbnail'])
		{
			$this->copy_local_attachment_file($source_physical_filename, $target_physical_filename, true);
		}

		return [];
	}

	protected function copy_local_attachment_file(string $source_physical_filename, string $target_physical_filename, bool $thumbnail): void
	{
		$source_path = $this->attachment_path($source_physical_filename, $thumbnail);
		$target_path = $this->attachment_path($target_physical_filename, $thumbnail);

		if (!is_file($source_path) || !is_readable($source_path) || @filesize($source_path) <= 0)
		{
			throw new \RuntimeException('Unable to copy attachment file for blog post.');
		}

		if (!@copy($source_path, $target_path))
		{
			throw new \RuntimeException('Unable to write copied attachment file for blog post.');
		}
	}

	protected function cleanup_copied_attachment_files(array $physical_filenames): void
	{
		foreach (array_unique($physical_filenames) as $physical_filename)
		{
			$physical_filename = utf8_basename((string) $physical_filename);
			if ($physical_filename === '')
			{
				continue;
			}

			@unlink($this->attachment_path($physical_filename, false));
			@unlink($this->attachment_path($physical_filename, true));
		}
	}

	protected function attachment_path(string $physical_filename, bool $thumbnail): string
	{
		$filename = $thumbnail ? ('thumb_' . $physical_filename) : $physical_filename;

		return $this->root_path . $this->config['upload_path'] . '/' . utf8_basename($filename);
	}

	protected function attachment_local_file_is_usable(string $physical_filename, bool $thumbnail): bool
	{
		$path = $this->attachment_path($physical_filename, $thumbnail);

		return is_file($path) && is_readable($path) && @filesize($path) > 0;
	}

	protected function attachment_has_s3_object(array $attachment): bool
	{
		return trim((string) ($attachment['s3_object_key'] ?? '')) !== ''
			|| trim((string) ($attachment['s3_thumb_object_key'] ?? '')) !== '';
	}

	protected function new_attachment_physical_filename(): string
	{
		$user_id = (int) $this->user->data['user_id'];
		for ($i = 0; $i < 20; $i++)
		{
			$filename = $user_id . '_' . md5(unique_id() . ':' . $i);
			if (!$this->attachment_physical_filename_exists($filename))
			{
				return $filename;
			}
		}

		throw new \RuntimeException('Unable to allocate attachment filename for blog post.');
	}

	protected function attachment_physical_filename_exists(string $physical_filename): bool
	{
		if (file_exists($this->attachment_path($physical_filename, false)) || file_exists($this->attachment_path($physical_filename, true)))
		{
			return true;
		}

		$sql = 'SELECT attach_id
			FROM ' . ATTACHMENTS_TABLE . "
			WHERE physical_filename = '" . $this->db->sql_escape($physical_filename) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$exists = (bool) $this->db->sql_fetchfield('attach_id');
		$this->db->sql_freeresult($result);

		return $exists;
	}

	protected function insert_source_topic(int $topic_id, int $user_id, array $post): void
	{
		$sql_ary = [
			'topic_id' => $topic_id,
			'user_id' => $user_id,
			'source_post_id' => (int) $post['post_id'],
			'source_topic_id' => (int) $post['topic_id'],
			'is_draft' => 1,
			'created_time' => time(),
		];

		$sql = 'INSERT INTO ' . $this->blog_topics_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
		$this->db->sql_query($sql);
	}

	protected function set_blog_topic_draft(int $topic_id, int $user_id, int $is_draft): void
	{
		$sql = 'SELECT topic_id
			FROM ' . $this->blog_topics_table . '
			WHERE topic_id = ' . (int) $topic_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$exists = (bool) $this->db->sql_fetchfield('topic_id');
		$this->db->sql_freeresult($result);

		if ($exists)
		{
			$sql = 'UPDATE ' . $this->blog_topics_table . '
				SET is_draft = ' . (int) $is_draft . '
				WHERE topic_id = ' . (int) $topic_id;
			$this->db->sql_query($sql);
			return;
		}

		$sql_ary = [
			'topic_id' => $topic_id,
			'user_id' => $user_id,
			'source_post_id' => 0,
			'source_topic_id' => 0,
			'is_draft' => (int) $is_draft,
			'created_time' => time(),
		];
		$this->db->sql_query('INSERT INTO ' . $this->blog_topics_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
	}

	protected function can_view_topic(array $entry): bool
	{
		$forum_id = (int) $entry['forum_id'];
		if (!$this->auth->acl_get('f_read', $forum_id))
		{
			return false;
		}

		if (!empty($entry['is_draft']))
		{
			return ((int) $entry['topic_poster'] === (int) $this->user->data['user_id'] && $this->auth->acl_get('u_blog_create'))
				|| $this->auth->acl_get('m_approve', $forum_id)
				|| $this->auth->acl_get('m_edit', $forum_id);
		}

		return $this->auth->acl_get('f_read', $forum_id)
			&& $this->content_visibility->is_visible('topic', $forum_id, $entry);
	}

	protected function can_edit_topic(array $entry): bool
	{
		$forum_id = (int) $entry['forum_id'];
		return ((int) $entry['topic_poster'] === (int) $this->user->data['user_id']
				&& $this->auth->acl_get('u_blog_create')
				&& $this->auth->acl_get('f_edit', $forum_id))
			|| $this->auth->acl_get('m_edit', $forum_id);
	}

	protected function can_comment_topic(array $entry): bool
	{
		if (isset($entry['user_blog_comments_enabled']) && !(bool) $entry['user_blog_comments_enabled'])
		{
			return false;
		}

		if ((int) ($entry['topic_status'] ?? ITEM_UNLOCKED) === ITEM_LOCKED)
		{
			return false;
		}

		$forum_id = (int) $entry['forum_id'];
		return $this->auth->acl_get('f_reply', $forum_id)
			|| (int) $this->user->data['user_id'] === ANONYMOUS;
	}

	protected function assign_not_configured(): void
	{
		$this->template->assign_vars([
			'BLOG_PAGE_TITLE' => $this->language->lang('BLOGS'),
			'BLOG_NOTICE' => $this->language->lang('BLOG_FORUM_NOT_CONFIGURED'),
			'S_BLOG_HAS_ENTRIES' => false,
		]);
	}

	protected function assign_entry_list(array $rows, bool $manage, int $start = 0): void
	{
		foreach ($rows as $row)
		{
			$this->template->assign_block_vars('blog_entries', $this->entry_template_vars($row, $manage, $start));
		}

		$this->template->assign_vars([
			'S_BLOG_HAS_ENTRIES' => !empty($rows),
		]);
	}

	protected function assign_top_blog_list(int $forum_id): void
	{
		$topics = $this->get_top_blog_topics($forum_id, self::TOP_BLOG_SIZE);
		$topics = $this->hydrate_top_blog_topic_stats($topics);
		$collapsible = $this->collapsible_operator
			&& method_exists($this->collapsible_operator, 'is_collapsed')
			&& method_exists($this->collapsible_operator, 'get_collapsible_link');
		$hidden = $collapsible ? (bool) $this->collapsible_operator->is_collapsed(self::TOP_BLOG_COLLAPSE_ID) : false;
		foreach ($topics as $topic)
		{
			$this->template->assign_block_vars('top_blog_entries', $this->top_blog_template_vars($topic));
		}

		$this->template->assign_vars([
			'S_BLOG_HAS_TOP_ENTRIES' => !empty($topics),
			'S_BLOG_TOP_COLLAPSIBLE' => $collapsible,
			'S_BLOG_TOP_HIDDEN' => $hidden,
			'U_BLOG_TOP_COLLAPSE_URL' => $collapsible ? $this->collapsible_operator->get_collapsible_link(self::TOP_BLOG_COLLAPSE_ID) : '',
			'BLOG_TOP_BLOCK_ID' => self::TOP_BLOG_COLLAPSE_ID,
			'BLOG_TOP_COLLAPSE_HIDDEN_DATA' => $hidden ? '1' : '',
			'BLOG_TOP_COLLAPSE_TITLE' => $hidden ? $this->language->lang('BLOG_TOP_COLLAPSE_SHOW') : $this->language->lang('BLOG_TOP_COLLAPSE_HIDE'),
			'BLOG_TOP_COLLAPSE_ALT_TITLE' => $hidden ? $this->language->lang('BLOG_TOP_COLLAPSE_HIDE') : $this->language->lang('BLOG_TOP_COLLAPSE_SHOW'),
			'BLOG_TOP_COLLAPSE_ICON' => $hidden ? 'fa-plus-square' : 'fa-minus-square',
		]);
	}

	protected function get_top_blog_topics(int $forum_id, int $limit): array
	{
		if ($limit <= 0)
		{
			return [];
		}

		if (!empty($this->toptopics_ranker) && method_exists($this->toptopics_ranker, 'get_topics'))
		{
			$topics = $this->toptopics_ranker->get_topics([$forum_id], $limit * 3);
			$topics = $this->filter_public_blog_topics($topics, $forum_id, $limit);
			if (!empty($topics))
			{
				return $topics;
			}
		}

		return $this->get_top_blog_topics_fallback($forum_id, $limit);
	}

	protected function get_top_blog_topics_fallback(int $forum_id, int $limit): array
	{
		$sql = 'SELECT t.topic_id, t.forum_id, t.topic_title, t.topic_time, t.topic_last_post_time, t.topic_last_post_id,
				t.topic_last_poster_id, t.topic_last_poster_name, t.topic_last_poster_colour, t.topic_type,
				t.topic_status, t.poll_start, t.topic_posts_approved, t.topic_views,
				t.topic_poster, t.topic_first_poster_name, t.topic_first_poster_colour, f.forum_name
			FROM ' . TOPICS_TABLE . ' t
			INNER JOIN ' . FORUMS_TABLE . ' f
				ON f.forum_id = t.forum_id
			LEFT JOIN ' . $this->blog_topics_table . ' bt
				ON bt.topic_id = t.topic_id
			WHERE ' . $this->public_blog_topic_sql($forum_id) . '
			ORDER BY t.topic_views DESC, t.topic_posts_approved DESC, t.topic_last_post_time DESC';

		return $this->fetch_rows($sql, $limit);
	}

	protected function hydrate_top_blog_topic_stats(array $topics): array
	{
		if (empty($topics))
		{
			return [];
		}

		$topic_ids = [];
		foreach ($topics as $topic)
		{
			$topic_id = (int) ($topic['topic_id'] ?? 0);
			if ($topic_id > 0)
			{
				$topic_ids[$topic_id] = true;
			}
		}

		if (empty($topic_ids))
		{
			return $topics;
		}

		$sql = 'SELECT topic_id, topic_posts_approved, topic_views, topic_last_post_time, topic_last_post_id,
				topic_last_poster_id, topic_last_poster_name, topic_last_poster_colour
			FROM ' . TOPICS_TABLE . '
			WHERE ' . $this->db->sql_in_set('topic_id', array_keys($topic_ids));
		$result = $this->db->sql_query($sql);
		$stats = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$stats[(int) $row['topic_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		foreach ($topics as &$topic)
		{
			$topic_id = (int) ($topic['topic_id'] ?? 0);
			if ($topic_id <= 0 || !isset($stats[$topic_id]))
			{
				continue;
			}

			$topic['topic_posts_approved'] = (int) $stats[$topic_id]['topic_posts_approved'];
			$topic['topic_views'] = (int) $stats[$topic_id]['topic_views'];
			$topic['topic_last_post_time'] = (int) $stats[$topic_id]['topic_last_post_time'];
			$topic['topic_last_post_id'] = (int) $stats[$topic_id]['topic_last_post_id'];
			$topic['topic_last_poster_id'] = (int) $stats[$topic_id]['topic_last_poster_id'];
			$topic['topic_last_poster_name'] = (string) $stats[$topic_id]['topic_last_poster_name'];
			$topic['topic_last_poster_colour'] = (string) $stats[$topic_id]['topic_last_poster_colour'];
			$topic['replies'] = max(0, $topic['topic_posts_approved'] - 1);
			$topic['views'] = $topic['topic_views'];
		}
		unset($topic);

		return $topics;
	}

	protected function filter_public_blog_topics(array $topics, int $forum_id, int $limit): array
	{
		if (empty($topics))
		{
			return [];
		}

		$topic_ids = [];
		foreach ($topics as $topic)
		{
			$topic_id = (int) ($topic['topic_id'] ?? 0);
			if ($topic_id > 0)
			{
				$topic_ids[$topic_id] = true;
			}
		}

		if (empty($topic_ids))
		{
			return [];
		}

		$sql = 'SELECT t.topic_id
			FROM ' . TOPICS_TABLE . ' t
			LEFT JOIN ' . $this->blog_topics_table . ' bt
				ON bt.topic_id = t.topic_id
			WHERE ' . $this->db->sql_in_set('t.topic_id', array_keys($topic_ids)) . '
				AND t.forum_id = ' . (int) $forum_id . '
				AND t.topic_moved_id = 0
				AND t.topic_visibility = ' . ITEM_APPROVED . '
				AND (bt.is_draft IS NULL OR bt.is_draft = 0)';
		$result = $this->db->sql_query($sql);
		$allowed_topic_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$allowed_topic_ids[(int) $row['topic_id']] = true;
		}
		$this->db->sql_freeresult($result);

		$filtered = [];
		foreach ($topics as $topic)
		{
			$topic_id = (int) ($topic['topic_id'] ?? 0);
			if ($topic_id > 0 && isset($allowed_topic_ids[$topic_id]))
			{
				$filtered[] = $topic;
				if (count($filtered) >= $limit)
				{
					break;
				}
			}
		}

		return $filtered;
	}

	protected function increment_blog_topic_views(array $entry): void
	{
		if ($this->user->data['is_bot'] || !empty($entry['is_draft']))
		{
			return;
		}

		$topic_id = (int) ($entry['topic_id'] ?? 0);
		if ($topic_id <= 0)
		{
			return;
		}

		$session_page = (string) ($this->user->data['session_page'] ?? '');
		$current_entry_page = 'app.php/blog/entry/' . $topic_id;
		if (strpos($session_page, $current_entry_page) !== false && empty($this->user->data['session_created']))
		{
			return;
		}

		$sql = 'UPDATE ' . TOPICS_TABLE . '
			SET topic_views = topic_views + 1, topic_last_view_time = ' . time() . '
			WHERE topic_id = ' . $topic_id;
		$this->db->sql_query($sql);
	}

	protected function top_blog_template_vars(array $topic): array
	{
		$topic_id = (int) $topic['topic_id'];
		$last_post_time = (int) ($topic['topic_last_post_time'] ?? $topic['topic_time'] ?? 0);
		$replies = isset($topic['replies'])
			? (int) $topic['replies']
			: max(0, (int) ($topic['topic_posts_approved'] ?? 0) - 1);
		$views = isset($topic['views'])
			? (int) $topic['views']
			: (int) ($topic['topic_views'] ?? 0);

		return [
			'U_ENTRY' => $this->helper->route('freemitbbs_blog_entry', ['entry_id' => $topic_id]),
			'U_LAST_POST' => $this->helper->route('freemitbbs_blog_entry', ['entry_id' => $topic_id]),
			'TOPIC_TITLE' => censor_text((string) $topic['topic_title']),
			'USERNAME_FULL' => get_username_string(
				'full',
				(int) $topic['topic_poster'],
				(string) $topic['topic_first_poster_name'],
				(string) $topic['topic_first_poster_colour']
			),
			'POST_TIME' => $this->user->format_date((int) ($topic['topic_time'] ?? 0)),
			'LAST_POST_AUTHOR_FULL' => $this->last_post_author_full($topic),
			'LAST_POST_TIME' => $this->user->format_date($last_post_time),
			'LAST_POST_TIME_RFC3339' => gmdate(DATE_RFC3339, $last_post_time),
			'REPLIES' => $replies,
			'VIEWS' => $views,
		];
	}

	protected function last_post_author_full(array $topic): string
	{
		$last_poster_id = (int) ($topic['topic_last_poster_id'] ?? 0);
		$last_poster_name = (string) ($topic['topic_last_poster_name'] ?? '');
		$last_poster_colour = (string) ($topic['topic_last_poster_colour'] ?? '');

		if ($last_poster_id <= 0 || $last_poster_name === '')
		{
			$last_poster_id = (int) ($topic['topic_poster'] ?? 0);
			$last_poster_name = (string) ($topic['topic_first_poster_name'] ?? '');
			$last_poster_colour = (string) ($topic['topic_first_poster_colour'] ?? '');
		}

		return get_username_string('full', $last_poster_id, $last_poster_name, $last_poster_colour);
	}

	protected function get_post_attachments(array $entry): array
	{
		if (empty($entry['post_attachment'])
			|| !$this->auth->acl_get('u_download')
			|| !$this->auth->acl_get('f_download', (int) $entry['forum_id']))
		{
			return [];
		}

		$sql = 'SELECT *
			FROM ' . ATTACHMENTS_TABLE . '
			WHERE post_msg_id = ' . (int) $entry['topic_first_post_id'] . '
				AND topic_id = ' . (int) $entry['topic_id'] . '
				AND in_message = 0
			ORDER BY attach_id DESC';

		return $this->fetch_rows($sql);
	}

	protected function get_blog_comments(array $entry, int $limit, int $start): array
	{
		$sql = 'SELECT p.post_id, p.post_text, p.post_attachment, p.post_time, p.bbcode_uid, p.bbcode_bitfield,
				p.enable_bbcode, p.enable_smilies, p.enable_magic_url,
				u.user_id, u.username, u.user_colour
			FROM ' . POSTS_TABLE . ' p
			INNER JOIN ' . USERS_TABLE . ' u
				ON u.user_id = p.poster_id
			WHERE p.topic_id = ' . (int) $entry['topic_id'] . '
				AND p.post_id <> ' . (int) $entry['topic_first_post_id'] . '
				AND p.post_visibility = ' . ITEM_APPROVED . '
			ORDER BY p.post_time ASC, p.post_id ASC';

		return $this->fetch_rows($sql, $limit, $start);
	}

	protected function get_comment_attachments(array $entry, array $comments): array
	{
		if (!$comments
			|| !$this->auth->acl_get('u_download')
			|| !$this->auth->acl_get('f_download', (int) $entry['forum_id']))
		{
			return [];
		}

		$post_ids = [];
		foreach ($comments as $comment)
		{
			if (!empty($comment['post_attachment']))
			{
				$post_ids[] = (int) $comment['post_id'];
			}
		}

		if (!$post_ids)
		{
			return [];
		}

		$sql = 'SELECT *
			FROM ' . ATTACHMENTS_TABLE . '
			WHERE ' . $this->db->sql_in_set('post_msg_id', $post_ids) . '
				AND topic_id = ' . (int) $entry['topic_id'] . '
				AND in_message = 0
			ORDER BY post_msg_id ASC, attach_id DESC';
		$result = $this->db->sql_query($sql);
		$attachments = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$attachments[(int) $row['post_msg_id']][] = $row;
		}
		$this->db->sql_freeresult($result);

		return $attachments;
	}

	protected function assign_blog_comments(array $entry, array $comments): void
	{
		$comment_attachments = $this->get_comment_attachments($entry, $comments);
		foreach ($comments as $comment)
		{
			$text = generate_text_for_display(
				$comment['post_text'],
				$comment['bbcode_uid'],
				$comment['bbcode_bitfield'],
				$this->post_options($comment)
			);
			$attachments = $comment_attachments[(int) $comment['post_id']] ?? [];
			if ($attachments)
			{
				$update_count = [];
				parse_attachments((int) $entry['forum_id'], $text, $attachments, $update_count);
			}

			$this->template->assign_block_vars('blog_comments', [
				'COMMENT_AUTHOR_FULL' => get_username_string('full', (int) $comment['user_id'], $comment['username'], $comment['user_colour']),
				'COMMENT_TIME' => $this->user->format_date((int) $comment['post_time']),
				'COMMENT_TIME_RFC3339' => gmdate(DATE_RFC3339, (int) $comment['post_time']),
				'COMMENT_TEXT' => $text,
				'S_HAS_ATTACHMENTS' => !empty($attachments),
			]);

			foreach ($attachments as $attachment)
			{
				$this->template->assign_block_vars('blog_comments.comment_attachments', [
					'DISPLAY_ATTACHMENT' => $attachment,
				]);
			}
		}
	}

	protected function assign_entry(array $entry): void
	{
		$text = generate_text_for_display(
			$entry['post_text'],
			$entry['bbcode_uid'],
			$entry['bbcode_bitfield'],
			$this->post_options($entry)
		);
		$attachments = $this->get_post_attachments($entry);
		if (!empty($attachments))
		{
			$update_count = [];
			parse_attachments((int) $entry['forum_id'], $text, $attachments, $update_count);
			foreach ($attachments as $attachment)
			{
				$this->template->assign_block_vars('blog_attachments', [
					'DISPLAY_ATTACHMENT' => $attachment,
				]);
			}
		}
		$comment_total = max(0, (int) ($entry['topic_posts_approved'] ?? 0) - 1);
		$comment_start = max(0, $this->request->variable('comment_start', 0));
		$comment_start = $this->pagination->validate_start($comment_start, self::COMMENT_PAGE_SIZE, $comment_total);
		$comments = $this->get_blog_comments($entry, self::COMMENT_PAGE_SIZE, $comment_start);
		$this->assign_blog_comments($entry, $comments);

		$this->pagination->generate_template_pagination(
			$this->helper->route('freemitbbs_blog_entry', ['entry_id' => (int) $entry['topic_id']]),
			'pagination',
			'comment_start',
			$comment_total,
			self::COMMENT_PAGE_SIZE,
			$comment_start
		);

		$this->template->assign_vars($this->entry_template_vars($entry, false));
		$this->template->assign_vars([
			'BLOG_TEXT' => $text,
			'BLOG_COMMENT_COUNT' => $comment_total,
			'COMMENT_PAGE_NUMBER' => $this->pagination->on_page($comment_total, self::COMMENT_PAGE_SIZE, $comment_start),
			'S_BLOG_ENTRY' => true,
			'S_BLOG_HAS_ATTACHMENTS' => !empty($attachments),
			'S_BLOG_HAS_COMMENTS' => !empty($comments),
			'S_BLOG_CAN_COMMENT' => $this->can_comment_topic($entry),
			'S_BLOG_CAN_EDIT_ENTRY' => $this->can_edit_topic($entry),
			'U_BLOG_COMMENT' => $this->posting_reply_url((int) $entry['topic_id']),
			'U_BLOG_EDIT' => $this->posting_edit_url((int) $entry['topic_first_post_id']),
			'U_BLOG_TOGGLE' => $this->helper->route('freemitbbs_blog_toggle', [
				'entry_id' => (int) $entry['topic_id'],
				'hash' => generate_link_hash('freemitbbs_blog_toggle_' . (int) $entry['topic_id']),
				'return' => 'entry',
			]),
			'BLOG_TOGGLE_LABEL' => !empty($entry['is_draft']) ? $this->language->lang('BLOG_PUBLISH') : $this->language->lang('BLOG_UNPUBLISH'),
		]);
	}

	protected function entry_template_vars(array $row, bool $manage, int $start = 0): array
	{
		$topic_id = (int) $row['topic_id'];
		$text = generate_text_for_display(
			$row['post_text'],
			$row['bbcode_uid'],
			$row['bbcode_bitfield'],
			$this->post_options($row)
		);
		$plain_text = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8'))));
		$source_post_id = (int) ($row['source_post_id'] ?? 0);
		$time = (int) ($row['topic_time'] ?: $row['topic_last_post_time']);
		$is_draft = !empty($row['is_draft']);

		return [
			'ENTRY_ID' => $topic_id,
			'ENTRY_TITLE' => censor_text($row['topic_title']),
			'ENTRY_EXCERPT' => $this->entry_excerpt($plain_text),
			'ENTRY_STATUS' => $is_draft ? $this->language->lang('BLOG_STATUS_DRAFT') : $this->visibility_label((int) $row['topic_visibility']),
			'ENTRY_STATUS_CLASS' => $is_draft ? 'draft' : $this->visibility_class((int) $row['topic_visibility']),
			'ENTRY_TIME' => $this->user->format_date($time),
			'ENTRY_TIME_RFC3339' => gmdate(DATE_RFC3339, $time),
			'ENTRY_AUTHOR_FULL' => get_username_string('full', (int) $row['topic_poster'], $row['username'], $row['user_colour']),
			'U_ENTRY' => $this->helper->route('freemitbbs_blog_entry', ['entry_id' => $topic_id]),
			'U_VIEW_TOPIC' => $this->view_topic_url($topic_id),
			'U_AUTHOR_BLOG' => $this->helper->route('freemitbbs_blog_user', ['user_id' => (int) $row['topic_poster']]),
			'U_EDIT' => $manage ? $this->posting_edit_url((int) $row['topic_first_post_id']) : '',
			'U_TOGGLE' => $manage ? $this->helper->route('freemitbbs_blog_toggle', array_filter([
				'entry_id' => $topic_id,
				'hash' => generate_link_hash('freemitbbs_blog_toggle_' . $topic_id),
				'return' => 'manage',
				'start' => $start,
			], static fn($value) => $value !== 0 && $value !== '')) : '',
			'TOGGLE_LABEL' => $is_draft ? $this->language->lang('BLOG_PUBLISH') : $this->language->lang('BLOG_UNPUBLISH'),
			'U_DELETE' => $manage ? $this->posting_delete_url((int) $row['topic_first_post_id']) : '',
			'S_ENTRY_DRAFT' => $is_draft || (int) $row['topic_visibility'] !== ITEM_APPROVED,
			'S_HAS_SOURCE_POST' => $source_post_id > 0,
			'U_SOURCE_POST' => $source_post_id > 0 ? append_sid(
				$this->root_path . 'viewtopic.' . $this->php_ext,
				't=' . (int) ($row['source_topic_id'] ?? 0) . '&amp;p=' . $source_post_id . '#p' . $source_post_id
			) : '',
		];
	}

	protected function entry_excerpt(string $plain_text): string
	{
		$excerpt = trim(truncate_string($plain_text, self::EXCERPT_LENGTH, self::EXCERPT_LENGTH, false));
		if ($excerpt === '')
		{
			return '';
		}

		$excerpt = preg_replace('/(?:\.\.\.|…)\s*$/u', '', $excerpt);

		return rtrim((string) $excerpt) . '...';
	}

	protected function assign_common_vars(?array $forum = null): void
	{
		$user_id = (int) $this->user->data['user_id'];
		$forum_id = $forum ? (int) $forum['forum_id'] : 0;
		$can_create = $forum_id > 0
			&& $user_id !== ANONYMOUS
			&& $this->auth->acl_get('u_blog_create')
			&& $this->auth->acl_get('f_post', $forum_id);

		$this->template->assign_vars([
			'S_FREEMITBBS_BLOG_PAGE' => true,
			'S_FREEMITBBS_BLOG_NAV' => true,
			'S_BLOG_CAN_CREATE' => $can_create,
			'S_BLOG_CONFIGURED' => $forum_id > 0,
			'U_BLOG_INDEX' => $this->helper->route('freemitbbs_blog_index'),
			'U_BLOG_MANAGE' => $can_create ? $this->helper->route('freemitbbs_blog_manage') : '',
			'U_BLOG_NEW' => $can_create ? $this->posting_new_url($forum_id) : '',
			'U_BLOG_FORUM' => $forum_id > 0 ? $this->view_forum_url($forum_id) : '',
		]);
	}

	protected function fetch_rows(string $sql, int $limit = 0, int $start = 0): array
	{
		$result = $limit > 0 ? $this->db->sql_query_limit($sql, $limit, $start) : $this->db->sql_query($sql);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function post_options(array $row): int
	{
		return ((int) $row['enable_bbcode'] ? OPTION_FLAG_BBCODE : 0)
			+ ((int) $row['enable_smilies'] ? OPTION_FLAG_SMILIES : 0)
			+ ((int) $row['enable_magic_url'] ? OPTION_FLAG_LINKS : 0);
	}

	protected function visibility_label(int $visibility): string
	{
		return match ($visibility) {
			ITEM_APPROVED => $this->language->lang('BLOG_STATUS_PUBLISHED'),
			ITEM_DELETED => $this->language->lang('BLOG_STATUS_DELETED'),
			default => $this->language->lang('BLOG_STATUS_PENDING'),
		};
	}

	protected function visibility_class(int $visibility): string
	{
		return match ($visibility) {
			ITEM_APPROVED => 'published',
			ITEM_DELETED => 'deleted',
			default => 'pending',
		};
	}

	protected function posting_new_url(int $forum_id): string
	{
		return append_sid($this->root_path . 'posting.' . $this->php_ext, 'mode=post&amp;f=' . $forum_id);
	}

	protected function posting_edit_url(int $post_id): string
	{
		return append_sid($this->root_path . 'posting.' . $this->php_ext, 'mode=edit&amp;p=' . $post_id);
	}

	protected function posting_reply_url(int $topic_id): string
	{
		return append_sid($this->root_path . 'posting.' . $this->php_ext, 'mode=reply&amp;t=' . $topic_id);
	}

	protected function posting_delete_url(int $post_id): string
	{
		return append_sid($this->root_path . 'posting.' . $this->php_ext, 'mode=delete&amp;p=' . $post_id);
	}

	protected function view_topic_url(int $topic_id): string
	{
		return append_sid($this->root_path . 'viewtopic.' . $this->php_ext, 't=' . $topic_id);
	}

	protected function source_post_url(array $post): string
	{
		$post_id = (int) $post['post_id'];
		return append_sid(
			$this->root_path . 'viewtopic.' . $this->php_ext,
			't=' . (int) $post['topic_id'] . '&amp;p=' . $post_id . '#p' . $post_id
		);
	}

	protected function view_forum_url(int $forum_id): string
	{
		return append_sid($this->root_path . 'viewforum.' . $this->php_ext, 'f=' . $forum_id);
	}

	protected function escape(string $text): string
	{
		return htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
	}
}
