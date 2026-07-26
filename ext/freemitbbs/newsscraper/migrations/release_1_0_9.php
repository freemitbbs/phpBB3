<?php

namespace freemitbbs\newsscraper\migrations;

class release_1_0_9 extends \phpbb\db\migration\migration
{
	private const DEFAULT_MODEL = 'deepseek-v4-flash';
	private const DEPRECATED_MODELS = ['deepseek-chat', 'deepseek-reasoner'];

	public static function depends_on()
	{
		return [
			'\freemitbbs\newsscraper\migrations\release_1_0_8',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'replace_deprecated_model']]],
			['config.update', ['newsscraper_version', '1.0.9']],
		];
	}

	public function replace_deprecated_model(): void
	{
		$sql = 'UPDATE ' . CONFIG_TABLE . "
			SET config_value = '" . $this->db->sql_escape(self::DEFAULT_MODEL) . "'
			WHERE config_name = 'newsscraper_model'
				AND " . $this->db->sql_in_set('config_value', self::DEPRECATED_MODELS);
		$this->db->sql_query($sql);
	}
}
