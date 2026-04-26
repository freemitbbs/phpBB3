<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_16 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_15',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'clear_materialized_scopes']]],
			['config.update', ['toptopics_version', '1.1.16']],
		];
	}

	public function clear_materialized_scopes(): void
	{
		$this->db->sql_query('DELETE FROM ' . $this->table_prefix . 'toptopics_scope_forums');
		$this->db->sql_query('DELETE FROM ' . $this->table_prefix . 'toptopics_scope_snapshots');
	}
}
