<?php

namespace freemitbbs\toptopics\cron;

class refresh_scopes extends \phpbb\cron\task\base
{
	private const STALE_SCOPE_BATCH_SIZE = 10;
	private const LAST_RUN_CACHE_KEY = '_freemitbbs_toptopics_refresh_scopes_last_run';

	protected \phpbb\cache\service $cache;
	protected \phpbb\config\config $config;
	protected \freemitbbs\toptopics\service\ranker $ranker;

	public function __construct(
		\phpbb\cache\service $cache,
		\phpbb\config\config $config,
		\freemitbbs\toptopics\service\ranker $ranker
	)
	{
		$this->cache = $cache;
		$this->config = $config;
		$this->ranker = $ranker;
	}

	public function run()
	{
		$this->ranker->refresh_stale_materialized_scopes(self::STALE_SCOPE_BATCH_SIZE);
		$this->cache->put(self::LAST_RUN_CACHE_KEY, time(), 86400);
	}

	public function is_runnable()
	{
		return (int) ($this->config['toptopics_summary_cache_seconds'] ?? 0) > 0;
	}

	public function should_run()
	{
		$run_interval = $this->get_run_interval_seconds();
		$last_run = (int) $this->cache->get(self::LAST_RUN_CACHE_KEY);
		if ($last_run > (time() - $run_interval))
		{
			return false;
		}

		return $this->ranker->has_stale_materialized_scopes(1);
	}

	protected function get_run_interval_seconds(): int
	{
		$cache_ttl = (int) ($this->config['toptopics_summary_cache_seconds'] ?? 0);
		if ($cache_ttl <= 0)
		{
			return 60;
		}

		return max(15, min(60, (int) floor($cache_ttl / 4)));
	}
}
