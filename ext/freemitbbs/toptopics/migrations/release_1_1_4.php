<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_4 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_3',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['toptopics_content_weight', '0.35']],
			['config.update', ['toptopics_version', '1.1.4']],
		];
	}
}
