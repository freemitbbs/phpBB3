<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_10 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_9',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['toptopics_index_excluded_forum_ids', '']],
			['config.update', ['toptopics_version', '1.1.10']],
		];
	}
}
