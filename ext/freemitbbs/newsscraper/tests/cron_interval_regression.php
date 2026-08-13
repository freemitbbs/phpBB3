<?php

namespace phpbb\cron\task
{
	class base
	{
	}
}

namespace phpbb\cache
{
	class service
	{
		public function get(string $key)
		{
			return time();
		}
	}
}

namespace phpbb\config
{
	class config implements \ArrayAccess
	{
		public array $sets = [];

		public function __construct(public array $values)
		{
		}

		public function set($key, $value, $use_cache = true): void
		{
			$this->values[$key] = $value;
			$this->sets[] = [$key, $value, $use_cache];
		}

		public function offsetExists(mixed $offset): bool
		{
			return array_key_exists($offset, $this->values);
		}

		public function offsetGet(mixed $offset): mixed
		{
			return $this->values[$offset] ?? null;
		}

		public function offsetSet(mixed $offset, mixed $value): void
		{
			$this->values[$offset] = $value;
		}

		public function offsetUnset(mixed $offset): void
		{
			unset($this->values[$offset]);
		}
	}
}

namespace freemitbbs\newsscraper\service
{
	class scraper
	{
		public int $process_count = 0;

		public function is_configured(): bool
		{
			return true;
		}

		public function process(): array
		{
			$this->process_count++;

			return [
				'discovered' => 0,
				'evaluated' => 0,
				'selected' => 0,
				'posted' => 0,
				'rejected' => 0,
				'failed' => 0,
			];
		}
	}
}

namespace freemitbbs\newsscraper\tests
{
	require_once __DIR__ . '/../cron/scrape_news.php';

	$cache = new \phpbb\cache\service();
	$config = new \phpbb\config\config([
		'newsscraper_interval_seconds' => 3600,
		'newsscraper_last_run' => time() - 60,
	]);
	$scraper = new \freemitbbs\newsscraper\service\scraper();
	$cron = new \freemitbbs\newsscraper\cron\scrape_news($cache, $config, $scraper);

	$cases = [];
	$cases['recent database timestamp blocks run'] = $cron->should_run() === false;
	$config->values['newsscraper_last_run'] = time() - 7200;
	$cases['old database timestamp allows run despite cache value'] = $cron->should_run() === true;
	$cron->run();
	$last_set = $config->sets[count($config->sets) - 1] ?? [];
	$cases['run persisted timestamp'] = ($last_set[0] ?? '') === 'newsscraper_last_run'
		&& (int) ($last_set[1] ?? 0) >= time() - 2;
	$cases['timestamp bypassed config cache'] = ($last_set[2] ?? true) === false;
	$cases['scraper processed once'] = $scraper->process_count === 1;
	$cases['new timestamp blocks immediate rerun'] = $cron->should_run() === false;

	$failures = array_keys(array_filter($cases, static fn (bool $passed): bool => !$passed));
	if ($failures)
	{
		fwrite(STDERR, "News scraper cron interval regression failed:\n- " . implode("\n- ", $failures) . "\n");
		exit(1);
	}

	echo 'News scraper cron interval regression passed (' . count($cases) . " cases)\n";
}
