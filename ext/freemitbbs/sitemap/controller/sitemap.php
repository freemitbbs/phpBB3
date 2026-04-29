<?php

namespace freemitbbs\sitemap\controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class sitemap
{
	private const TOPICS_PER_SITEMAP = 45000;

	protected \phpbb\config\config $config;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\extension\manager $extension_manager;
	protected \phpbb\user $user;
	protected string $table_prefix;
	protected string $php_ext;
	protected ?\phpbb\auth\auth $anonymous_auth = null;
	protected ?array $public_forum_ids = null;
	protected ?array $public_post_forum_ids = null;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\controller\helper $helper,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\extension\manager $extension_manager,
		\phpbb\user $user,
		string $table_prefix,
		string $php_ext
	)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->db = $db;
		$this->extension_manager = $extension_manager;
		$this->user = $user;
		$this->table_prefix = $table_prefix;
		$this->php_ext = $php_ext;
	}

	public function index(): Response
	{
		$sitemaps = [
			[
				'loc' => $this->absolute_route('freemitbbs_sitemap_pages'),
			],
		];

		$forum_lastmod = $this->get_forum_lastmod();
		if ($forum_lastmod > 0)
		{
			$sitemaps[] = [
				'loc' => $this->absolute_route('freemitbbs_sitemap_forums'),
				'lastmod' => $this->format_lastmod($forum_lastmod),
			];
		}

		$topic_count = $this->count_topics();
		$topic_lastmod = $this->get_topic_lastmod();
		$topic_pages = (int) ceil($topic_count / self::TOPICS_PER_SITEMAP);
		for ($page = 1; $page <= $topic_pages; $page++)
		{
			$sitemap = [
				'loc' => $this->absolute_route('freemitbbs_sitemap_topics', ['page' => $page]),
			];
			if ($topic_lastmod > 0)
			{
				$sitemap['lastmod'] = $this->format_lastmod($topic_lastmod);
			}
			$sitemaps[] = $sitemap;
		}

		$blog_lastmod = $this->get_blog_lastmod();
		if ($blog_lastmod > 0)
		{
			$sitemaps[] = [
				'loc' => $this->absolute_route('freemitbbs_sitemap_blog'),
				'lastmod' => $this->format_lastmod($blog_lastmod),
			];
		}

		return $this->xml_response($this->sitemap_index_xml($sitemaps));
	}

	public function pages(): Response
	{
		$urls = [
			[
				'loc' => generate_board_url() . '/',
			],
		];

		if ($this->is_blog_public())
		{
			$urls[] = [
				'loc' => $this->absolute_route('freemitbbs_blog_index'),
				'lastmod' => $this->optional_lastmod($this->get_blog_lastmod()),
			];
		}

		return $this->xml_response($this->urlset_xml($this->filter_empty_values($urls)));
	}

	public function forums(): Response
	{
		$forum_ids = $this->get_public_forum_ids(false);
		$urls = [];

		if (!empty($forum_ids))
		{
			$sql = 'SELECT forum_id, forum_last_post_time
				FROM ' . FORUMS_TABLE . '
				WHERE ' . $this->db->sql_in_set('forum_id', $forum_ids) . '
					AND forum_type <> ' . FORUM_LINK . '
				ORDER BY left_id ASC';
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$lastmod = (int) $row['forum_last_post_time'];
				$urls[] = [
					'loc' => $this->board_url('viewforum.' . $this->php_ext, 'f=' . (int) $row['forum_id']),
					'lastmod' => $this->optional_lastmod($lastmod),
				];
			}
			$this->db->sql_freeresult($result);
		}

		return $this->xml_response($this->urlset_xml($this->filter_empty_values($urls)));
	}

	public function topics(int $page): Response
	{
		$page = max(1, $page);
		$topic_count = $this->count_topics();
		$max_page = max(1, (int) ceil($topic_count / self::TOPICS_PER_SITEMAP));

		if ($page > $max_page)
		{
			throw new \phpbb\exception\http_exception(404, 'NOT_FOUND');
		}

		$forum_ids = $this->get_public_topic_forum_ids();
		$urls = [];

		if (!empty($forum_ids) && $topic_count > 0)
		{
			$sql = 'SELECT topic_id, topic_time, topic_last_post_time
				FROM ' . TOPICS_TABLE . '
				WHERE ' . $this->public_topic_where_sql($forum_ids) . '
				ORDER BY topic_last_post_time DESC, topic_id DESC';
			$result = $this->db->sql_query_limit($sql, self::TOPICS_PER_SITEMAP, ($page - 1) * self::TOPICS_PER_SITEMAP);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$lastmod = max((int) $row['topic_last_post_time'], (int) $row['topic_time']);
				$urls[] = [
					'loc' => $this->board_url('viewtopic.' . $this->php_ext, 't=' . (int) $row['topic_id']),
					'lastmod' => $this->optional_lastmod($lastmod),
				];
			}
			$this->db->sql_freeresult($result);
		}

		return $this->xml_response($this->urlset_xml($this->filter_empty_values($urls)));
	}

	public function blog(): Response
	{
		$forum_id = $this->get_public_blog_forum_id();
		$urls = [];

		if ($forum_id > 0)
		{
			$urls = array_merge($urls, $this->get_blog_user_urls($forum_id));
			$urls = array_merge($urls, $this->get_blog_entry_urls($forum_id));
		}

		return $this->xml_response($this->urlset_xml($this->filter_empty_values($urls)));
	}

	protected function get_blog_user_urls(int $forum_id): array
	{
		$urls = [];
		$sql = 'SELECT t.topic_poster, MAX(t.topic_last_post_time) AS last_post_time, MAX(t.topic_time) AS topic_time
			FROM ' . TOPICS_TABLE . ' t
			LEFT JOIN ' . $this->blog_topics_table() . ' bt
				ON bt.topic_id = t.topic_id
			WHERE ' . $this->public_blog_topic_where_sql($forum_id) . '
			GROUP BY t.topic_poster
			ORDER BY last_post_time DESC';
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$lastmod = max((int) $row['last_post_time'], (int) $row['topic_time']);
			$urls[] = [
				'loc' => $this->absolute_route('freemitbbs_blog_user', ['user_id' => (int) $row['topic_poster']]),
				'lastmod' => $this->optional_lastmod($lastmod),
			];
		}
		$this->db->sql_freeresult($result);

		return $urls;
	}

	protected function get_blog_entry_urls(int $forum_id): array
	{
		$urls = [];
		$sql = 'SELECT t.topic_id, t.topic_time, t.topic_last_post_time
			FROM ' . TOPICS_TABLE . ' t
			LEFT JOIN ' . $this->blog_topics_table() . ' bt
				ON bt.topic_id = t.topic_id
			WHERE ' . $this->public_blog_topic_where_sql($forum_id) . '
			ORDER BY t.topic_last_post_time DESC, t.topic_id DESC';
		$result = $this->db->sql_query_limit($sql, self::TOPICS_PER_SITEMAP);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$lastmod = max((int) $row['topic_last_post_time'], (int) $row['topic_time']);
			$urls[] = [
				'loc' => $this->absolute_route('freemitbbs_blog_entry', ['entry_id' => (int) $row['topic_id']]),
				'lastmod' => $this->optional_lastmod($lastmod),
			];
		}
		$this->db->sql_freeresult($result);

		return $urls;
	}

	protected function count_topics(): int
	{
		$forum_ids = $this->get_public_topic_forum_ids();
		if (empty($forum_ids))
		{
			return 0;
		}

		$sql = 'SELECT COUNT(*) AS topic_count
			FROM ' . TOPICS_TABLE . '
			WHERE ' . $this->public_topic_where_sql($forum_ids);
		$result = $this->db->sql_query($sql);
		$count = (int) $this->db->sql_fetchfield('topic_count');
		$this->db->sql_freeresult($result);

		return $count;
	}

	protected function get_forum_lastmod(): int
	{
		$forum_ids = $this->get_public_forum_ids(false);
		if (empty($forum_ids))
		{
			return 0;
		}

		$sql = 'SELECT MAX(forum_last_post_time) AS lastmod
			FROM ' . FORUMS_TABLE . '
			WHERE ' . $this->db->sql_in_set('forum_id', $forum_ids) . '
				AND forum_type <> ' . FORUM_LINK;
		$result = $this->db->sql_query($sql);
		$lastmod = (int) $this->db->sql_fetchfield('lastmod');
		$this->db->sql_freeresult($result);

		return $lastmod;
	}

	protected function get_topic_lastmod(): int
	{
		$forum_ids = $this->get_public_topic_forum_ids();
		if (empty($forum_ids))
		{
			return 0;
		}

		$sql = 'SELECT MAX(topic_last_post_time) AS lastmod
			FROM ' . TOPICS_TABLE . '
			WHERE ' . $this->public_topic_where_sql($forum_ids);
		$result = $this->db->sql_query($sql);
		$lastmod = (int) $this->db->sql_fetchfield('lastmod');
		$this->db->sql_freeresult($result);

		return $lastmod;
	}

	protected function get_blog_lastmod(): int
	{
		$forum_id = $this->get_public_blog_forum_id();
		if ($forum_id <= 0)
		{
			return 0;
		}

		$sql = 'SELECT MAX(t.topic_last_post_time) AS lastmod
			FROM ' . TOPICS_TABLE . ' t
			LEFT JOIN ' . $this->blog_topics_table() . ' bt
				ON bt.topic_id = t.topic_id
			WHERE ' . $this->public_blog_topic_where_sql($forum_id);
		$result = $this->db->sql_query($sql);
		$lastmod = (int) $this->db->sql_fetchfield('lastmod');
		$this->db->sql_freeresult($result);

		return $lastmod;
	}

	protected function get_public_topic_forum_ids(): array
	{
		$forum_ids = $this->get_public_forum_ids(true);
		$blog_forum_id = $this->get_public_blog_forum_id();
		if ($blog_forum_id > 0)
		{
			$forum_ids = array_values(array_diff($forum_ids, [$blog_forum_id]));
		}

		return $forum_ids;
	}

	protected function get_public_forum_ids(bool $post_only): array
	{
		if ($post_only && $this->public_post_forum_ids !== null)
		{
			return $this->public_post_forum_ids;
		}
		if (!$post_only && $this->public_forum_ids !== null)
		{
			return $this->public_forum_ids;
		}

		$auth = $this->get_anonymous_auth();
		$read_ids = array_keys($auth->acl_getf('f_read', true));
		$list_ids = array_keys($auth->acl_getf('f_list', true));
		$forum_ids = array_values(array_unique(array_map('intval', array_intersect($read_ids, $list_ids))));

		if (empty($forum_ids))
		{
			return $post_only ? ($this->public_post_forum_ids = []) : ($this->public_forum_ids = []);
		}

		$sql = 'SELECT forum_id
			FROM ' . FORUMS_TABLE . '
			WHERE ' . $this->db->sql_in_set('forum_id', $forum_ids) . "
				AND forum_password = ''
				AND forum_type <> " . FORUM_LINK .
				($post_only ? ' AND forum_type = ' . FORUM_POST : '') . '
			ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql);

		$public_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$public_ids[] = (int) $row['forum_id'];
		}
		$this->db->sql_freeresult($result);

		if ($post_only)
		{
			return $this->public_post_forum_ids = $public_ids;
		}

		return $this->public_forum_ids = $public_ids;
	}

	protected function get_anonymous_auth(): \phpbb\auth\auth
	{
		if ($this->anonymous_auth !== null)
		{
			return $this->anonymous_auth;
		}

		$auth = new \phpbb\auth\auth();
		$user_data = $auth->obtain_user_data(ANONYMOUS);
		$auth->acl($user_data);

		return $this->anonymous_auth = $auth;
	}

	protected function get_public_blog_forum_id(): int
	{
		$forum_id = (int) ($this->config['freemitbbs_blog_forum_id'] ?? 0);
		if ($forum_id <= 0 || !$this->is_extension_enabled('freemitbbs/blog'))
		{
			return 0;
		}

		return in_array($forum_id, $this->get_public_forum_ids(true), true) ? $forum_id : 0;
	}

	protected function is_blog_public(): bool
	{
		return $this->get_public_blog_forum_id() > 0;
	}

	protected function public_topic_where_sql(array $forum_ids): string
	{
		return $this->db->sql_in_set('forum_id', $forum_ids) . '
			AND topic_moved_id = 0
			AND topic_visibility = ' . ITEM_APPROVED;
	}

	protected function public_blog_topic_where_sql(int $forum_id): string
	{
		return 't.forum_id = ' . (int) $forum_id . '
			AND t.topic_moved_id = 0
			AND t.topic_visibility = ' . ITEM_APPROVED . '
			AND (bt.is_draft IS NULL OR bt.is_draft = 0)';
	}

	protected function blog_topics_table(): string
	{
		return $this->table_prefix . 'blog_topics';
	}

	protected function is_extension_enabled(string $name): bool
	{
		return $this->extension_manager->is_enabled($name);
	}

	protected function absolute_route(string $route, array $params = []): string
	{
		return $this->helper->route($route, $params, true, '', UrlGeneratorInterface::ABSOLUTE_URL);
	}

	protected function board_url(string $path, string $query = ''): string
	{
		$url = generate_board_url() . '/' . ltrim($path, '/');
		if ($query !== '')
		{
			$url .= '?' . $query;
		}

		return $url;
	}

	protected function optional_lastmod(int $timestamp): string
	{
		return $timestamp > 0 ? $this->format_lastmod($timestamp) : '';
	}

	protected function format_lastmod(int $timestamp): string
	{
		return gmdate('Y-m-d\TH:i:s+00:00', $timestamp);
	}

	protected function sitemap_index_xml(array $sitemaps): string
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ($sitemaps as $sitemap)
		{
			$xml .= "\t<sitemap>\n";
			$xml .= "\t\t<loc>" . $this->xml_escape($sitemap['loc']) . "</loc>\n";
			if (!empty($sitemap['lastmod']))
			{
				$xml .= "\t\t<lastmod>" . $this->xml_escape($sitemap['lastmod']) . "</lastmod>\n";
			}
			$xml .= "\t</sitemap>\n";
		}

		$xml .= '</sitemapindex>' . "\n";

		return $xml;
	}

	protected function urlset_xml(array $urls): string
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ($urls as $url)
		{
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . $this->xml_escape($url['loc']) . "</loc>\n";
			if (!empty($url['lastmod']))
			{
				$xml .= "\t\t<lastmod>" . $this->xml_escape($url['lastmod']) . "</lastmod>\n";
			}
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>' . "\n";

		return $xml;
	}

	protected function xml_response(string $xml): Response
	{
		return new Response($xml, 200, [
			'Content-Type' => 'application/xml; charset=UTF-8',
			'Cache-Control' => 'public, max-age=900',
		]);
	}

	protected function xml_escape(string $value): string
	{
		return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
	}

	protected function filter_empty_values(array $items): array
	{
		return array_map(static function (array $item): array {
			return array_filter($item, static function ($value): bool {
				return $value !== '';
			});
		}, $items);
	}
}
