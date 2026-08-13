<?php

namespace freemitbbs\newsscraper\migrations;

class release_1_0_10 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\newsscraper\migrations\release_1_0_9',
		];
	}

	public function update_data()
	{
		return [
			// Dynamic config values are read directly from the database instead of
			// phpBB's config cache. This makes the cron interval durable.
			['config.add', ['newsscraper_last_run', '0', true]],
			['config.update', ['newsscraper_version', '1.0.10']],
		];
	}
}
