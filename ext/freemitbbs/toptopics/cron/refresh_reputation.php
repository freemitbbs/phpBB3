<?php

namespace freemitbbs\toptopics\cron;

class refresh_reputation extends \phpbb\cron\task\base
{
	private const DEFAULT_BATCH_SIZE = 25;
	private const DEFAULT_INTERVAL_SECONDS = 300;
	private const LAST_RUN_CACHE_KEY = '_freemitbbs_toptopics_refresh_reputation_last_run';

	protected \phpbb\cache\service $cache;
	protected \phpbb\config\config $config;
	protected \freemitbbs\toptopics\service\reputation $reputation;

	public function __construct(
		\phpbb\cache\service $cache,
		\phpbb\config\config $config,
		\freemitbbs\toptopics\service\reputation $reputation
	)
	{
		$this->cache = $cache;
		$this->config = $config;
		$this->reputation = $reputation;
	}

	public function run()
	{
		$refreshed = $this->reputation->refresh_queued_reputations($this->get_batch_size());
		$this->cache->put(self::LAST_RUN_CACHE_KEY, time(), 86400);
		$this->write_stdout('refreshed_users=' . $refreshed);
	}

	public function is_runnable()
	{
		return true;
	}

	public function should_run()
	{
		$last_run = (int) $this->cache->get(self::LAST_RUN_CACHE_KEY);
		if ($last_run > (time() - $this->get_interval_seconds()))
		{
			return false;
		}

		return $this->reputation->has_queued_reputation_refreshes();
	}

	protected function get_batch_size(): int
	{
		return max(1, min(500, (int) ($this->config['toptopics_reputation_refresh_batch_size'] ?? self::DEFAULT_BATCH_SIZE)));
	}

	protected function get_interval_seconds(): int
	{
		return max(30, (int) ($this->config['toptopics_reputation_refresh_seconds'] ?? self::DEFAULT_INTERVAL_SECONDS));
	}

	protected function write_stdout(string $message): void
	{
		if (PHP_SAPI !== 'cli' || !defined('STDOUT'))
		{
			return;
		}

		fwrite(STDOUT, '[toptopics:refresh_reputation] ' . $message . PHP_EOL);
	}
}
