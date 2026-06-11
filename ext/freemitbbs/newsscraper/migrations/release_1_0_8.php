<?php

namespace freemitbbs\newsscraper\migrations;

class release_1_0_8 extends \phpbb\db\migration\migration
{
	private const OLD_DEFAULT_SOURCES = 'guardian,bbc,dw,cnbc,ars,zerohedge,foxnews,wenxuecity,zaobao,sina_world,sohu';
	private const NEW_DEFAULT_SOURCES = 'wenxuecity,zaobao,sina_world,sohu,guardian,bbc,dw,cnbc,ars,zerohedge,foxnews';

	public static function depends_on()
	{
		return [
			'\freemitbbs\newsscraper\migrations\release_1_0_7',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'update_default_source_order']]],
			['config.update', ['newsscraper_version', '1.0.8']],
		];
	}

	public function update_default_source_order(): void
	{
		$sql = 'UPDATE ' . CONFIG_TABLE . "
			SET config_value = '" . $this->db->sql_escape(self::NEW_DEFAULT_SOURCES) . "'
			WHERE config_name = 'newsscraper_enabled_sources'
				AND config_value = '" . $this->db->sql_escape(self::OLD_DEFAULT_SOURCES) . "'";
		$this->db->sql_query($sql);
	}
}
