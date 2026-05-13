<?php

namespace freemitbbs\cardgamesauth\migrations;

class release_1_0_11 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\cardgamesauth\migrations\release_1_0_10',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'backfill_hand_ids']]],
			['config.update', ['cardgamesauth_version', '1.0.11']],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.10']],
		];
	}

	public function backfill_hand_ids()
	{
		$table = $this->table_prefix . 'game_sessions';
		$sql = 'SELECT id, random_audit_json
			FROM ' . $table . "
			WHERE hand_id = ''
				AND random_audit_json <> ''";
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$audit = json_decode((string) $row['random_audit_json'], true);
			if (!is_array($audit))
			{
				continue;
			}

			$hand_id = substr(trim((string) ($audit['handId'] ?? $audit['hand_id'] ?? '')), 0, 64);
			if ($hand_id === '')
			{
				continue;
			}

			$this->db->sql_query('UPDATE ' . $table . "
				SET hand_id = '" . $this->db->sql_escape($hand_id) . "'
				WHERE id = " . (int) $row['id']);
		}
		$this->db->sql_freeresult($result);
	}
}
