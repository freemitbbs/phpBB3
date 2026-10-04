<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_32 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_31',
		];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_index_exists($this->table_prefix . 'sessions', 'toptopics_guest_online');
	}

	public function update_schema()
	{
		return [
			'add_index' => [
				$this->table_prefix . 'sessions' => [
					'toptopics_guest_online' => ['session_user_id', 'session_time', 'session_ip'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_keys' => [
				$this->table_prefix . 'sessions' => ['toptopics_guest_online'],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['toptopics_version', '1.1.32']],
		];
	}
}
