<?php

namespace freemitbbs\toptopics\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class inline_preview
{
	private const CACHE_PREFIX = '_freemitbbs_toptopics_inline_preview_';
	private const CACHE_REVISION = 3;
	private const CACHE_SECONDS = 600;
	private const MAX_BATCH_TOPICS = 40;
	private const MAX_IMAGES = 8;
	private const MAX_TEXT_CHARS = 420;

	protected \phpbb\auth\auth $auth;
	protected \phpbb\cache\service $cache;
	protected \phpbb\content_visibility $content_visibility;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\request\request_interface $request;
	protected string $root_path;
	protected string $php_ext;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\cache\service $cache,
		\phpbb\content_visibility $content_visibility,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\request\request_interface $request,
		string $root_path,
		string $php_ext
	)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->content_visibility = $content_visibility;
		$this->db = $db;
		$this->request = $request;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public function base($topic): JsonResponse
	{
		$topic_id = (int) $topic;
		$preview = $this->get_preview_for_topic($topic_id);
		if (!$preview)
		{
			return new JsonResponse(['status' => 404], 404);
		}

		return new JsonResponse($preview);
	}

	public function batch(): JsonResponse
	{
		$topic_ids = $this->parse_topic_ids($this->request->variable('topic_ids', ''));
		if (empty($topic_ids))
		{
			return new JsonResponse([]);
		}

		$rows = $this->get_preview_rows($topic_ids);
		$response = [];
		foreach ($topic_ids as $topic_id)
		{
			if (isset($rows[$topic_id]))
			{
				$preview = $this->build_preview_for_row($rows[$topic_id]);
				$response[(string) $topic_id] = $preview ?: ['status' => 404];
			}
			else
			{
				$response[(string) $topic_id] = ['status' => 404];
			}
		}

		return new JsonResponse($response);
	}

	protected function get_preview_for_topic(int $topic_id): array|false
	{
		if ($topic_id <= 0)
		{
			return false;
		}

		$rows = $this->get_preview_rows([$topic_id]);

		return isset($rows[$topic_id]) ? $this->build_preview_for_row($rows[$topic_id]) : false;
	}

	protected function get_preview_rows(array $topic_ids): array
	{
		$topic_ids = array_values(array_unique(array_filter(array_map('intval', $topic_ids), static function ($topic_id) {
			return $topic_id > 0;
		})));
		if (empty($topic_ids))
		{
			return [];
		}

		$sql = 'SELECT t.topic_id, t.forum_id, t.topic_first_post_id, t.topic_visibility, t.topic_poster,
				p.post_id, p.poster_id, p.post_visibility, p.post_text, p.bbcode_uid, p.post_attachment,
				p.post_time, p.post_edit_time
			FROM ' . TOPICS_TABLE . ' t
			INNER JOIN ' . POSTS_TABLE . ' p
				ON p.post_id = t.topic_first_post_id
			WHERE ' . $this->db->sql_in_set('t.topic_id', $topic_ids);
		$result = $this->db->sql_query($sql);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$topic_id = (int) $row['topic_id'];
			if ($this->can_view_row($row))
			{
				$rows[$topic_id] = $row;
			}
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function can_view_row(array $row): bool
	{
		$forum_id = (int) $row['forum_id'];

		return $forum_id > 0
			&& $this->auth->acl_get('f_read', $forum_id)
			&& $this->content_visibility->is_visible('topic', $forum_id, $row)
			&& $this->content_visibility->is_visible('post', $forum_id, $row);
	}

	protected function build_preview_for_row(array $row): array|false
	{
		$cache_key = $this->build_cache_key($row);
		$cached = $this->cache->get($cache_key);
		if (is_array($cached))
		{
			return $cached;
		}

		$image_urls = $this->extract_image_urls((string) $row['post_text']);
		$embedded_media = $this->extract_embedded_media((string) $row['post_text']);
		if (!empty($row['post_attachment']))
		{
			$attachment_media = $this->get_attachment_media((int) $row['post_id']);
			$image_urls = $this->merge_image_urls($image_urls, $attachment_media['image_urls']);
			if ($embedded_media['media_type'] === '' && $attachment_media['video_url'] !== '')
			{
				$embedded_media = [
					'media_type' => 'video',
					'media_url' => $attachment_media['video_url'],
					'media_id' => '',
				];
			}
		}

		$media_type = empty($image_urls) ? $embedded_media['media_type'] : 'image';
		$media_url = empty($image_urls) ? $embedded_media['media_url'] : ($image_urls[0] ?? '');
		$preview = [
			'status' => 200,
			'topic_id' => (int) $row['topic_id'],
			'plain_text' => $this->extract_plain_text((string) $row['post_text'], (string) $row['bbcode_uid']),
			'image_urls' => $image_urls,
			'media_type' => $media_type,
			'media_url' => $media_url,
			'media_id' => empty($image_urls) ? $embedded_media['media_id'] : '',
			'media_urls' => $image_urls,
		];

		if ($preview['plain_text'] === '' && empty($preview['image_urls']) && $preview['media_url'] === '')
		{
			return false;
		}

		$this->cache->put($cache_key, $preview, self::CACHE_SECONDS);

		return $preview;
	}

	protected function build_cache_key(array $row): string
	{
		return self::CACHE_PREFIX
			. self::CACHE_REVISION . '_'
			. (int) $row['topic_id'] . '_'
			. (int) $row['topic_first_post_id'] . '_'
			. max((int) $row['post_time'], (int) $row['post_edit_time']) . '_'
			. (int) $row['post_attachment'];
	}

	protected function parse_topic_ids(string $value): array
	{
		$topic_ids = [];
		foreach (preg_split('/\s*,\s*/', $value) ?: [] as $piece)
		{
			$topic_id = (int) $piece;
			if ($topic_id > 0 && !isset($topic_ids[$topic_id]))
			{
				$topic_ids[$topic_id] = $topic_id;
			}

			if (count($topic_ids) >= self::MAX_BATCH_TOPICS)
			{
				break;
			}
		}

		return array_values($topic_ids);
	}

	protected function extract_image_urls(string $post_text): array
	{
		if ($post_text === '')
		{
			return [];
		}

		$post_text = $this->remove_quoted_content($post_text);
		$image_urls = [];
		$seen = [];

		if (preg_match_all('#<IMG\b[^>]*\bsrc=(["\'])(.*?)\1#is', $post_text, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				$this->add_image_url($image_urls, $seen, $match[2]);
			}
		}

		if (preg_match_all('#\[img(?:=[^\]]*)?\](.*?)\[/img\]#is', $post_text, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				$this->add_image_url($image_urls, $seen, $match[1]);
			}
		}

		if (preg_match_all('#https?://[^\s\[\]<>"\']+\.(?:jpe?g|png|gif|webp|avif)(?:[?\#][^\s\[\]<>"\']*)?#i', $post_text, $matches))
		{
			foreach ($matches[0] as $url)
			{
				if ($this->is_trusted_uploaded_image_url($url))
				{
					$this->add_image_url($image_urls, $seen, $url);
				}
			}
		}

		return $image_urls;
	}

	protected function extract_embedded_media(string $post_text): array
	{
		$video_url = $this->extract_video_url($post_text);
		if ($video_url !== '')
		{
			return [
				'media_type' => 'video',
				'media_url' => $video_url,
				'media_id' => '',
			];
		}

		$youtube = $this->extract_youtube_media($post_text);
		if ($youtube['media_url'] !== '')
		{
			return $youtube;
		}

		$tweet = $this->extract_tweet_media($post_text);
		if ($tweet['media_url'] !== '')
		{
			return $tweet;
		}

		return [
			'media_type' => '',
			'media_url' => '',
			'media_id' => '',
		];
	}

	protected function extract_video_url(string $post_text): string
	{
		if ($post_text === '')
		{
			return '';
		}

		$post_text = $this->remove_quoted_content($post_text);
		foreach ([
			'#<(?:VIDEO|SOURCE)\b[^>]*\bsrc=(["\'])(.*?)\1#is',
			'#\[video(?:=[^\]]*)?\](.*?)\[/video\]#is',
			'#(https?://[^\s\[\]<>"\']+\.(?:mp4|m4v|mov|webm)(?:[?\#][^\s\[\]<>"\']*)?)#i',
		] as $pattern)
		{
			if (!preg_match_all($pattern, $post_text, $matches, PREG_SET_ORDER))
			{
				continue;
			}

			foreach ($matches as $match)
			{
				$url = $this->normalize_image_url($match[2] ?? $match[1] ?? '');
				if ($url !== '' && $this->is_allowed_image_url($url) && $this->is_trusted_uploaded_video_url($url))
				{
					return $url;
				}
			}
		}

		return '';
	}

	protected function extract_youtube_media(string $post_text): array
	{
		$empty = [
			'media_type' => '',
			'media_url' => '',
			'media_id' => '',
		];

		if ($post_text === '')
		{
			return $empty;
		}

		$post_text = $this->remove_quoted_content($post_text);
		if (preg_match('#<YOUTUBE\b[^>]*\bid=(["\'])([A-Za-z0-9_-]{11})\1#i', $post_text, $match))
		{
			return $this->build_youtube_media((string) $match[2]);
		}

		if (!preg_match_all('#https?://(?:www\.|m\.)?(?:youtube\.com|youtu\.be)/[^\s\[\]<>"\']+#i', $post_text, $matches))
		{
			return $empty;
		}

		foreach ($matches[0] as $url)
		{
			$youtube_id = $this->extract_youtube_id_from_url($url);
			if ($youtube_id !== '')
			{
				return $this->build_youtube_media($youtube_id);
			}
		}

		return $empty;
	}

	protected function build_youtube_media(string $youtube_id): array
	{
		return [
			'media_type' => 'youtube',
			'media_url' => 'https://www.youtube.com/watch?v=' . $youtube_id,
			'media_id' => $youtube_id,
		];
	}

	protected function extract_youtube_id_from_url(string $url): string
	{
		$url = htmlspecialchars_decode(trim($url), ENT_QUOTES | ENT_HTML5);
		$parts = parse_url($url);
		if (empty($parts['host']))
		{
			return '';
		}

		$host = strtolower((string) $parts['host']);
		$path = (string) ($parts['path'] ?? '');
		$youtube_id = '';

		if ($host === 'youtu.be' || $host === 'www.youtu.be' || $host === 'm.youtu.be')
		{
			$youtube_id = ltrim($path, '/');
			$youtube_id = strtok($youtube_id, '/') ?: '';
		}
		else if (preg_match('#(?:^|\.)youtube\.com$#i', $host))
		{
			if ($path === '/watch')
			{
				parse_str((string) ($parts['query'] ?? ''), $query);
				$youtube_id = (string) ($query['v'] ?? '');
			}
			else if (preg_match('#^/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})#', $path, $match))
			{
				$youtube_id = (string) $match[1];
			}
		}

		return preg_match('/^[A-Za-z0-9_-]{11}$/', $youtube_id) ? $youtube_id : '';
	}

	protected function extract_tweet_media(string $post_text): array
	{
		$empty = [
			'media_type' => '',
			'media_url' => '',
			'media_id' => '',
		];

		if ($post_text === '' || !preg_match('#https?://(?:www\.)?(?:twitter\.com|x\.com)/[^/\s\[\]<>"\']+/status(?:es)?/(\d+)#i', $post_text, $match))
		{
			return $empty;
		}

		return [
			'media_type' => 'tweet',
			'media_url' => $this->normalize_image_url($match[0]),
			'media_id' => (string) $match[1],
		];
	}

	protected function get_attachment_media(int $post_id): array
	{
		$media = [
			'image_urls' => [],
			'video_url' => '',
		];

		if ($post_id <= 0 || !defined('ATTACHMENTS_TABLE'))
		{
			return $media;
		}

		$sql = 'SELECT attach_id, mimetype, extension
			FROM ' . ATTACHMENTS_TABLE . '
			WHERE post_msg_id = ' . $post_id . '
				AND in_message = 0
				AND is_orphan = 0
			ORDER BY filetime DESC, attach_id ASC';
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$url = append_sid($this->root_path . 'download/file.' . $this->php_ext, 'id=' . (int) $row['attach_id']);
			if ($this->is_attachment_image($row))
			{
				$media['image_urls'][] = $url;
			}
			else if ($media['video_url'] === '' && $this->is_attachment_video($row))
			{
				$media['video_url'] = $url;
			}

			if (count($media['image_urls']) >= self::MAX_IMAGES && $media['video_url'] !== '')
			{
				break;
			}
		}
		$this->db->sql_freeresult($result);

		return $media;
	}

	protected function is_attachment_image(array $row): bool
	{
		$mimetype = strtolower((string) ($row['mimetype'] ?? ''));
		$extension = strtolower((string) ($row['extension'] ?? ''));

		return strpos($mimetype, 'image/') === 0
			|| in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true);
	}

	protected function is_attachment_video(array $row): bool
	{
		$mimetype = strtolower((string) ($row['mimetype'] ?? ''));
		$extension = strtolower((string) ($row['extension'] ?? ''));

		return strpos($mimetype, 'video/') === 0
			|| in_array($extension, ['mp4', 'm4v', 'mov', 'webm'], true);
	}

	protected function merge_image_urls(array $first, array $second): array
	{
		$image_urls = [];
		$seen = [];
		foreach (array_merge($first, $second) as $url)
		{
			$this->add_image_url($image_urls, $seen, (string) $url);
		}

		return $image_urls;
	}

	protected function add_image_url(array &$image_urls, array &$seen, string $url): void
	{
		$url = $this->normalize_image_url($url);
		if ($url === ''
			|| isset($seen[$url])
			|| !$this->is_allowed_image_url($url)
			|| $this->is_ignored_image_url($url)
			|| count($image_urls) >= self::MAX_IMAGES)
		{
			return;
		}

		$seen[$url] = true;
		$image_urls[] = $url;
	}

	protected function normalize_image_url(string $url): string
	{
		$url = trim(htmlspecialchars_decode(strip_tags($url), ENT_QUOTES | ENT_HTML5));
		$url = trim($url, "\"' \t\n\r\0\x0B");

		if ($url === '' || preg_match('#\s#', $url))
		{
			return '';
		}

		return $url;
	}

	protected function is_allowed_image_url(string $url): bool
	{
		return (bool) preg_match('#^(?:https?:)?//#i', $url)
			|| strpos($url, '/') === 0
			|| strpos($url, './') === 0
			|| strpos($url, '../') === 0;
	}

	protected function is_ignored_image_url(string $url): bool
	{
		return (bool) preg_match('#(?:^https?://fonts\.gstatic\.com/s/e/notoemoji/|/(?:images/)?smilies/)#i', $url);
	}

	protected function is_trusted_uploaded_image_url(string $url): bool
	{
		return $this->is_trusted_uploaded_media_url($url, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif']);
	}

	protected function is_trusted_uploaded_video_url(string $url): bool
	{
		return $this->is_trusted_uploaded_media_url($url, ['mp4', 'm4v', 'mov', 'webm']);
	}

	protected function is_trusted_uploaded_media_url(string $url, array $extensions): bool
	{
		$parts = parse_url(htmlspecialchars_decode($url, ENT_QUOTES | ENT_HTML5));
		if (empty($parts['host']) || empty($parts['path']))
		{
			return false;
		}

		$host = strtolower((string) $parts['host']);
		$path = (string) $parts['path'];
		$current_host = strtolower(preg_replace('/:\d+$/', '', (string) $this->request->server('HTTP_HOST', '')));
		$root_host = preg_replace('/^www\./', '', $current_host);
		$extension_pattern = implode('|', array_map(static function ($extension) {
			return preg_quote($extension, '#');
		}, $extensions));

		if (!preg_match('#\.(?:' . $extension_pattern . ')$#i', $path))
		{
			return false;
		}

		if ($host === 'uploads.themitbbs.com')
		{
			return true;
		}

		if ($root_host !== '' && $host === 'uploads.' . $root_host)
		{
			return true;
		}

		return $root_host !== ''
			&& in_array($host, array_unique([$current_host, $root_host, 'www.' . $root_host]), true)
			&& (bool) preg_match('#^/(?:uploads?|videos?)/#i', $path);
	}

	protected function extract_plain_text(string $post_text, string $bbcode_uid): string
	{
		if ($post_text === '')
		{
			return '';
		}

		$text = $post_text;
		if (!function_exists('decode_message'))
		{
			include_once($this->root_path . 'includes/functions_content.' . $this->php_ext);
		}
		if (function_exists('decode_message'))
		{
			decode_message($text, $bbcode_uid);
		}

		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = $this->remove_quoted_content($text);
		$text = $this->replace_all([
			'#\[code(?:=[^\]]*)?\].*?\[/code\]#si',
			'#<CODE\b[^>]*>.*?</CODE>#si',
			'#\[img(?:=[^\]]*)?\].*?\[/img\]#si',
			'#\[video(?:=[^\]]*)?\].*?\[/video\]#si',
			'#<IMG\b[^>]*>.*?</IMG>#si',
			'#<IMG\b[^>]*>#si',
			'#<VIDEO\b[^>]*>.*?</VIDEO>#si',
			'#<VIDEO\b[^>]*>#si',
			'#<YOUTUBE\b[^>]*>.*?</YOUTUBE>#si',
			'#<TWITTER\b[^>]*>.*?</TWITTER>#si',
			'#https?://[^\s\[\]<>"\']+\.(?:jpe?g|png|gif|webp|avif|mp4|m4v|mov|webm)(?:[?\#][^\s\[\]<>"\']*)?#i',
			'#https?://(?:www\.|m\.)?(?:youtube\.com|youtu\.be)/[^\s\[\]<>"\']+#i',
			'#https?://(?:www\.)?(?:twitter\.com|x\.com)/[^\s\[\]<>"\']+/status(?:es)?/\d+(?:[?\#][^\s\[\]<>"\']*)?#i',
			'#<br\s*/?>#i',
			'#</(?:p|div|li|blockquote|pre|tr|table|h[1-6])>#i',
		], ' ', $text);
		$text = strip_tags($text);
		$text = preg_replace('#\[(?:/?[a-z][a-z0-9_-]*|\*)(?:=[^\]]*)?\]#i', ' ', $text) ?? $text;
		$text = preg_replace('/[\s\p{Zs}]+/u', "\u{3000}", $text) ?? $text;
		$text = trim($text, " \t\n\r\0\x0B" . "\u{3000}");

		return $this->truncate_text($text, self::MAX_TEXT_CHARS);
	}

	protected function remove_quoted_content(string $text): string
	{
		foreach ([
			'#<QUOTE\b[^>]*>.*?</QUOTE>#si',
			'#\[quote(?:=[^\]]*)?\].*?\[/quote\]#si',
		] as $pattern)
		{
			do
			{
				$previous = $text;
				$text = preg_replace($pattern, '', $text);
				if ($text === null)
				{
					return $previous;
				}
			}
			while ($text !== $previous);
		}

		return $text;
	}

	protected function replace_all(array $patterns, string $replacement, string $text): string
	{
		foreach ($patterns as $pattern)
		{
			$updated = preg_replace($pattern, $replacement, $text);
			if (is_string($updated))
			{
				$text = $updated;
			}
		}

		return $text;
	}

	protected function truncate_text(string $text, int $max_chars): string
	{
		if ($max_chars <= 0)
		{
			return '';
		}

		if (function_exists('utf8_strlen') && function_exists('utf8_substr'))
		{
			return utf8_strlen($text) > $max_chars ? utf8_substr($text, 0, $max_chars) : $text;
		}

		if (function_exists('mb_strlen') && function_exists('mb_substr'))
		{
			return mb_strlen($text, 'UTF-8') > $max_chars ? mb_substr($text, 0, $max_chars, 'UTF-8') : $text;
		}

		return strlen($text) > $max_chars ? substr($text, 0, $max_chars) : $text;
	}
}
