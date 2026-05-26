<?php

namespace freemitbbs\searchqueue\cron;

class process extends \phpbb\cron\task\base
{
	private const DEFAULT_BATCH_SIZE = 25;
	private const DEFAULT_INTERVAL_SECONDS = 30;
	private const LAST_RUN_CACHE_KEY = '_freemitbbs_searchqueue_process_last_run';

	protected \phpbb\cache\service $cache;
	protected \phpbb\config\config $config;
	protected \freemitbbs\searchqueue\service\queue $queue;

	public function __construct(
		\phpbb\cache\service $cache,
		\phpbb\config\config $config,
		\freemitbbs\searchqueue\service\queue $queue
	)
	{
		$this->cache = $cache;
		$this->config = $config;
		$this->queue = $queue;
	}

	public function run()
	{
		$indexed = $this->queue->process_queued_posts($this->get_batch_size());
		$this->cache->put(self::LAST_RUN_CACHE_KEY, time(), 86400);
		$this->write_stdout('indexed_posts=' . $indexed);
	}

	public function is_runnable()
	{
		return true;
	}

	public function should_run()
	{
		if (!$this->queue->can_process())
		{
			return false;
		}

		$last_run = (int) $this->cache->get(self::LAST_RUN_CACHE_KEY);
		if ($last_run > (time() - $this->get_interval_seconds()))
		{
			return false;
		}

		return $this->queue->has_queued_posts();
	}

	protected function get_batch_size(): int
	{
		return max(1, min(500, (int) ($this->config['freemitbbs_searchqueue_batch_size'] ?? self::DEFAULT_BATCH_SIZE)));
	}

	protected function get_interval_seconds(): int
	{
		return max(30, (int) ($this->config['freemitbbs_searchqueue_interval_seconds'] ?? self::DEFAULT_INTERVAL_SECONDS));
	}

	protected function write_stdout(string $message): void
	{
		if (PHP_SAPI !== 'cli' || !defined('STDOUT'))
		{
			return;
		}

		fwrite(STDOUT, '[searchqueue:process] ' . $message . PHP_EOL);
	}
}
