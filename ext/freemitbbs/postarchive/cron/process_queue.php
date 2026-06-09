<?php

namespace freemitbbs\postarchive\cron;

class process_queue extends \phpbb\cron\task\base
{
	private const LAST_RUN_CACHE_KEY = '_freemitbbs_postarchive_process_queue_last_run';
	private const DEFAULT_INTERVAL_SECONDS = 60;

	protected \phpbb\cache\service $cache;
	protected \phpbb\config\config $config;
	protected \freemitbbs\postarchive\service\manager $manager;

	public function __construct(
		\phpbb\cache\service $cache,
		\phpbb\config\config $config,
		\freemitbbs\postarchive\service\manager $manager
	)
	{
		$this->cache = $cache;
		$this->config = $config;
		$this->manager = $manager;
	}

	public function run()
	{
		$result = $this->manager->process_next_job();
		$this->cache->put(self::LAST_RUN_CACHE_KEY, time(), 86400);
		$this->write_stdout(sprintf(
			'processed=%d status=%s expired_archives=%d expired_jobs=%d',
			(int) $result['processed'],
			(string) $result['status'],
			(int) $result['expired_archives'],
			(int) $result['expired_jobs']
		));
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

		return $this->manager->has_runnable_jobs() || $this->manager->has_expired_archives();
	}

	protected function get_interval_seconds(): int
	{
		return max(60, min(3600, (int) ($this->config['freemitbbs_postarchive_cron_interval_seconds'] ?? self::DEFAULT_INTERVAL_SECONDS)));
	}

	protected function write_stdout(string $message): void
	{
		if (PHP_SAPI !== 'cli' || !defined('STDOUT'))
		{
			return;
		}

		fwrite(STDOUT, '[postarchive:process_queue] ' . $message . PHP_EOL);
	}
}
