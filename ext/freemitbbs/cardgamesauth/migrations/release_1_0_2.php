<?php

namespace freemitbbs\cardgamesauth\migrations;

class release_1_0_2 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\cardgamesauth\migrations\release_1_0_1',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'game_room_configs' => [
					'COLUMNS' => [
						'room_key' => ['VCHAR:64', ''],
						'game_type' => ['VCHAR:32', ''],
						'display_name' => ['VCHAR_UNI:255', ''],
						'sort_order' => ['INT:11', 0],
						'enabled' => ['BOOL', 1],
						'default_settings_json' => ['MTEXT_UNI', ''],
						'created_at' => ['TIMESTAMP', 0],
						'updated_at' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'room_key',
					'KEYS' => [
						'enabled_sort' => ['INDEX', ['enabled', 'sort_order']],
					],
				],
				$this->table_prefix . 'game_sessions' => [
					'COLUMNS' => [
						'id' => ['UINT', null, 'auto_increment'],
						'room_key' => ['VCHAR:64', ''],
						'game_type' => ['VCHAR:32', ''],
						'status' => ['VCHAR:32', ''],
						'owner_user_id' => ['UINT:8', 0],
						'settings_json' => ['MTEXT_UNI', ''],
						'state_schema_version' => ['UINT:4', 1],
						'state_version' => ['BINT', 0],
						'random_audit_json' => ['MTEXT_UNI', ''],
						'created_at' => ['TIMESTAMP', 0],
						'started_at' => ['TIMESTAMP', 0],
						'updated_at' => ['TIMESTAMP', 0],
						'finished_at' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'id',
					'KEYS' => [
						'room_status' => ['INDEX', ['room_key', 'status']],
						'updated_at' => ['INDEX', 'updated_at'],
					],
				],
				$this->table_prefix . 'game_room_members' => [
					'COLUMNS' => [
						'id' => ['UINT', null, 'auto_increment'],
						'session_id' => ['UINT', 0],
						'room_key' => ['VCHAR:64', ''],
						'user_id' => ['UINT:8', 0],
						'role' => ['VCHAR:32', ''],
						'seat_index' => ['INT:11', 0],
						'watched_seat_index' => ['INT:11', 0],
						'connected' => ['BOOL', 0],
						'joined_at' => ['TIMESTAMP', 0],
						'last_seen_at' => ['TIMESTAMP', 0],
						'left_at' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'id',
					'KEYS' => [
						'room_user' => ['INDEX', ['room_key', 'user_id']],
						'session_user' => ['INDEX', ['session_id', 'user_id']],
						'session_seat' => ['INDEX', ['session_id', 'seat_index']],
						'connected' => ['INDEX', ['room_key', 'connected']],
					],
				],
				$this->table_prefix . 'game_events' => [
					'COLUMNS' => [
						'id' => ['UINT', null, 'auto_increment'],
						'session_id' => ['UINT', 0],
						'seq' => ['BINT', 0],
						'game_type' => ['VCHAR:32', ''],
						'actor_user_id' => ['UINT:8', 0],
						'request_id' => ['VCHAR:64', ''],
						'event_type' => ['VCHAR:64', ''],
						'payload_schema_version' => ['UINT:4', 1],
						'payload_json' => ['MTEXT_UNI', ''],
						'created_at' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'id',
					'KEYS' => [
						'session_seq' => ['UNIQUE', ['session_id', 'seq']],
						'actor' => ['INDEX', 'actor_user_id'],
						'created_at' => ['INDEX', 'created_at'],
						'request' => ['INDEX', ['session_id', 'request_id']],
					],
				],
				$this->table_prefix . 'game_snapshots' => [
					'COLUMNS' => [
						'id' => ['UINT', null, 'auto_increment'],
						'session_id' => ['UINT', 0],
						'seq' => ['BINT', 0],
						'game_type' => ['VCHAR:32', ''],
						'state_schema_version' => ['UINT:4', 1],
						'state_json' => ['MTEXT_UNI', ''],
						'created_at' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'id',
					'KEYS' => [
						'session_seq' => ['UNIQUE', ['session_id', 'seq']],
						'created_at' => ['INDEX', 'created_at'],
					],
				],
				$this->table_prefix . 'game_finished_summaries' => [
					'COLUMNS' => [
						'id' => ['UINT', null, 'auto_increment'],
						'session_id' => ['UINT', 0],
						'game_type' => ['VCHAR:32', ''],
						'room_key' => ['VCHAR:64', ''],
						'winner_json' => ['MTEXT_UNI', ''],
						'score_json' => ['MTEXT_UNI', ''],
						'summary_json' => ['MTEXT_UNI', ''],
						'finished_at' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'id',
					'KEYS' => [
						'session' => ['UNIQUE', 'session_id'],
						'finished_at' => ['INDEX', 'finished_at'],
					],
				],
				$this->table_prefix . 'game_player_stats' => [
					'COLUMNS' => [
						'id' => ['UINT', null, 'auto_increment'],
						'game_type' => ['VCHAR:32', ''],
						'user_id' => ['UINT:8', 0],
						'games_played' => ['UINT:8', 0],
						'games_won' => ['UINT:8', 0],
						'stats_json' => ['MTEXT_UNI', ''],
						'updated_at' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'id',
					'KEYS' => [
						'game_user' => ['UNIQUE', ['game_type', 'user_id']],
					],
				],
				$this->table_prefix . 'game_player_settings' => [
					'COLUMNS' => [
						'id' => ['UINT', null, 'auto_increment'],
						'game_type' => ['VCHAR:32', ''],
						'user_id' => ['UINT:8', 0],
						'settings_json' => ['MTEXT_UNI', ''],
						'updated_at' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'id',
					'KEYS' => [
						'game_user' => ['UNIQUE', ['game_type', 'user_id']],
					],
				],
				$this->table_prefix . 'game_moderation_audit' => [
					'COLUMNS' => [
						'id' => ['UINT', null, 'auto_increment'],
						'room_key' => ['VCHAR:64', ''],
						'session_id' => ['UINT', 0],
						'moderator_user_id' => ['UINT:8', 0],
						'target_user_id' => ['UINT:8', 0],
						'action' => ['VCHAR:64', ''],
						'reason' => ['VCHAR_UNI:255', ''],
						'payload_json' => ['MTEXT_UNI', ''],
						'created_at' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'id',
					'KEYS' => [
						'session' => ['INDEX', 'session_id'],
						'target' => ['INDEX', 'target_user_id'],
						'created_at' => ['INDEX', 'created_at'],
					],
				],
				$this->table_prefix . 'cardgamesauth_proxy_nonces' => [
					'COLUMNS' => [
						'nonce_hash' => ['VCHAR:64', ''],
						'created_at' => ['TIMESTAMP', 0],
						'expires_at' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'nonce_hash',
					'KEYS' => [
						'expires_at' => ['INDEX', 'expires_at'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'cardgamesauth_proxy_nonces',
				$this->table_prefix . 'game_moderation_audit',
				$this->table_prefix . 'game_player_settings',
				$this->table_prefix . 'game_player_stats',
				$this->table_prefix . 'game_finished_summaries',
				$this->table_prefix . 'game_snapshots',
				$this->table_prefix . 'game_events',
				$this->table_prefix . 'game_room_members',
				$this->table_prefix . 'game_sessions',
				$this->table_prefix . 'game_room_configs',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.2']],
			['config.add', ['cardgamesauth_proxy_enabled', 1]],
			['config.add', ['cardgamesauth_proxy_secret', $this->generate_secret()]],
			['config.add', ['cardgamesauth_proxy_clock_skew', 300]],
			['config.add', ['cardgamesauth_proxy_nonce_ttl', 300]],
			['config.add', ['cardgamesauth_proxy_max_body_bytes', 262144]],
			['config.add', ['cardgamesauth_token_clock_tolerance', 10]],
			['custom', [[$this, 'seed_room_configs']]],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['cardgamesauth_token_clock_tolerance']],
			['config.remove', ['cardgamesauth_proxy_max_body_bytes']],
			['config.remove', ['cardgamesauth_proxy_nonce_ttl']],
			['config.remove', ['cardgamesauth_proxy_clock_skew']],
			['config.remove', ['cardgamesauth_proxy_secret']],
			['config.remove', ['cardgamesauth_proxy_enabled']],
		];
	}

	public function seed_room_configs(): void
	{
		$now = time();
		$rooms = [
			['qinglong', 'tractor', '青龙阁', 10],
			['baihu', 'tractor', '白虎堂', 20],
			['zhuque', 'tractor', '朱雀台', 30],
			['xuanwu', 'tractor', '玄武轩', 40],
		];

		foreach ($rooms as [$room_key, $game_type, $display_name, $sort_order])
		{
			$row = [
				'room_key' => $room_key,
				'game_type' => $game_type,
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

	protected function generate_secret(): string
	{
		try
		{
			return bin2hex(random_bytes(32));
		}
		catch (\Exception $e)
		{
			return sha1(uniqid((string) mt_rand(), true) . microtime(true));
		}
	}
}
