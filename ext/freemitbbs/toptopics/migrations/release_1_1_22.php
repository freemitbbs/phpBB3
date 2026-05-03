<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_22 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_1_21',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['toptopics_post_collapse_dislike_threshold', 5]],
			['config.update', ['toptopics_version', '1.1.22']],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['toptopics_post_collapse_dislike_threshold']],
		];
	}
}
