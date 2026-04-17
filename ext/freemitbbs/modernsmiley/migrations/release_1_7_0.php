<?php

namespace freemitbbs\modernsmiley\migrations;

class release_1_7_0 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\modernsmiley\migrations\release_1_6_0',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'normalize_html_encoded_smiley_codes']]],
			['config.update', ['modernsmiley_version', '1.7.0']],
		];
	}

	public function normalize_html_encoded_smiley_codes(): bool
	{
		$smilies_table = $this->table_prefix . 'smilies';
		$sql = 'SELECT smiley_id, code
			FROM ' . $smilies_table;
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$code = (string) $row['code'];
			$normalized = $this->normalize_smiley_code($code);

			if ($normalized === $code)
			{
				continue;
			}

			$this->sql_query('UPDATE ' . $smilies_table . "
				SET code = '" . $this->db->sql_escape($normalized) . "'
				WHERE smiley_id = " . (int) $row['smiley_id']);
		}

		$this->db->sql_freeresult($result);

		return true;
	}

	private function normalize_smiley_code(string $code): string
	{
		$code = trim($code);

		for ($i = 0; $i < 3; $i++)
		{
			$decoded = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			if ($decoded === $code)
			{
				break;
			}

			$code = $decoded;
		}

		return trim($code);
	}
}
