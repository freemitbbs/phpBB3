<?php

namespace freemitbbs\videoupload\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class upload
{
	private const LINK_HASH_NAME = 'freemitbbs_videoupload';
	private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
	private const BROWSER_VIDEO_CODEC_CHECK_EXTENSIONS = ['mp4', 'mov'];
	private const UNSUPPORTED_BROWSER_VIDEO_CODECS = ['hvc1', 'hev1', 'dvh1', 'dvhe'];
	private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'mp4', 'mov', 'ogg', 'webm', 'weba', 'mp3', 'm4a', 'aac', 'wav', 'oga', 'opus', 'flac'];
	private const MEDIA_MIME_TYPES = [
		'mp4' => ['video/mp4', 'application/mp4'],
		'mov' => ['video/quicktime', 'video/mp4'],
		'ogg' => ['video/ogg', 'audio/ogg', 'application/ogg'],
		'webm' => ['video/webm', 'audio/webm'],
		'weba' => ['audio/webm', 'video/webm'],
		'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg', 'audio/x-mp3'],
		'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4'],
		'aac' => ['audio/aac', 'audio/x-aac', 'audio/x-hx-aac-adts'],
		'wav' => ['audio/wav', 'audio/wave', 'audio/x-wav', 'audio/vnd.wave'],
		'oga' => ['audio/ogg', 'application/ogg'],
		'opus' => ['audio/opus', 'audio/ogg', 'application/ogg'],
		'flac' => ['audio/flac', 'audio/x-flac'],
	];

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
		if ($this->has_unsupported_browser_video_codec($tmp_name, $extension))
		{
			return $this->json_error($this->language->lang('VIDEOUPLOAD_ERR_UNSUPPORTED_VIDEO_CODEC'), 400);
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
		if (in_array($extension, self::IMAGE_EXTENSIONS, true))
		{
			$image_info = @getimagesize($tmp_name);
			if (is_array($image_info) && isset($image_info[2]))
			{
				$image_type = (int) $image_info[2];
				$allowed_types = [
					'jpg' => [IMAGETYPE_JPEG],
					'jpeg' => [IMAGETYPE_JPEG],
					'png' => [IMAGETYPE_PNG],
					'gif' => [IMAGETYPE_GIF],
					'webp' => defined('IMAGETYPE_WEBP') ? [IMAGETYPE_WEBP] : [],
					'avif' => defined('IMAGETYPE_AVIF') ? [IMAGETYPE_AVIF] : [],
				];

				return in_array($image_type, $allowed_types[$extension] ?? [], true);
			}

			return $extension === 'avif' && $this->is_valid_avif_upload($tmp_name);
		}

		$mime_type = $this->detect_upload_mime_type($tmp_name);

		return $mime_type !== '' && in_array($mime_type, self::MEDIA_MIME_TYPES[$extension] ?? [], true);
	}

	protected function is_valid_avif_upload(string $tmp_name): bool
	{
		if (!$this->file_has_avif_brand($tmp_name))
		{
			return false;
		}

		$mime_type = $this->detect_upload_mime_type($tmp_name);
		return in_array($mime_type, ['', 'application/octet-stream', 'image/avif', 'image/avif-sequence'], true);
	}

	protected function file_has_avif_brand(string $tmp_name): bool
	{
		$handle = @fopen($tmp_name, 'rb');
		if ($handle === false)
		{
			return false;
		}

		try
		{
			$header = fread($handle, 64);
		}
		finally
		{
			fclose($handle);
		}

		if (!is_string($header) || strlen($header) < 12 || substr($header, 4, 4) !== 'ftyp')
		{
			return false;
		}

		$brands = substr($header, 8);
		return strpos($brands, 'avif') !== false || strpos($brands, 'avis') !== false;
	}

	protected function has_unsupported_browser_video_codec(string $tmp_name, string $extension): bool
	{
		if (!in_array($extension, self::BROWSER_VIDEO_CODEC_CHECK_EXTENSIONS, true))
		{
			return false;
		}

		$metadata_result = $this->mp4_metadata_contains_any($tmp_name, self::UNSUPPORTED_BROWSER_VIDEO_CODECS);

		return $metadata_result === true;
	}

	protected function mp4_metadata_contains_any(string $file_path, array $needles): ?bool
	{
		$file_size = @filesize($file_path);
		if ($file_size === false || $file_size < 8)
		{
			return null;
		}

		$handle = @fopen($file_path, 'rb');
		if ($handle === false)
		{
			return null;
		}

		try
		{
			$offset = 0;
			while ($offset + 8 <= $file_size)
			{
				if (@fseek($handle, $offset) !== 0)
				{
					return null;
				}

				$header = fread($handle, 8);
				if (!is_string($header) || strlen($header) !== 8)
				{
					return null;
				}

				$size = $this->uint32(substr($header, 0, 4));
				$type = substr($header, 4, 4);
				$header_size = 8;

				if ($size === 1)
				{
					$extended_size = fread($handle, 8);
					if (!is_string($extended_size) || strlen($extended_size) !== 8)
					{
						return null;
					}
					$size = $this->uint64($extended_size);
					$header_size = 16;
				}
				else if ($size === 0)
				{
					$size = $file_size - $offset;
				}

				if ($size < $header_size || ($offset + $size) > $file_size)
				{
					return null;
				}

				if ($type === 'moov')
				{
					return $this->stream_contains_any($handle, $size - $header_size, $needles);
				}

				$offset += $size;
			}
		}
		finally
		{
			fclose($handle);
		}

		return null;
	}

	protected function stream_contains_any($handle, int $bytes_to_read, array $needles): bool
	{
		$tail = '';
		while ($bytes_to_read > 0 && !feof($handle))
		{
			$chunk_size = min(8192, $bytes_to_read);
			$chunk = fread($handle, $chunk_size);
			if (!is_string($chunk) || $chunk === '')
			{
				break;
			}

			$haystack = $tail . $chunk;
			foreach ($needles as $needle)
			{
				if (strpos($haystack, $needle) !== false)
				{
					return true;
				}
			}

			$tail = substr($haystack, -3);
			$bytes_to_read -= strlen($chunk);
		}

		return false;
	}

	protected function uint32(string $bytes): int
	{
		$unpacked = unpack('Nvalue', $bytes);

		return (int) ($unpacked['value'] ?? 0);
	}

	protected function uint64(string $bytes): int
	{
		$parts = unpack('Nhigh/Nlow', $bytes);
		$high = (int) ($parts['high'] ?? 0);
		$low = (int) ($parts['low'] ?? 0);

		return (int) (($high * 4294967296) + $low);
	}

	protected function detect_upload_mime_type(string $tmp_name): string
	{
		if (function_exists('finfo_open'))
		{
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			if ($finfo !== false)
			{
				$mime_type = finfo_file($finfo, $tmp_name);
				finfo_close($finfo);
				if (is_string($mime_type) && $mime_type !== '')
				{
					return strtolower(trim($mime_type));
				}
			}
		}

		if (function_exists('mime_content_type'))
		{
			$mime_type = mime_content_type($tmp_name);
			if (is_string($mime_type) && $mime_type !== '')
			{
				return strtolower(trim($mime_type));
			}
		}

		return '';
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
