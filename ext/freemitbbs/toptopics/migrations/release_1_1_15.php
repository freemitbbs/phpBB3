<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_15 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_14',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['toptopics_per_forum_limit', 3]],
			['config.update', ['toptopics_version', '1.1.15']],
		];
	}
}
