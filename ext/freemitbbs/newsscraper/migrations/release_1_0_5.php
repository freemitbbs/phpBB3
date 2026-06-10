<?php

namespace freemitbbs\newsscraper\migrations;

class release_1_0_5 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\newsscraper\migrations\release_1_0_4',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'update_frontpage_default']]],
			['config.update', ['newsscraper_version', '1.0.5']],
		];
	}

	public function update_frontpage_default(): void
	{
		$sql = 'UPDATE ' . CONFIG_TABLE . "
			SET config_value = '20'
			WHERE config_name = 'newsscraper_frontpage_count'
				AND config_value = '12'";
		$this->db->sql_query($sql);
	}
}
