<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_7 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_6',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['toptopics_candidate_pool_limit', '2000']],
			['config.update', ['toptopics_version', '1.1.7']],
		];
	}
}
