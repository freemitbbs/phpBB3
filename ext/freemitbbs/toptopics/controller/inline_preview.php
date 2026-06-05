<?php

namespace freemitbbs\toptopics\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class inline_preview
{
	private const CACHE_PREFIX = '_freemitbbs_toptopics_inline_preview_';
	private const CACHE_REVISION = 9;
	private const CACHE_SECONDS = 600;
	private const MEDIA_SITE_TAGS_CACHE_KEY = '_freemitbbs_toptopics_inline_preview_media_site_tags';
	private const MAX_BATCH_TOPICS = 40;
	private const MAX_IMAGES = 8;
	private const MAX_TEXT_CHARS = 300;

	protected \phpbb\auth\auth $auth;
	protected \phpbb\cache\service $cache;
	protected \phpbb\content_visibility $content_visibility;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\request\request_interface $request;
	protected string $root_path;
	protected string $php_ext;
	protected ?array $media_site_tags = null;
	protected ?\phpbb\auth\auth $anonymous_auth = null;
	protected array $anonymous_forum_read_cache = [];

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
		if ($topic_id <= 0)
		{
			return $this->json_response(['status' => 404], 404);
		}

		$rows = $this->get_preview_rows([$topic_id]);
		if (!isset($rows[$topic_id]))
		{
			return $this->json_response(['status' => 404], 404);
		}

		$preview = $this->build_preview_for_row($rows[$topic_id]);
		if (!$preview)
		{
			return $this->json_response(['status' => 404], 404);
		}

