<?php

namespace freemitbbs\newsscraper\migrations;

class release_1_0_2 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\newsscraper\migrations\release_1_0_1',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'increase_default_title_length']]],
			['config.update', ['newsscraper_version', '1.0.2']],
		];
	}

	public function increase_default_title_length(): void
	{
		$sql = 'UPDATE ' . CONFIG_TABLE . "
			SET config_value = '30'
			WHERE config_name = 'newsscraper_title_max_chars'
				AND config_value = '22'";
		$this->db->sql_query($sql);
	}
}
