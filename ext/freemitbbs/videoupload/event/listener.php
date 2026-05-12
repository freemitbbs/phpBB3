<?php

namespace freemitbbs\videoupload\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
	private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'ogg', 'webm', 'weba', 'mp3', 'm4a', 'aac', 'wav', 'oga', 'opus', 'flac'];
	private const IMAGE_ACCEPT_MIME_TYPES = [
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
	];
	private const ACCEPT_MIME_TYPES = [
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'video/mp4',
		'video/quicktime',
		'video/x-quicktime',
		'video/ogg',
		'video/webm',
		'audio/webm',
		'audio/mpeg',
		'audio/mp4',
		'audio/aac',
		'audio/wav',
		'audio/x-wav',
		'audio/ogg',
		'audio/opus',
		'audio/flac',
	];

	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\language\language $language;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\user $user;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\language\language $language,
		\phpbb\controller\helper $helper,
		\phpbb\user $user
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->language = $language;
		$this->helper = $helper;
		$this->user = $user;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language',
			'core.posting_modify_template_vars' => 'add_posting_template_vars',
			'core.viewtopic_modify_quick_reply_template_vars' => 'add_quick_reply_template_vars',
		];
	}

	public function load_language($event): void
	{
		$this->language->add_lang('common', 'freemitbbs/videoupload');
	}

	public function add_posting_template_vars($event): void
	{
		$forum_id = (int) $event['forum_id'];
		$mode = (string) $event['mode'];
		if (!$this->should_show_uploader($forum_id, $mode))
		{
			return;
		}

		$page_data = $event['page_data'];
		$page_data = array_merge($page_data, $this->uploader_template_vars($forum_id));

		$event['page_data'] = $page_data;
	}

	public function add_quick_reply_template_vars($event): void
	{
		$topic_data = $event['topic_data'];
		$forum_id = (int) ($topic_data['forum_id'] ?? 0);
		if (!$this->should_show_uploader($forum_id, 'reply'))
		{
			return;
		}

		$tpl_ary = $event['tpl_ary'];
		$tpl_ary = array_merge($tpl_ary, $this->uploader_template_vars($forum_id));

		$event['tpl_ary'] = $tpl_ary;
	}

	protected function uploader_template_vars(int $forum_id): array
	{
		$configured_max_size_mb = max(1, (int) ($this->config['videoupload_max_size_mb'] ?? 64));
		$configured_max_bytes = $configured_max_size_mb * 1024 * 1024;
		$php_limit_bytes = $this->php_upload_limit_bytes();
		$max_bytes = ($php_limit_bytes > 0) ? min($configured_max_bytes, $php_limit_bytes) : $configured_max_bytes;
		$allowed_label = implode(', ', self::ALLOWED_EXTENSIONS);
		$image_label = implode(', ', self::IMAGE_EXTENSIONS);
		$allowed_exts = array_map(static function ($extension)
		{
			return '.' . $extension;
		}, self::ALLOWED_EXTENSIONS);
		$image_exts = array_map(static function ($extension)
		{
			return '.' . $extension;
		}, self::IMAGE_EXTENSIONS);
		$allowed_exts_value = implode(',', $allowed_exts);
		$image_exts_value = implode(',', $image_exts);
		$accept_value = implode(',', array_merge($allowed_exts, self::ACCEPT_MIME_TYPES));
		$image_accept_value = implode(',', array_merge($image_exts, self::IMAGE_ACCEPT_MIME_TYPES));

		return [
			'S_VIDEOUPLOAD_AVAILABLE' => true,
			'VIDEOUPLOAD_U_UPLOAD' => $this->helper->route('freemitbbs_videoupload_upload'),
			'VIDEOUPLOAD_HASH' => generate_link_hash('freemitbbs_videoupload'),
			'VIDEOUPLOAD_FORUM_ID' => $forum_id,
			'VIDEOUPLOAD_MAX_BYTES' => $max_bytes,
			'VIDEOUPLOAD_ALLOWED_EXTS' => $allowed_exts_value,
			'VIDEOUPLOAD_IMAGE_EXTS' => $image_exts_value,
			'VIDEOUPLOAD_ACCEPT' => $accept_value,
			'VIDEOUPLOAD_IMAGE_ACCEPT' => $image_accept_value,
			'VIDEOUPLOAD_HELP_TEXT' => $this->language->lang('VIDEOUPLOAD_HELP_WITH_LIMIT', $allowed_label, $this->format_size($max_bytes)),
			'VIDEOUPLOAD_MSG_UPLOADING' => $this->language->lang('VIDEOUPLOAD_UPLOADING'),
			'VIDEOUPLOAD_MSG_SUCCESS' => $this->language->lang('VIDEOUPLOAD_UPLOAD_SUCCESS'),
			'VIDEOUPLOAD_MSG_EXTENSION' => $this->language->lang('VIDEOUPLOAD_ERR_UNSUPPORTED_EXTENSION', $allowed_label),
			'VIDEOUPLOAD_MSG_IMAGE_EXTENSION' => $this->language->lang('VIDEOUPLOAD_ERR_UNSUPPORTED_EXTENSION', $image_label),
			'VIDEOUPLOAD_MSG_TOO_LARGE' => $this->language->lang('VIDEOUPLOAD_ERR_TOO_LARGE', $this->format_size($max_bytes)),
			'VIDEOUPLOAD_MSG_GENERIC' => $this->language->lang('VIDEOUPLOAD_ERR_SERVER'),
			'VIDEOUPLOAD_MSG_MULTI_EMPTY' => $this->language->lang('VIDEOUPLOAD_MULTI_EMPTY'),
			'VIDEOUPLOAD_MSG_MULTI_UPLOADING' => $this->language->lang('VIDEOUPLOAD_MULTI_UPLOADING'),
			'VIDEOUPLOAD_MSG_MULTI_SUCCESS' => $this->language->lang('VIDEOUPLOAD_MULTI_SUCCESS'),
			'VIDEOUPLOAD_MSG_MULTI_PARTIAL' => $this->language->lang('VIDEOUPLOAD_MULTI_PARTIAL'),
		];
	}

	protected function should_show_uploader(int $forum_id, string $mode): bool
	{
		if ($forum_id <= 0)
		{
			return false;
		}

		if (!in_array($mode, ['post', 'reply', 'quote', 'edit'], true))
		{
			return false;
		}

		if ((int) $this->user->data['user_id'] === ANONYMOUS)
		{
			return false;
		}

		if (!(bool) ((int) ($this->config['videoupload_enabled'] ?? 0)))
		{
			return false;
		}

		if (!$this->has_storage_config())
		{
			return false;
		}

		if (!$this->auth->acl_get('f_read', $forum_id))
		{
			return false;
		}

		return $this->auth->acl_get('f_post', $forum_id)
			|| $this->auth->acl_get('f_reply', $forum_id)
			|| $this->auth->acl_get('f_edit', $forum_id)
			|| $this->auth->acl_get('m_edit', $forum_id)
			|| $this->auth->acl_get('m_', $forum_id);
	}

	protected function has_storage_config(): bool
	{
		$required = [
			'videoupload_s3_endpoint',
			'videoupload_s3_region',
			'videoupload_s3_bucket',
			'videoupload_s3_access_key',
			'videoupload_s3_secret_key',
		];

		foreach ($required as $key)
		{
			if (trim((string) ($this->config[$key] ?? '')) === '')
			{
				return false;
			}
		}

		return true;
	}

	protected function format_size(int $bytes): string
	{
		$mb = $bytes / 1024 / 1024;
		if ($mb >= 1024)
		{
			return number_format($mb / 1024, 1) . ' GB';
		}

		return number_format($mb, 0) . ' MB';
	}

	protected function php_upload_limit_bytes(): int
	{
		$upload_max = $this->ini_size_to_bytes((string) ini_get('upload_max_filesize'));
		$post_max = $this->ini_size_to_bytes((string) ini_get('post_max_size'));
		if ($upload_max <= 0 && $post_max <= 0)
		{
			return 0;
		}
		if ($upload_max <= 0)
		{
			return $post_max;
		}
		if ($post_max <= 0)
		{
			return $upload_max;
		}

		return min($upload_max, $post_max);
	}

	protected function ini_size_to_bytes(string $value): int
	{
		$value = trim($value);
		if ($value === '')
		{
			return 0;
		}

		$number = (float) $value;
		$unit = strtolower(substr($value, -1));
		switch ($unit)
		{
			case 'g':
				$number *= 1024;
				// no break
			case 'm':
				$number *= 1024;
				// no break
			case 'k':
				$number *= 1024;
				break;
			default:
				break;
		}

		return (int) round($number);
	}
}
