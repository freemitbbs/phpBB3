<?php

namespace freemitbbs\searchqueue\migrations;

class release_1_0_1 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\searchqueue\migrations\release_1_0_0',
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['fulltext_native_common_thres', '0']],
			['config.update', ['fulltext_native_max_chars', '40']],
			['config.update', ['freemitbbs_searchqueue_version', '1.0.1']],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['fulltext_native_common_thres', '5']],
			['config.update', ['fulltext_native_max_chars', '14']],
			['config.update', ['freemitbbs_searchqueue_version', '1.0.0']],
		];
	}
}
