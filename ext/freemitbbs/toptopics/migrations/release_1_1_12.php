<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_12 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_11',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'clear_materialized_reputation']]],
			['config.update', ['toptopics_version', '1.1.12']],
		];
	}

	public function clear_materialized_reputation(): void
	{
		$sql = 'DELETE FROM ' . $this->table_prefix . 'toptopics_user_reputation';
		$this->db->sql_query($sql);
	}
}
