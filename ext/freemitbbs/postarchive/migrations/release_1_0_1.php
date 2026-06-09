<?php

namespace freemitbbs\postarchive\migrations;

class release_1_0_1 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\postarchive\migrations\release_1_0_0',
		];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'postarchive_jobs');
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'postarchive_jobs' => [
					'COLUMNS' => [
						'job_id' => ['UINT', null, 'auto_increment'],
						'user_id' => ['UINT:8', 0],
						'status' => ['VCHAR:16', ''],
						'requested_time' => ['TIMESTAMP', 0],
						'started_time' => ['TIMESTAMP', 0],
						'completed_time' => ['TIMESTAMP', 0],
						'archive_id' => ['UINT', 0],
						'physical_filename' => ['VCHAR:255', ''],
						'real_filename' => ['VCHAR:255', ''],
						'attempt_count' => ['USINT', 0],
						'last_error' => ['VCHAR:255', ''],
					],
					'PRIMARY_KEY' => 'job_id',
					'KEYS' => [
						'user_status' => ['INDEX', ['user_id', 'status', 'requested_time']],
						'status_requested' => ['INDEX', ['status', 'requested_time']],
						'archive_id' => ['INDEX', 'archive_id'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'postarchive_jobs',
			],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'delete_job_files']]],
		];
	}

	public function delete_job_files(): void
	{
		$sql = 'SELECT physical_filename
			FROM ' . $this->table_prefix . "postarchive_jobs
			WHERE physical_filename <> ''";
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
}
