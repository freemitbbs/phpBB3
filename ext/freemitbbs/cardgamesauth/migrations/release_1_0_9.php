<?php

namespace freemitbbs\cardgamesauth\migrations;

class release_1_0_9 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\cardgamesauth\migrations\release_1_0_8',
		];
	}

	public function update_schema()
	{
		return [
				'add_columns' => [
					$this->table_prefix . 'game_room_members' => [
						'ready' => ['BOOL', 0],
					],
				],
			];
		}

	public function revert_schema()
	{
		return [
				'drop_columns' => [
					$this->table_prefix . 'game_room_members' => [
						'ready',
					],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.9']],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.8']],
		];
	}
}
