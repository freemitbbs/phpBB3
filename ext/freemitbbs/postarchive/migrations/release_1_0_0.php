<?php

namespace freemitbbs\postarchive\migrations;

class release_1_0_0 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\phpbb\db\migration\data\v33x\v3310',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'postarchive_archives' => [
					'COLUMNS' => [
						'archive_id' => ['UINT', null, 'auto_increment'],
						'user_id' => ['UINT:8', 0],
						'physical_filename' => ['VCHAR:255', ''],
						'real_filename' => ['VCHAR:255', ''],
						'created_time' => ['TIMESTAMP', 0],
						'expires_time' => ['TIMESTAMP', 0],
						'post_count' => ['UINT:8', 0],
						'filesize' => ['UINT:8', 0],
					],
					'PRIMARY_KEY' => 'archive_id',
					'KEYS' => [
						'user_expires' => ['INDEX', ['user_id', 'expires_time']],
						'expires_time' => ['INDEX', 'expires_time'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'postarchive_archives',
			],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'remove_extension_modules']]],
			['config.add', ['freemitbbs_postarchive_version', '1.0.0']],
			['module.add', [
				'ucp',
				'UCP_MAIN',
				[
					'module_langname' => 'UCP_POSTARCHIVE',
					'module_basename' => '\freemitbbs\postarchive\ucp\main_module',
					'module_mode' => 'download',
					'module_auth' => 'ext_freemitbbs/postarchive',
				],
			]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'delete_archive_files']]],
			['custom', [[$this, 'remove_extension_modules']]],
			['config.remove', ['freemitbbs_postarchive_version']],
		];
	}

	public function delete_archive_files(): void
	{
		$sql = 'SELECT physical_filename
			FROM ' . $this->table_prefix . 'postarchive_archives';
		$result = $this->db->sql_query($sql);

		$storage_dir = rtrim($this->phpbb_root_path, '/\\') . '/store/postarchive';
		while ($row = $this->db->sql_fetchrow($result))
		{
			$path = $storage_dir . '/' . basename((string) $row['physical_filename']);
			if (is_file($path))
			{
				@unlink($path);
			}
		}
		$this->db->sql_freeresult($result);
	}

	public function remove_extension_modules(): void
	{
		global $phpbb_container;

		$sql = 'SELECT module_id
			FROM ' . MODULES_TABLE . "
			WHERE module_class = 'ucp'
				AND module_langname = 'UCP_POSTARCHIVE'
			ORDER BY left_id DESC";
		$result = $this->db->sql_query($sql);

		$module_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$module_ids[] = (int) $row['module_id'];
		}
		$this->db->sql_freeresult($result);

		if (empty($module_ids))
		{
			return;
		}

		$module_manager = $phpbb_container->get('module.manager');
		foreach ($module_ids as $module_id)
		{
			try
			{
				$module_manager->delete_module($module_id, 'ucp');
			}
			catch (\phpbb\module\exception\module_exception $e)
			{
				$this->db->sql_query('DELETE FROM ' . MODULES_TABLE . '
					WHERE module_id = ' . $module_id . "
						AND module_class = 'ucp'");
			}
		}

		$module_manager->remove_cache_file('ucp');
	}
}
