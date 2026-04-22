<?php

namespace freemitbbs\blog\migrations;

class release_1_0_1 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\blog\migrations\release_1_0_0',
		];
	}

	public function update_schema()
	{
		return [];
	}

	public function revert_schema()
	{
		return [];
	}

	public function update_data()
	{
		return [
			['config.update', ['freemitbbs_blog_version', '1.0.1']],
		];
	}
}
