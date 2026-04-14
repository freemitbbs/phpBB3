<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_3 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_2',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['toptopics_reply_weight', '0.75']],
			['config.add', ['toptopics_view_weight', '0.15']],
			['config.update', ['toptopics_version', '1.1.3']],
		];
	}
}
