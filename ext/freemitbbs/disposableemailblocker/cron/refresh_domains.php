<?php

namespace freemitbbs\disposableemailblocker\cron;

use freemitbbs\disposableemailblocker\service\domain_blocklist;

class refresh_domains extends \phpbb\cron\task\base
{
	protected const SOURCE_URL = 'https://raw.githubusercontent.com/doodad-labs/disposable-email-domains/main/data/domains.txt';
	protected const CHECK_INTERVAL_SECONDS = 604800;
	protected const RETRY_INTERVAL_SECONDS = 86400;
	protected const MINIMUM_DOMAINS = 1000;
	protected const DOWNLOAD_TIMEOUT_SECONDS = 20;
	protected const LAST_ATTEMPT_CONFIG = 'disposableemailblocker_domains_last_attempt';
	protected const LAST_SUCCESS_CONFIG = 'disposableemailblocker_domains_last_success';
	protected const LAST_COUNT_CONFIG = 'disposableemailblocker_domains_last_count';
	protected const LAST_ERROR_CONFIG = 'disposableemailblocker_domains_last_error';

	protected \phpbb\config\config $config;
	protected domain_blocklist $blocklist;

	public function __construct(\phpbb\config\config $config, domain_blocklist $blocklist)
	{
		$this->config = $config;
		$this->blocklist = $blocklist;
	}

	public function run()
	{
		$now = time();
		$this->config->set(self::LAST_ATTEMPT_CONFIG, (string) $now, false);

		try
		{
			$result = $this->blocklist->refresh_from_url(self::SOURCE_URL, self::MINIMUM_DOMAINS, self::DOWNLOAD_TIMEOUT_SECONDS);
		}
		catch (\RuntimeException $e)
		{
			$this->config->set(self::LAST_ERROR_CONFIG, substr($e->getMessage(), 0, 255), false);
			return;
		}

		$this->config->set(self::LAST_SUCCESS_CONFIG, (string) $now, false);
		$this->config->set(self::LAST_COUNT_CONFIG, (string) ($result['domains'] ?? 0), false);
		$this->config->set(self::LAST_ERROR_CONFIG, '', false);
	}

	public function should_run()
	{
		$now = time();
		$last_success = (int) ($this->config[self::LAST_SUCCESS_CONFIG] ?? 0);
		if ($last_success > ($now - self::CHECK_INTERVAL_SECONDS))
		{
			return false;
		}

		$last_attempt = (int) ($this->config[self::LAST_ATTEMPT_CONFIG] ?? 0);
		return $last_attempt <= ($now - self::RETRY_INTERVAL_SECONDS);
	}
}
