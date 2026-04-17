<?php

namespace freemitbbs\modernsmiley\migrations;

use freemitbbs\modernsmiley\service\mapper;

class release_1_2_0 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\modernsmiley\migrations\release_1_1_0',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['modernsmiley_asset_url', mapper::DEFAULT_ASSET_URL_PATTERN]],
			['config.update', ['modernsmiley_version', '1.2.0']],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['modernsmiley_asset_url']],
		];
	}
}
