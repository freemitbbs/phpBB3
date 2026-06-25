<?php

namespace freemitbbs\postautosave\migrations;

class release_1_0_1 extends \phpbb\db\migration\migration
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
			['config.update', ['session_length', 86400]],
			['config.update', ['form_token_lifetime', 86400]],
			['config.update', ['ip_check', 0]],
			['config.update', ['browser_check', 0]],
			['config.update', ['forwarded_for_check', 0]],
			['config.update', ['allow_autologin', 1]],
			['config.add', ['freemitbbs_postautosave_version', '1.0.1']],
		];
	}
}
