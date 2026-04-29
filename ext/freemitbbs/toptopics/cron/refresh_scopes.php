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
		\freemitbbs\toptopics\service\ranker $ranker,
		...$unused
	)
	{
		$this->cache = $cache;
		$this->config = $config;
		$this->ranker = $ranker;
	}

	public function run()
	{
		$started = time();
		$started_microtime = microtime(true);
		$this->write_stdout('start batch_size=' . self::STALE_SCOPE_BATCH_SIZE . ' time=' . $this->format_time($started));

		try
		{
			$refreshed = $this->ranker->refresh_stale_materialized_scopes(self::STALE_SCOPE_BATCH_SIZE);
			$duration_ms = $this->elapsed_milliseconds($started_microtime);

			$this->cache->put(self::LAST_RUN_CACHE_KEY, $started, 86400);
			$this->write_stdout('finish status=ok refreshed_scopes=' . $refreshed . ' duration_ms=' . $duration_ms . ' time=' . $this->format_time(time()));
		}
		catch (\Throwable $e)
		{
			$duration_ms = $this->elapsed_milliseconds($started_microtime);
			$error = substr(get_class($e) . ': ' . $e->getMessage(), 0, 255);

			$this->write_stdout('finish status=failed duration_ms=' . $duration_ms . ' error=' . $this->quote_log_value($error) . ' time=' . $this->format_time(time()));

			throw $e;
		}
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

	protected function elapsed_milliseconds(float $started_microtime): int
	{
		return max(0, (int) round((microtime(true) - $started_microtime) * 1000));
	}

	protected function format_time(int $time): string
	{
		return gmdate('Y-m-d\TH:i:s\Z', $time);
	}

	protected function quote_log_value(string $value): string
	{
		return '"' . str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\"', ' ', ' '], $value) . '"';
	}

	protected function write_stdout(string $message): void
	{
		if (PHP_SAPI !== 'cli' || !defined('STDOUT'))
		{
			return;
		}

		fwrite(STDOUT, '[toptopics:refresh_scopes] ' . $message . PHP_EOL);
	}
}
