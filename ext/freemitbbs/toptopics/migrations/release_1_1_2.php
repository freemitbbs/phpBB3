<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_2 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_1',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['toptopics_summary_cache_seconds', 600]],
			['config.update', ['toptopics_version', '1.1.2']],
		];
	}
}
