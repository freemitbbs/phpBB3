<?php

namespace freemitbbs\topicmover\migrations;

class release_1_0_2 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\topicmover\migrations\release_1_0_1',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['topicmover_min_latest_reply_age_hours', '12']],
			['config.update', ['topicmover_version', '1.0.2']],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['topicmover_min_latest_reply_age_hours']],
			['config.update', ['topicmover_version', '1.0.1']],
		];
	}
}
