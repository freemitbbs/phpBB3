<?php

namespace freemitbbs\toptopics\service;

class cache_invalidator
{
	private const GLOBAL_KEY = '_freemitbbs_toptopics_global_generation';
	private const FORUM_KEY_PREFIX = '_freemitbbs_toptopics_forum_generation_';

	protected \phpbb\cache\service $cache;

	public function __construct(\phpbb\cache\service $cache)
	{
		$this->cache = $cache;
	}

	public function get_cache_scope(array $forum_ids): array
	{
		$scope = [
			'global' => $this->get_generation(self::GLOBAL_KEY),
			'forums' => [],
		];

		foreach ($this->normalize_forum_ids($forum_ids) as $forum_id)
		{
			$scope['forums'][$forum_id] = $this->get_generation($this->build_forum_key($forum_id));
		}

		return $scope;
	}

	public function invalidate_forums(array $forum_ids): void
	{
		foreach ($this->normalize_forum_ids($forum_ids) as $forum_id)
		{
			$key = $this->build_forum_key($forum_id);
			$this->cache->put($key, $this->get_generation($key) + 1);
		}
	}

	public function invalidate_all(): void
	{
		$this->cache->put(self::GLOBAL_KEY, $this->get_generation(self::GLOBAL_KEY) + 1);
	}

	protected function get_generation(string $key): int
	{
		$value = $this->cache->get($key);

		return ($value === false) ? 0 : (int) $value;
	}

	protected function build_forum_key(int $forum_id): string
	{
		return self::FORUM_KEY_PREFIX . $forum_id;
	}

	protected function normalize_forum_ids(array $forum_ids): array
	{
		$forum_ids = array_values(array_unique(array_filter(array_map('intval', $forum_ids), static function ($forum_id) {
			return $forum_id > 0;
		})));
		sort($forum_ids);

		return $forum_ids;
	}
}
