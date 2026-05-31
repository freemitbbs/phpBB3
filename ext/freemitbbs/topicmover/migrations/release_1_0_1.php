<?php

namespace freemitbbs\topicmover\migrations;

class release_1_0_1 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\topicmover\migrations\release_1_0_0',
		];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'users' => [
					'topicmover_no_move' => ['BOOL', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'users' => [
					'topicmover_no_move',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['topicmover_excluded_user_ids', '']],
			['config.update', ['topicmover_version', '1.0.1']],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['topicmover_excluded_user_ids']],
			['config.update', ['topicmover_version', '1.0.0']],
		];
	}
}
