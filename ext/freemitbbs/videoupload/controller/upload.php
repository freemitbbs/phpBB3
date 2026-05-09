<?php

namespace freemitbbs\videoupload\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class upload
{
	private const LINK_HASH_NAME = 'freemitbbs_videoupload';
	private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
	private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'ogg', 'webm', 'weba', 'mp3', 'm4a', 'aac', 'wav', 'oga', 'opus', 'flac'];

	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\user $user;
	protected \phpbb\language\language $language;
	protected \freemitbbs\videoupload\service\s3_uploader $s3_uploader;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\request\request_interface $request,
		\phpbb\user $user,
		\phpbb\language\language $language,
		\freemitbbs\videoupload\service\s3_uploader $s3_uploader
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->request = $request;
		$this->user = $user;
		$this->language = $language;
		$this->s3_uploader = $s3_uploader;
	}

	public function handle(): JsonResponse
	{
		$this->language->add_lang('common', 'freemitbbs/videoupload');

		if ($this->request->server('REQUEST_METHOD') !== 'POST')
		{
			return $this->json_error($this->language->lang('VIDEOUPLOAD_ERR_METHOD'), 405);
		}

		if (!(bool) ((int) ($this->config['videoupload_enabled'] ?? 0)))
		{
			return $this->json_error($this->language->lang('VIDEOUPLOAD_ERR_DISABLED'), 403);
		}

		$php_upload_limit = $this->php_upload_limit_bytes();
		$content_length = (int) $this->request->server('CONTENT_LENGTH', 0);
		if ($php_upload_limit > 0 && $content_length > $php_upload_limit)
		{
			return $this->json_error(
				$this->language->lang('VIDEOUPLOAD_ERR_PHP_LIMIT', $this->format_size($php_upload_limit)),
				413
			);
		}

		if ((int) $this->user->data['user_id'] === ANONYMOUS)
		{
			return $this->json_error($this->language->lang('VIDEOUPLOAD_ERR_LOGIN_REQUIRED'), 403);
		}

		if (!$this->has_storage_config())
		{
			return $this->json_error($this->language->lang('VIDEOUPLOAD_ERR_MISSING_CONFIG'), 503);
		}

		if (!check_link_hash($this->request->variable('hash', ''), self::LINK_HASH_NAME))
		{
			return $this->json_error($this->language->lang('VIDEOUPLOAD_ERR_FORM'), 400);
		}

		$forum_id = (int) $this->request->variable('forum_id', 0);
		if ($forum_id <= 0)
		{
			return $this->json_error($this->language->lang('VIDEOUPLOAD_ERR_FORUM'), 400);
		}

		if (!$this->can_upload_in_forum($forum_id))
		{
			return $this->json_error($this->language->lang('VIDEOUPLOAD_ERR_PERMISSION'), 403);
		}

		$files = $this->request->get_super_global(\phpbb\request\request_interface::FILES);
		$file = $files['media_file'] ?? ($files['video_file'] ?? null);
		if (!is_array($file))
		{
			return $this->json_error($this->language->lang('VIDEOUPLOAD_ERR_NO_FILE'), 400);
		}

		$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
		if ($error !== UPLOAD_ERR_OK)
		{
			return $this->json_error($this->upload_error_message($error), 400);
		}

		$tmp_name = (string) ($file['tmp_name'] ?? '');
		$original_name = (string) ($file['name'] ?? '');
		$size = (int) ($file['size'] ?? 0);
		if ($tmp_name === '' || $size <= 0 || !is_uploaded_file($tmp_name))
		{
			return $this->json_error($this->language->lang('VIDEOUPLOAD_ERR_INVALID_UPLOAD'), 400);
		}

		$max_size_bytes = max(1, (int) ($this->config['videoupload_max_size_mb'] ?? 64)) * 1024 * 1024;
		if ($size > $max_size_bytes)
		{
			return $this->json_error($this->language->lang('VIDEOUPLOAD_ERR_TOO_LARGE', $this->format_size($max_size_bytes)), 400);
		}

		$extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
		if (!in_array($extension, self::ALLOWED_EXTENSIONS, true))
		{
			return $this->json_error($this->language->lang('VIDEOUPLOAD_ERR_UNSUPPORTED_EXTENSION', implode(', ', self::ALLOWED_EXTENSIONS)), 400);
		}

		if (!$this->is_valid_upload_content($tmp_name, $extension))
		{
			return $this->json_error($this->language->lang('VIDEOUPLOAD_ERR_INVALID_IMAGE'), 400);
		}

		$object_key = $this->build_object_key($extension);

		try
		{
			$url = $this->s3_uploader->upload($tmp_name, $size, $extension, $object_key);
		}
		catch (\RuntimeException $e)
		{
			return $this->json_error($e->getMessage(), 500);
		}

		if (!$this->url_has_allowed_extension($url))
		{
			return $this->json_error($this->language->lang('VIDEOUPLOAD_ERR_BAD_URL'), 500);
		}

		return new JsonResponse([
			'success' => true,
			'url' => $url,
			'message' => $this->language->lang('VIDEOUPLOAD_UPLOAD_SUCCESS'),
		]);
	}

	protected function json_error(string $message, int $status = 400): JsonResponse
	{
		return new JsonResponse([
			'success' => false,
			'error' => $message,
		], $status);
	}

	protected function upload_error_message(int $error_code): string
	{
		return match ($error_code) {
			UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $this->language->lang('VIDEOUPLOAD_ERR_FILE_TOO_LARGE_PHP'),
			UPLOAD_ERR_PARTIAL => $this->language->lang('VIDEOUPLOAD_ERR_PARTIAL'),
			UPLOAD_ERR_NO_FILE => $this->language->lang('VIDEOUPLOAD_ERR_NO_FILE'),
			UPLOAD_ERR_CANT_WRITE => $this->language->lang('VIDEOUPLOAD_ERR_DISK'),
			UPLOAD_ERR_EXTENSION => $this->language->lang('VIDEOUPLOAD_ERR_EXTENSION'),
			default => $this->language->lang('VIDEOUPLOAD_ERR_UPLOAD_FAILED'),
		};
	}

	protected function build_object_key(string $extension): string
	{
		$prefix = trim((string) ($this->config['videoupload_s3_path_prefix'] ?? ''), " \t\n\r\0\x0B/");
		try
		{
			$random_part = bin2hex(random_bytes(16));
		}
		catch (\Exception $e)
		{
			$random_part = sha1(uniqid((string) mt_rand(), true));
		}

		$key = gmdate('Y/m') . '/' . $random_part . '.' . $extension;

		return ($prefix !== '') ? ($prefix . '/' . $key) : $key;
	}

	protected function url_has_allowed_extension(string $url): bool
	{
		$path = parse_url($url, PHP_URL_PATH);
		if (!is_string($path) || $path === '')
		{
			return false;
		}

		$lower_path = strtolower($path);
		foreach (self::ALLOWED_EXTENSIONS as $ext)
		{
			if (str_ends_with($lower_path, '.' . $ext))
			{
				return true;
			}
		}

		return false;
	}

	protected function is_valid_upload_content(string $tmp_name, string $extension): bool
	{
		if (!in_array($extension, self::IMAGE_EXTENSIONS, true))
		{
			return true;
		}

		$image_info = @getimagesize($tmp_name);
		if (!is_array($image_info) || !isset($image_info[2]))
		{
			return false;
		}

		$image_type = (int) $image_info[2];
		$allowed_types = [
			'jpg' => [IMAGETYPE_JPEG],
			'jpeg' => [IMAGETYPE_JPEG],
			'png' => [IMAGETYPE_PNG],
			'gif' => [IMAGETYPE_GIF],
			'webp' => defined('IMAGETYPE_WEBP') ? [IMAGETYPE_WEBP] : [],
		];

		return in_array($image_type, $allowed_types[$extension] ?? [], true);
	}

	protected function can_upload_in_forum(int $forum_id): bool
	{
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
		return $this->s3_uploader->has_storage_config();
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
