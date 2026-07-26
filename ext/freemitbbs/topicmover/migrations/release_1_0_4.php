<?php

namespace freemitbbs\topicmover\migrations;

class release_1_0_4 extends \phpbb\db\migration\migration
{
	private const DEFAULT_MODEL = 'deepseek-v4-flash';
	private const DEPRECATED_MODELS = ['deepseek-chat', 'deepseek-reasoner'];

	public static function depends_on()
	{
		return [
			'\freemitbbs\topicmover\migrations\release_1_0_3',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'replace_deprecated_model']]],
			['config.update', ['topicmover_version', '1.0.4']],
		];
	}

	public function replace_deprecated_model(): void
	{
		$sql = 'UPDATE ' . CONFIG_TABLE . "
			SET config_value = '" . $this->db->sql_escape(self::DEFAULT_MODEL) . "'
			WHERE config_name = 'topicmover_model'
				AND " . $this->db->sql_in_set('config_value', self::DEPRECATED_MODELS);
		$this->db->sql_query($sql);
	}
}
