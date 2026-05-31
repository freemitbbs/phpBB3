<?php

namespace freemitbbs\topicmover\cron;

class move_topics extends \phpbb\cron\task\base
{
	private const LAST_RUN_CACHE_KEY = '_freemitbbs_topicmover_last_run';
	private const DEFAULT_INTERVAL_SECONDS = 3600;

	protected \phpbb\cache\service $cache;
	protected \phpbb\config\config $config;
	protected \freemitbbs\topicmover\service\mover $mover;

	public function __construct(
		\phpbb\cache\service $cache,
		\phpbb\config\config $config,
		\freemitbbs\topicmover\service\mover $mover
	)
	{
		$this->cache = $cache;
		$this->config = $config;
		$this->mover = $mover;
	}

	public function run()
	{
		if (!$this->mover->is_configured())
		{
			$this->write_stdout('not_configured');
			return;
		}

		$result = $this->mover->process_candidates();
		$this->cache->put(self::LAST_RUN_CACHE_KEY, time(), 86400);
		$this->write_stdout(sprintf(
			'checked=%d moved=%d skipped=%d errors=%d',
			(int) $result['checked'],
			(int) $result['moved'],
			(int) $result['skipped'],
			(int) $result['errors']
		));
	}

	public function is_runnable()
	{
		return true;
	}

	public function should_run()
	{
		if (!$this->mover->is_configured())
		{
			return false;
		}

		$last_run = (int) $this->cache->get(self::LAST_RUN_CACHE_KEY);
		if ($last_run > (time() - $this->get_interval_seconds()))
		{
			return false;
		}

		return $this->mover->has_candidates();
	}

	protected function get_interval_seconds(): int
	{
		return max(300, min(86400, (int) ($this->config['topicmover_interval_seconds'] ?? self::DEFAULT_INTERVAL_SECONDS)));
	}

	protected function write_stdout(string $message): void
	{
		if (PHP_SAPI !== 'cli' || !defined('STDOUT'))
		{
			return;
		}

		fwrite(STDOUT, '[topicmover:move_topics] ' . $message . PHP_EOL);
	}
}
