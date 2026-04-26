<?php

namespace freemitbbs\videoupload\migrations;

class release_1_0_1 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\videoupload\migrations\release_1_0_0',
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['videoupload_version', '1.0.1']],
		];
	}
}
