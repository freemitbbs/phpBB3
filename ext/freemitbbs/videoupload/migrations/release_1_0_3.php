<?php

namespace freemitbbs\videoupload\migrations;

class release_1_0_3 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\videoupload\migrations\release_1_0_2',
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['videoupload_version', '1.0.3']],
		];
	}
}
