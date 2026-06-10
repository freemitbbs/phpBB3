<?php

namespace freemitbbs\newsscraper\cron;

class scrape_news extends \phpbb\cron\task\base
{
	private const LAST_RUN_CACHE_KEY = '_freemitbbs_newsscraper_last_run';
	private const DEFAULT_INTERVAL_SECONDS = 3600;

	protected \phpbb\cache\service $cache;
	protected \phpbb\config\config $config;
	protected \freemitbbs\newsscraper\service\scraper $scraper;

	public function __construct(
		\phpbb\cache\service $cache,
		\phpbb\config\config $config,
		\freemitbbs\newsscraper\service\scraper $scraper
	)
	{
		$this->cache = $cache;
		$this->config = $config;
		$this->scraper = $scraper;
	}

	public function run()
	{
		if (!$this->scraper->is_configured())
		{
			$this->write_stdout('not_configured');
			return;
		}

		$result = $this->scraper->process();
		$this->cache->put(self::LAST_RUN_CACHE_KEY, time(), 86400);
		$this->write_stdout(sprintf(
			'discovered=%d evaluated=%d selected=%d posted=%d rejected=%d failed=%d',
			(int) $result['discovered'],
			(int) $result['evaluated'],
			(int) $result['selected'],
			(int) $result['posted'],
			(int) $result['rejected'],
			(int) $result['failed']
		));
	}

	public function is_runnable()
	{
		return true;
	}

	public function should_run()
	{
		if (!$this->scraper->is_configured())
		{
			return false;
		}

		$last_run = (int) $this->cache->get(self::LAST_RUN_CACHE_KEY);
		if ($last_run > (time() - $this->get_interval_seconds()))
		{
			return false;
		}

		return true;
	}

	protected function get_interval_seconds(): int
	{
		return max(300, min(86400, (int) ($this->config['newsscraper_interval_seconds'] ?? self::DEFAULT_INTERVAL_SECONDS)));
	}

	protected function write_stdout(string $message): void
	{
		if (PHP_SAPI !== 'cli' || !defined('STDOUT'))
		{
			return;
		}

		fwrite(STDOUT, '[newsscraper:scrape_news] ' . $message . PHP_EOL);
	}
}
