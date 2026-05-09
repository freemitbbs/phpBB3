<?php

namespace freemitbbs\cardgamesauth\migrations;

class release_1_0_6 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\cardgamesauth\migrations\release_1_0_5',
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.6']],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.5']],
		];
	}
}
