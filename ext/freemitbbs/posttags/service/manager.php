<?php

namespace freemitbbs\posttags\service;

class manager
{
	public const MAX_TAGS = 20;
	public const MAX_TAG_LENGTH = 50;

	protected \phpbb\db\driver\driver_interface $db;
	protected string $tags_table;
	protected string $post_tags_table;

	public function __construct(\phpbb\db\driver\driver_interface $db, string $table_prefix)
	{
		$this->db = $db;
		$this->tags_table = $table_prefix . 'posttags_tags';
		$this->post_tags_table = $table_prefix . 'posttags_posts';
	}

	public function get_tags_table(): string
	{
		return $this->tags_table;
	}

	public function get_post_tags_table(): string
	{
		return $this->post_tags_table;
	}

	public function clean_tags_from_string(string $raw): array
	{
		$raw = str_replace(["\r", "\n", "\t"], ' ', $raw);
		$parts = preg_split('#[\s,，、;；]+#u', $raw) ?: [];
		$tags = [];
		$seen = [];

		foreach ($parts as $part)
		{
			$tag = $this->clean_tag((string) $part);
			if ($tag === '')
			{
				continue;
			}

			$clean = $this->clean_key($tag);
			if ($clean === '' || isset($seen[$clean]))
			{
				continue;
			}

			$seen[$clean] = true;
			$tags[] = $tag;

			if (count($tags) >= self::MAX_TAGS)
			{
				break;
			}
		}

		return $tags;
	}

	public function tags_to_raw(array $tags): string
	{
		$names = [];
		foreach ($tags as $tag)
		{
			$name = is_array($tag) ? (string) ($tag['tag_name'] ?? '') : (string) $tag;
			$name = $this->clean_tag($name);
			if ($name !== '')
			{
				$names[] = $name;
			}
		}

		return implode(',', $names);
	}

	public function clean_tag(string $tag): string
	{
		$tag = trim($tag);
		$tag = preg_replace('~^[#＃]+~u', '', $tag);
		$tag = preg_replace('#[^\p{L}\p{N}_-]+#u', '', $tag);
		$tag = trim((string) $tag, "_- \t\n\r\0\x0B");

		if ($tag === '')
		{
			return '';
		}

		return truncate_string($tag, self::MAX_TAG_LENGTH, 255, false);
	}

	public function clean_key(string $tag): string
	{
		$tag = $this->clean_tag($tag);
		if ($tag === '')
		{
			return '';
		}

		if (function_exists('utf8_clean_string'))
		{
			return (string) utf8_clean_string($tag);
		}

		return function_exists('mb_strtolower') ? mb_strtolower($tag, 'UTF-8') : strtolower($tag);
	}

	public function get_tag(string $tag): ?array
	{
		$clean = $this->clean_key($tag);
		if ($clean === '')
		{
			return null;
		}

		$sql = 'SELECT tag_id, tag_name, tag_clean
			FROM ' . $this->tags_table . "
			WHERE tag_clean = '" . $this->db->sql_escape($clean) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	public function get_post_tags(int $post_id): array
	{
		if ($post_id <= 0)
		{
			return [];
		}

		$tags = [];
		$sql = 'SELECT t.tag_id, t.tag_name, t.tag_clean
			FROM ' . $this->post_tags_table . ' pt
			JOIN ' . $this->tags_table . ' t
				ON t.tag_id = pt.tag_id
			WHERE pt.post_id = ' . $post_id . '
			ORDER BY t.tag_name ASC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$tags[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $tags;
	}

	public function get_tags_for_posts(array $post_ids): array
	{
		$post_ids = array_values(array_unique(array_filter(array_map('intval', $post_ids))));
		if (empty($post_ids))
		{
			return [];
		}

		$tags = [];
		$sql = 'SELECT pt.post_id, t.tag_id, t.tag_name, t.tag_clean
			FROM ' . $this->post_tags_table . ' pt
			JOIN ' . $this->tags_table . ' t
				ON t.tag_id = pt.tag_id
			WHERE ' . $this->db->sql_in_set('pt.post_id', $post_ids) . '
			ORDER BY t.tag_name ASC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$tags[(int) $row['post_id']][] = $row;
		}
		$this->db->sql_freeresult($result);

		return $tags;
	}

	public function set_post_tags(int $post_id, array $tag_names): void
	{
		if ($post_id <= 0)
		{
			return;
		}

		$tag_names = array_slice($this->clean_tags_from_string($this->tags_to_raw($tag_names)), 0, self::MAX_TAGS);

		$this->db->sql_transaction('begin');
		$this->db->sql_query('DELETE FROM ' . $this->post_tags_table . ' WHERE post_id = ' . $post_id);

		foreach ($tag_names as $tag_name)
		{
			$tag_id = $this->ensure_tag($tag_name);
			if ($tag_id <= 0)
			{
				continue;
			}

			$sql_ary = [
				'post_id' => $post_id,
				'tag_id' => $tag_id,
				'tagged_time' => time(),
			];
			$this->db->sql_query('INSERT INTO ' . $this->post_tags_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
		}

		$this->db->sql_transaction('commit');
	}

	public function delete_post_tags(array $post_ids): void
	{
		$post_ids = array_values(array_unique(array_filter(array_map('intval', $post_ids))));
		if (empty($post_ids))
		{
			return;
		}

		$this->db->sql_query('DELETE FROM ' . $this->post_tags_table . ' WHERE ' . $this->db->sql_in_set('post_id', $post_ids));
	}

	protected function ensure_tag(string $tag_name): int
	{
		$tag_name = $this->clean_tag($tag_name);
		$tag_clean = $this->clean_key($tag_name);
		if ($tag_name === '' || $tag_clean === '')
		{
			return 0;
		}

		$existing = $this->get_tag($tag_name);
		if ($existing)
		{
			return (int) $existing['tag_id'];
		}

		$sql_ary = [
			'tag_name' => $tag_name,
			'tag_clean' => $tag_clean,
			'created_time' => time(),
		];
		$this->db->sql_query('INSERT INTO ' . $this->tags_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));

		return (int) $this->db->sql_nextid();
	}
}
