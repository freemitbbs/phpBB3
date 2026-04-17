<?php

namespace freemitbbs\modernsmiley\migrations;

class release_1_1_0 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\modernsmiley\migrations\release_1_0_0',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'modern_smiley_map' => [
					'COLUMNS' => [
						'smiley_id' => ['UINT:10', 0],
						'emoji_seq' => ['VCHAR:191', ''],
					],
					'PRIMARY_KEY' => 'smiley_id',
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'modern_smiley_map',
			],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'migrate_legacy_mappings']]],
			['config.update', ['modernsmiley_version', '1.1.0']],
		];
	}

	public function migrate_legacy_mappings(): void
	{
		$sql = 'SELECT s.smiley_id, m.emoji_seq
			FROM ' . SMILIES_TABLE . ' s
			INNER JOIN ' . $this->table_prefix . 'modern_smilies m
				ON m.smiley_url = s.smiley_url
			ORDER BY s.smiley_id';
		$result = $this->db->sql_query($sql);

		$sql_ary = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$sql_ary[] = [
				'smiley_id' => (int) $row['smiley_id'],
				'emoji_seq' => (string) $row['emoji_seq'],
			];
		}
		$this->db->sql_freeresult($result);

		if (!empty($sql_ary))
		{
			$this->db->sql_multi_insert($this->table_prefix . 'modern_smiley_map', $sql_ary);
		}
	}
}
