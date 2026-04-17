<?php

namespace freemitbbs\modernsmiley\migrations;

use freemitbbs\modernsmiley\service\mapper;

class release_1_4_0 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\modernsmiley\migrations\release_1_3_0',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['modernsmiley_hover_asset_url', mapper::DEFAULT_HOVER_ASSET_URL_PATTERN]],
			['config.add', ['modernsmiley_hover_asset_fallback_url', mapper::DEFAULT_HOVER_FALLBACK_ASSET_URL_PATTERN]],
			['config.update', ['modernsmiley_version', '1.4.0']],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['modernsmiley_hover_asset_url']],
			['config.remove', ['modernsmiley_hover_asset_fallback_url']],
		];
	}
}
