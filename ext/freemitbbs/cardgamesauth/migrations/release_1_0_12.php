<?php

namespace freemitbbs\cardgamesauth\migrations;

class release_1_0_12 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\cardgamesauth\migrations\release_1_0_11',
		];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_index_exists($this->table_prefix . 'game_events', 'request_lookup');
	}

	public function update_schema()
	{
		return [
			'add_index' => [
				$this->table_prefix . 'game_events' => [
					'request_lookup' => ['request_id', 'event_type', 'session_id'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_keys' => [
				$this->table_prefix . 'game_events' => [
					'request_lookup',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.12']],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.11']],
		];
	}
}
