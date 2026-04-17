<?php
/**
 *
 * Precise Similar Topics
 *
 * @copyright (c) 2026 Matt Friedman
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace vse\similartopics\migrations\release_1_8_x;

class guest_permission extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT gr.auth_setting
			FROM ' . ACL_GROUPS_TABLE . ' gr
			JOIN ' . GROUPS_TABLE . ' g
				ON g.group_id = gr.group_id
			JOIN ' . ACL_OPTIONS_TABLE . ' o
				ON o.auth_option_id = gr.auth_option_id
			WHERE g.group_name = \'GUESTS\'
				AND o.auth_option = \'u_similar_topics\'';
		$result = $this->db->sql_query($sql);
		$auth_setting = (int) $this->db->sql_fetchfield('auth_setting');
		$this->db->sql_freeresult($result);

		return $auth_setting === ACL_YES;
	}

	public static function depends_on()
	{
		return [
			'\vse\similartopics\migrations\release_1_8_x\search_title_index',
		];
	}

	public function update_data()
	{
		return [
			['permission.permission_set', ['GUESTS', 'u_similar_topics', 'group']],
		];
	}
}
