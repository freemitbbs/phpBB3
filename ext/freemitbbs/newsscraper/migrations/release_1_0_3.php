<?php

namespace freemitbbs\newsscraper\migrations;

class release_1_0_3 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\newsscraper\migrations\release_1_0_2',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'update_default_run_limits']]],
			['config.update', ['newsscraper_version', '1.0.3']],
		];
	}

	public function update_default_run_limits(): void
	{
		$sql = 'UPDATE ' . CONFIG_TABLE . "
			SET config_value = '3600'
			WHERE config_name = 'newsscraper_interval_seconds'
				AND config_value = '1800'";
		$this->db->sql_query($sql);

		$sql = 'UPDATE ' . CONFIG_TABLE . "
			SET config_value = '4'
			WHERE config_name = 'newsscraper_max_selected_per_run'
				AND config_value = '6'";
		$this->db->sql_query($sql);
	}
}
