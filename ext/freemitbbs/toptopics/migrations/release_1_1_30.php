<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_30 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_29',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['toptopics_flat_topic_list_per_page', 50]],
			['config.update', ['toptopics_version', '1.1.30']],
		];
	}
}