		return $this->json_response($preview, 200, $this->is_public_cacheable_row($rows[$topic_id]));
	}

	public function batch(): JsonResponse
	{
		$topic_ids = $this->parse_topic_ids($this->request->variable('topic_ids', ''));
		if (empty($topic_ids))
		{
			return $this->json_response([]);
		}

		$rows = $this->get_preview_rows($topic_ids);
		$response = [];
		$pending_rows = [];
		$public_cacheable = true;
		foreach ($topic_ids as $topic_id)
		{
			if (isset($rows[$topic_id]))
			{
				if (!$this->is_public_cacheable_row($rows[$topic_id]))
				{
					$public_cacheable = false;
				}

				$cached = $this->get_cached_preview_for_row($rows[$topic_id]);
				if ($cached)
				{
					$response[(string) $topic_id] = $cached;
				}
				else
				{
					$pending_rows[$topic_id] = $rows[$topic_id];
				}
			}
			else
			{
				$response[(string) $topic_id] = ['status' => 404];
				$public_cacheable = false;
			}
		}

		$attachment_media_by_post_id = $this->get_attachment_media_for_rows($pending_rows);
		foreach ($pending_rows as $topic_id => $row)
		{
			$post_id = (int) $row['post_id'];
			$preview = $this->build_preview_for_row($row, $attachment_media_by_post_id[$post_id] ?? null, true);
			$response[(string) $topic_id] = $preview ?: ['status' => 404];
			if (!$preview)
			{
				$public_cacheable = false;
			}
		}

		return $this->json_response($response, 200, $public_cacheable);
	}

	protected function json_response($data, int $status = 200, bool $public_cacheable = false): JsonResponse
	{
		$response = new JsonResponse(null, $status);
		if (\defined('JSON_INVALID_UTF8_SUBSTITUTE'))
		{
			$response->setEncodingOptions($response->getEncodingOptions() | \JSON_INVALID_UTF8_SUBSTITUTE);
		}

		$response->setData($data);

		return $this->apply_cache_headers($response, $status, $public_cacheable);
	}

	protected function apply_cache_headers(JsonResponse $response, int $status, bool $public_cacheable): JsonResponse
	{
		if ($status !== 200 || !$public_cacheable)
		{
			return $response;
		}

		$response->headers->remove('Set-Cookie');
		if (!\headers_sent())
		{
			\header_remove('Set-Cookie');
		}

		$response->headers->set('Cache-Control', 'public, max-age=60, s-maxage=' . self::CACHE_SECONDS);
		$response->headers->set('CDN-Cache-Control', 'public, max-age=' . self::CACHE_SECONDS);
		$response->headers->set('Cloudflare-CDN-Cache-Control', 'public, max-age=' . self::CACHE_SECONDS);
		$response->headers->set('Vary', 'Accept-Encoding');

		return $response;
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
				p.post_id, p.poster_id, p.post_visibility, p.post_text, p.bbcode_uid, p.bbcode_bitfield,
				p.enable_bbcode, p.enable_smilies, p.enable_magic_url, p.post_attachment,
				p.post_time, p.post_edit_time, f.forum_password
			FROM ' . TOPICS_TABLE . ' t
			INNER JOIN ' . POSTS_TABLE . ' p
				ON p.post_id = t.topic_first_post_id
			INNER JOIN ' . FORUMS_TABLE . ' f
				ON f.forum_id = t.forum_id
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

	protected function is_public_cacheable_row(array $row): bool
	{
		return \defined('ITEM_APPROVED')
			&& (int) ($row['topic_visibility'] ?? 0) === ITEM_APPROVED
			&& (int) ($row['post_visibility'] ?? 0) === ITEM_APPROVED
			&& (string) ($row['forum_password'] ?? '') === ''
			&& $this->anonymous_can_read_forum((int) ($row['forum_id'] ?? 0));
	}

	protected function anonymous_can_read_forum(int $forum_id): bool
	{
		if ($forum_id <= 0 || !\defined('ANONYMOUS'))
		{
			return false;
		}

		if (!array_key_exists($forum_id, $this->anonymous_forum_read_cache))
		{
			$anonymous_auth = $this->get_anonymous_auth();
			$this->anonymous_forum_read_cache[$forum_id] = $anonymous_auth !== null
				&& (bool) $anonymous_auth->acl_get('f_read', $forum_id);
		}

		return $this->anonymous_forum_read_cache[$forum_id];
	}

	protected function get_anonymous_auth(): ?\phpbb\auth\auth
	{
		if ($this->anonymous_auth !== null)
		{
			return $this->anonymous_auth;
		}

		$anonymous_data = $this->auth->obtain_user_data(ANONYMOUS);
		if (!is_array($anonymous_data))
		{
			return null;
		}

		$this->anonymous_auth = clone $this->auth;
		$this->anonymous_auth->acl($anonymous_data);

		return $this->anonymous_auth;
	}

	protected function get_cached_preview_for_row(array $row): array|false
	{
		$cached = $this->cache->get($this->build_cache_key($row));

		return is_array($cached) ? $cached : false;
	}

	protected function build_preview_for_row(array $row, ?array $prefetched_attachment_media = null, bool $skip_cache_lookup = false): array|false
	{
		if (!$skip_cache_lookup)
		{
			$cached = $this->get_cached_preview_for_row($row);
			if ($cached)
			{
				return $cached;
			}
		}

		$image_urls = $this->extract_image_urls((string) $row['post_text']);
		$embedded_media = $this->extract_embedded_media((string) $row['post_text']);
		if (!empty($row['post_attachment']))
		{
			$attachment_media = $prefetched_attachment_media ?? $this->get_attachment_media((int) $row['post_id']);
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
		$rendered_html = $this->should_build_rendered_preview_html($row, $image_urls, $embedded_media)
			? $this->build_rendered_preview_html($row)
			: '';
		$preview = [
			'status' => 200,
			'topic_id' => (int) $row['topic_id'],
			'plain_text' => $this->extract_plain_text((string) $row['post_text'], (string) $row['bbcode_uid']),
			'rendered_html' => $rendered_html,
			'image_urls' => $image_urls,
			'media_type' => $media_type,
			'media_url' => $media_url,
			'media_id' => empty($image_urls) ? $embedded_media['media_id'] : '',
			'media_urls' => $image_urls,
		];

		if ($preview['plain_text'] === '' && empty($preview['image_urls']) && $preview['media_url'] === '' && $preview['rendered_html'] === '')
		{
			return false;
		}

		$this->cache->put($this->build_cache_key($row), $preview, self::CACHE_SECONDS);

		return $preview;
	}

	protected function should_build_rendered_preview_html(array $row, array $image_urls, array $embedded_media): bool
	{
		return empty($image_urls)
			&& ($embedded_media['media_type'] ?? '') === ''
			&& $this->has_rendered_media_candidate((string) ($row['post_text'] ?? ''));
	}

	protected function has_rendered_media_candidate(string $post_text): bool
	{
		if ($post_text === '')
		{
			return false;
		}

		$post_text = $this->remove_quoted_content($post_text);
		if (preg_match('#<MEDIA\b#i', $post_text))
		{
			return true;
		}

		if (!preg_match_all('#<([A-Z][A-Z0-9_]*)\b#', $post_text, $matches))
		{
			return false;
		}

		$media_site_tags = $this->get_media_site_tags();
		foreach ($matches[1] as $tag_name)
		{
			if (isset($media_site_tags[strtoupper((string) $tag_name)]))
			{
				return true;
			}
		}

		return false;
	}

	protected function get_media_site_tags(): array
	{
		if ($this->media_site_tags !== null)
		{
			return $this->media_site_tags;
		}

		$cached = $this->cache->get(self::MEDIA_SITE_TAGS_CACHE_KEY);
		if (is_array($cached))
		{
			$this->media_site_tags = $cached;

			return $this->media_site_tags;
		}

		$site_ids = $this->get_configured_media_site_ids();
		$media_site_tags = [];
		foreach ($site_ids as $site_id)
		{
			$site_id = (string) $site_id;
			if (preg_match('/^[a-z0-9_]+$/i', $site_id))
			{
				$media_site_tags[strtoupper($site_id)] = true;
			}
		}

		$this->media_site_tags = $media_site_tags;
		$this->cache->put(self::MEDIA_SITE_TAGS_CACHE_KEY, $this->media_site_tags, self::CACHE_SECONDS);

		return $this->media_site_tags;
	}

	protected function get_configured_media_site_ids(): array
	{
		if (!defined('CONFIG_TEXT_TABLE'))
		{
			return [];
		}

		$sql = 'SELECT config_value
			FROM ' . CONFIG_TEXT_TABLE . "
			WHERE config_name = 'media_embed_sites'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (!$row || empty($row['config_value']))
		{
			return [];
		}

		$site_ids = json_decode((string) $row['config_value'], true);

		return is_array($site_ids) ? $site_ids : [];
	}

	protected function build_rendered_preview_html(array $row): string
	{
		$post_text = (string) ($row['post_text'] ?? '');
		if ($post_text === '')
		{
			return '';
		}

		if (!function_exists('generate_text_for_display'))
		{
			include_once($this->root_path . 'includes/functions_content.' . $this->php_ext);
		}
		if (!function_exists('generate_text_for_display'))
		{
			return '';
		}

		$parse_flags = ((int) ($row['enable_bbcode'] ?? 1) ? OPTION_FLAG_BBCODE : 0)
			| ((int) ($row['enable_smilies'] ?? 1) ? OPTION_FLAG_SMILIES : 0)
			| ((int) ($row['enable_magic_url'] ?? 1) ? OPTION_FLAG_LINKS : 0);
		$rendered_html = generate_text_for_display(
			$post_text,
			(string) ($row['bbcode_uid'] ?? ''),
			(string) ($row['bbcode_bitfield'] ?? ''),
			$parse_flags,
			true
		);
		$rendered_html = $this->remove_rendered_quoted_content($rendered_html);
		$rendered_html = trim($rendered_html);

		return $rendered_html === '' ? '' : '<div class="topic_preview_content">' . $rendered_html . '</div>';
	}

	protected function remove_rendered_quoted_content(string $html): string
	{
		foreach ([
			'#<blockquote\b[^>]*>.*?</blockquote>#si',
			'#<div\b[^>]*class=(["\'])(?:(?!\1).)*\bimcger-quote\b(?:(?!\1).)*\1[^>]*>.*?</div>#si',
		] as $pattern)
		{
			do
			{
				$previous = $html;
				$html = preg_replace($pattern, '', $html);
				if ($html === null)
				{
					return $previous;
				}
			}
			while ($html !== $previous);
		}

		return $html;
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

		$bilibili = $this->extract_bilibili_media($post_text);
		if ($bilibili['media_url'] !== '')
		{
			return $bilibili;
		}

		$tiktok = $this->extract_tiktok_media($post_text);
		if ($tiktok['media_url'] !== '')
		{
			return $tiktok;
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

	protected function extract_bilibili_media(string $post_text): array
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
		if (preg_match('#<BILIBILI\b[^>]*\bid=(["\'])(BV[0-9A-Za-z]+)\1#i', $post_text, $match))
		{
			return $this->build_bilibili_media((string) $match[2]);
		}

		foreach ([
			'#(?:https?:)?//(?:www\.|m\.)?bilibili\.com/video/[^\s\[\]<>"\']+#i',
			'#(?:https?:)?//player\.bilibili\.com/player\.html\?[^\s\[\]<>"\']+#i',
		] as $pattern)
		{
			if (!preg_match_all($pattern, $post_text, $matches))
			{
				continue;
			}

			foreach ($matches[0] as $url)
			{
				$bvid = $this->extract_bilibili_id_from_url($url);
				if ($bvid !== '')
				{
					return $this->build_bilibili_media($bvid);
				}
			}
		}

		return $empty;
	}

	protected function build_bilibili_media(string $bvid): array
	{
		$bvid = $this->normalize_bilibili_bvid($bvid);
		if ($bvid === '')
		{
			return [
				'media_type' => '',
				'media_url' => '',
				'media_id' => '',
			];
		}

		return [
			'media_type' => 'bilibili',
			'media_url' => 'https://www.bilibili.com/video/' . $bvid,
			'media_id' => $bvid,
		];
	}

	protected function extract_bilibili_id_from_url(string $url): string
	{
		$url = htmlspecialchars_decode(trim($url), ENT_QUOTES | ENT_HTML5);
		$url = trim($url, "\"' \t\n\r\0\x0B");
		$parts = parse_url($url);
		if (empty($parts['host']))
		{
			return '';
		}

		$host = strtolower((string) $parts['host']);
		$path = (string) ($parts['path'] ?? '');

		if (preg_match('#(?:^|\.)bilibili\.com$#i', $host)
			&& preg_match('#^/video/(BV[0-9A-Za-z]+)#i', $path, $match))
		{
			return $this->normalize_bilibili_bvid((string) $match[1]);
		}

		if ($host === 'player.bilibili.com' && $path === '/player.html')
		{
			parse_str((string) ($parts['query'] ?? ''), $query);

			return $this->normalize_bilibili_bvid((string) ($query['bvid'] ?? ''));
		}

		return '';
	}

	protected function normalize_bilibili_bvid(string $bvid): string
	{
		$bvid = trim($bvid);
		if (preg_match('/^BV[0-9A-Za-z]+$/i', $bvid))
		{
			return 'BV' . substr($bvid, 2);
		}

		return '';
	}

	protected function extract_tiktok_media(string $post_text): array
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
		if (preg_match('#<TIKTOK\b[^>]*\bid=(["\'])(\d+)\1#i', $post_text, $match))
		{
			return $this->build_tiktok_media((string) $match[2]);
		}

		foreach ([
			'#(?:https?:)?//(?:www\.|m\.)?tiktok\.com/[^\s\[\]<>"\']+#i',
			'#(?:https?:)?//(?:vm\.|vt\.)tiktok\.com/[^\s\[\]<>"\']+#i',
		] as $pattern)
		{
			if (!preg_match_all($pattern, $post_text, $matches))
			{
				continue;
			}

			foreach ($matches[0] as $url)
			{
				$tiktok_id = $this->extract_tiktok_id_from_url($url);
				if ($tiktok_id !== '')
				{
					return $this->build_tiktok_media($tiktok_id);
				}
			}
		}

		return $empty;
	}

	protected function build_tiktok_media(string $tiktok_id): array
	{
		$tiktok_id = $this->normalize_tiktok_id($tiktok_id);
		if ($tiktok_id === '')
		{
			return [
				'media_type' => '',
				'media_url' => '',
				'media_id' => '',
			];
		}

		return [
			'media_type' => 'tiktok',
			'media_url' => 'https://www.tiktok.com/video/' . $tiktok_id,
			'media_id' => $tiktok_id,
		];
	}

	protected function extract_tiktok_id_from_url(string $url): string
	{
		$url = htmlspecialchars_decode(trim($url), ENT_QUOTES | ENT_HTML5);
		$url = trim($url, "\"' \t\n\r\0\x0B");
		$parts = parse_url($url);
		if (empty($parts['host']))
		{
			return '';
		}

		$host = strtolower((string) $parts['host']);
		$path = (string) ($parts['path'] ?? '');
		if (!preg_match('#(?:^|\.)tiktok\.com$#i', $host))
		{
			return '';
		}

		if (preg_match('#^/(?:@[^/]+/video|v|i18n/share/video|embed(?:/v2)?)/(\d+)#i', $path, $match))
		{
			return $this->normalize_tiktok_id((string) $match[1]);
		}

		return '';
	}

	protected function normalize_tiktok_id(string $tiktok_id): string
	{
		$tiktok_id = trim($tiktok_id);

		return preg_match('/^\d{6,}$/', $tiktok_id) ? $tiktok_id : '';
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
		$media_by_post_id = $this->get_attachment_media_for_posts([$post_id]);

		return $media_by_post_id[$post_id] ?? $this->empty_attachment_media();
	}

	protected function get_attachment_media_for_rows(array $rows): array
	{
		$post_ids = [];
		foreach ($rows as $row)
		{
			if (!empty($row['post_attachment']))
			{
				$post_ids[] = (int) $row['post_id'];
			}
		}

		return $this->get_attachment_media_for_posts($post_ids);
	}

	protected function get_attachment_media_for_posts(array $post_ids): array
	{
		$post_ids = array_values(array_unique(array_filter(array_map('intval', $post_ids), static function ($post_id) {
			return $post_id > 0;
		})));
		$media_by_post_id = [];
		foreach ($post_ids as $post_id)
		{
			$media_by_post_id[$post_id] = $this->empty_attachment_media();
		}

		if (empty($post_ids) || !defined('ATTACHMENTS_TABLE'))
		{
			return $media_by_post_id;
		}

		$sql = 'SELECT post_msg_id, attach_id, mimetype, extension
			FROM ' . ATTACHMENTS_TABLE . '
			WHERE ' . $this->db->sql_in_set('post_msg_id', $post_ids) . '
				AND in_message = 0
				AND is_orphan = 0
			ORDER BY post_msg_id ASC, filetime DESC, attach_id ASC';
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$post_id = (int) $row['post_msg_id'];
			if (!isset($media_by_post_id[$post_id]))
			{
				continue;
			}

			$url = append_sid($this->root_path . 'download/file.' . $this->php_ext, 'id=' . (int) $row['attach_id']);
			if ($this->is_attachment_image($row) && count($media_by_post_id[$post_id]['image_urls']) < self::MAX_IMAGES)
			{
				$media_by_post_id[$post_id]['image_urls'][] = $url;
			}
			else if ($media_by_post_id[$post_id]['video_url'] === '' && $this->is_attachment_video($row))
			{
				$media_by_post_id[$post_id]['video_url'] = $url;
			}
		}
		$this->db->sql_freeresult($result);

		return $media_by_post_id;
	}

	protected function empty_attachment_media(): array
	{
		return [
			'image_urls' => [],
			'video_url' => '',
		];
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
			'#<BILIBILI\b[^>]*>.*?</BILIBILI>#si',
			'#<TIKTOK\b[^>]*>.*?</TIKTOK>#si',
			'#<TWITTER\b[^>]*>.*?</TWITTER>#si',
			'#https?://[^\s\[\]<>"\']+\.(?:jpe?g|png|gif|webp|avif|mp4|m4v|mov|webm)(?:[?\#][^\s\[\]<>"\']*)?#i',
			'#https?://(?:www\.|m\.)?(?:youtube\.com|youtu\.be)/[^\s\[\]<>"\']+#i',
			'#(?:https?:)?//(?:www\.|m\.)?bilibili\.com/video/[^\s\[\]<>"\']+#i',
			'#(?:https?:)?//player\.bilibili\.com/player\.html\?[^\s\[\]<>"\']+#i',
			'#(?:https?:)?//(?:www\.|m\.|vm\.|vt\.)?tiktok\.com/[^\s\[\]<>"\']+#i',
			'#https?://(?:www\.)?(?:twitter\.com|x\.com)/[^\s\[\]<>"\']+/status(?:es)?/\d+(?:[?\#][^\s\[\]<>"\']*)?#i',
			'#<br\s*/?>#i',
			'#</(?:p|div|li|blockquote|pre|tr|table|h[1-6])>#i',
		], ' ', $text);
		$text = strip_tags($text);
		$text = preg_replace('#\[(?:/?[a-z][a-z0-9_-]*|\*)(?:=[^\]]*)?\]#i', ' ', $text) ?? $text;
		$text = preg_replace('/[\s\p{Zs}]+/u', "\u{3000}", $text) ?? $text;
		$text = $this->trim_unicode_whitespace($text);

		return $this->truncate_text($text, self::MAX_TEXT_CHARS);
	}

	protected function trim_unicode_whitespace(string $text): string
	{
		$trimmed = preg_replace('/^[\s\p{Zs}]+|[\s\p{Zs}]+$/u', '', $text);

		return is_string($trimmed) ? $trimmed : trim($text);
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
