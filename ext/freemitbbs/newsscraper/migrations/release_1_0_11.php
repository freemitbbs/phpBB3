<?php

namespace freemitbbs\newsscraper\migrations;

class release_1_0_11 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\newsscraper\migrations\release_1_0_10',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'reduce_scraper_frequency']]],
			['config.update', ['newsscraper_version', '1.0.11']],
		];
	}

	public function reduce_scraper_frequency(): void
	{
		$sql = 'UPDATE ' . CONFIG_TABLE . "
			SET config_value = '7200'
			WHERE config_name = 'newsscraper_interval_seconds'
				AND config_value = '3600'";
		$this->db->sql_query($sql);
	}
}
