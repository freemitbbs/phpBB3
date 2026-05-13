<?php

namespace freemitbbs\cardgamesauth\migrations;

class release_1_0_10 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\cardgamesauth\migrations\release_1_0_9',
		];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'game_sessions', 'hand_id');
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'game_sessions' => [
					'hand_id' => ['VCHAR:64', ''],
				],
			],
			'add_index' => [
				$this->table_prefix . 'game_sessions' => [
					'room_game_hand' => ['room_key', 'game_type', 'hand_id'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_keys' => [
				$this->table_prefix . 'game_sessions' => [
					'room_game_hand',
				],
			],
			'drop_columns' => [
				$this->table_prefix . 'game_sessions' => [
					'hand_id',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.10']],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.9']],
		];
	}
}
