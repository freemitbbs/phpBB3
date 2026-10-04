<?php

namespace freemitbbs\newmemberguard\migrations;

class release_1_0_0 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\phpbb\db\migration\data\v33x\v3310',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['freemitbbs_newmemberguard_version', '1.0.0']],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['freemitbbs_newmemberguard_version']],
		];
	}
}
