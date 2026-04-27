<?php

namespace freemitbbs\hotforums\migrations;

class release_1_0_1 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\hotforums\migrations\release_1_0_0',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['hotforums_viewership_cache_seconds', 600]],
			['config.update', ['hotforums_version', '1.0.1']],
		];
	}
}
