<?php

namespace freemitbbs\cardgamesauth\migrations;

class release_1_0_8 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\cardgamesauth\migrations\release_1_0_7',
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.8']],
			['custom', [[$this, 'seed_guandan_room_configs']]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'remove_guandan_room_configs']]],
			['config.update', ['cardgamesauth_version', '1.0.7']],
		];
	}

	public function seed_guandan_room_configs(): void
	{
		$now = time();
		$rooms = [
			['fengrenzhai', '逢人斋', 50],
			['tongtianlou', '通天楼', 60],
			['siwangyuan', '四王院', 70],
		];

		foreach ($rooms as [$room_key, $display_name, $sort_order])
		{
			$row = [
				'room_key' => $room_key,
				'game_type' => 'guandan',
				'display_name' => $display_name,
				'sort_order' => $sort_order,
				'enabled' => 1,
				'default_settings_json' => '{"seatCount":4,"observerMode":"watched_player"}',
				'created_at' => $now,
				'updated_at' => $now,
			];

			$sql = 'SELECT room_key
				FROM ' . $this->table_prefix . "game_room_configs
				WHERE room_key = '" . $this->db->sql_escape($room_key) . "'";
			$result = $this->db->sql_query_limit($sql, 1);
			$exists = (bool) $this->db->sql_fetchfield('room_key');
			$this->db->sql_freeresult($result);

			if ($exists)
			{
				unset($row['created_at']);
				$this->db->sql_query('UPDATE ' . $this->table_prefix . 'game_room_configs
					SET ' . $this->db->sql_build_array('UPDATE', $row) . "
					WHERE room_key = '" . $this->db->sql_escape($room_key) . "'");
			}
			else
			{
				$this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'game_room_configs ' . $this->db->sql_build_array('INSERT', $row));
			}
		}
	}

	public function remove_guandan_room_configs(): void
	{
		$room_keys = ['fengrenzhai', 'tongtianlou', 'siwangyuan'];
		$this->db->sql_query('DELETE FROM ' . $this->table_prefix . 'game_room_configs
			WHERE ' . $this->db->sql_in_set('room_key', $room_keys));
	}
}
