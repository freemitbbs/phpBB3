<?php

namespace freemitbbs\modernsmiley\migrations;

class release_1_6_0 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\modernsmiley\migrations\release_1_5_0',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'normalize_remote_smiley_urls']]],
			['config.update', ['modernsmiley_version', '1.6.0']],
		];
	}

	public function normalize_remote_smiley_urls(): bool
	{
		$placeholder_url = $this->get_placeholder_url();
		if ($placeholder_url === '')
		{
			return true;
		}

		$sql = 'UPDATE ' . $this->table_prefix . "smilies s
			INNER JOIN " . $this->table_prefix . "modern_smiley_map m
				ON m.smiley_id = s.smiley_id
			SET s.smiley_url = '" . $this->db->sql_escape($placeholder_url) . "'
			WHERE s.smiley_url LIKE 'http://%'
				OR s.smiley_url LIKE 'https://%'";
		$this->sql_query($sql);

		return true;
	}

	private function get_placeholder_url(): string
	{
		$smilies_table = $this->table_prefix . 'smilies';
		$sql = 'SELECT smiley_url
			FROM ' . $smilies_table . "
			WHERE smiley_url = 'icon_e_smile.gif'
			ORDER BY smiley_id";
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($row)
		{
			return (string) $row['smiley_url'];
		}

		$sql = 'SELECT smiley_url
			FROM ' . $smilies_table . "
			WHERE smiley_url NOT LIKE 'http://%'
				AND smiley_url NOT LIKE 'https://%'
			ORDER BY smiley_id";
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ? (string) $row['smiley_url'] : '';
	}
}
