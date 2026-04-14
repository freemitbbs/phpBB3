<?php

namespace freemitbbs\riskwatch\cron;

class refresh_risk extends \phpbb\cron\task\base
{
	private const LAST_RUN_CACHE_KEY = '_freemitbbs_riskwatch_refresh_last_run';

	protected \phpbb\cache\service $cache;
	protected \phpbb\config\config $config;
	protected \freemitbbs\riskwatch\service\scorer $scorer;

	public function __construct(
		\phpbb\cache\service $cache,
		\phpbb\config\config $config,
		\freemitbbs\riskwatch\service\scorer $scorer
	)
	{
		$this->cache = $cache;
		$this->config = $config;
		$this->scorer = $scorer;
	}

	public function run()
	{
		$batch_size = max(1, (int) ($this->config['riskwatch_refresh_batch_size'] ?? 500));
		$this->scorer->refresh_candidates($batch_size);
		$this->cache->put(self::LAST_RUN_CACHE_KEY, time(), 86400);
	}

	public function is_runnable()
	{
		return (int) ($this->config['riskwatch_refresh_seconds'] ?? 0) > 0;
	}

	public function should_run()
	{
		$interval = max(30, (int) ($this->config['riskwatch_refresh_seconds'] ?? 300));
		$last_run = (int) $this->cache->get(self::LAST_RUN_CACHE_KEY);

		return $last_run <= (time() - $interval);
	}
}
