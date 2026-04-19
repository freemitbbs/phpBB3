<?php

namespace freemitbbs\s3attachments\migrations;

class release_1_0_1 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\s3attachments\migrations\release_1_0_0',
		];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'attachments' => [
					's3_object_key' => ['VCHAR:255', ''],
					's3_thumb_object_key' => ['VCHAR:255', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'attachments' => [
					's3_object_key',
					's3_thumb_object_key',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['s3attachments_object_keys_ready', 1]],
			['config.update', ['s3attachments_version', '1.0.1']],
		];
	}
}
