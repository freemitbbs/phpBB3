<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_26 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_25',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['toptopics_reputation_dislike_weight', '0.35']],
			['custom', [[$this, 'clear_materialized_reputation']]],
			['config.update', ['toptopics_version', '1.1.26']],
		];
	}

	public function clear_materialized_reputation(): void
	{
		$sql = 'DELETE FROM ' . $this->table_prefix . 'toptopics_user_reputation';
		$this->db->sql_query($sql);
	}
}
