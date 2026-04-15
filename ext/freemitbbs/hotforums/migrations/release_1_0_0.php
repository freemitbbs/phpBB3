<?php

namespace freemitbbs\hotforums\migrations;

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
			['config.add', ['hotforums_version', '1.0.0']],
			['config.add', ['hotforums_index_limit', 8]],
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_HOTFORUMS_GRP',
			]],
			['module.add', [
				'acp',
				'ACP_HOTFORUMS_GRP',
				[
					'module_basename' => '\freemitbbs\hotforums\acp\acp_hotforums_module',
					'module_mode' => ['main'],
					'module_auth' => 'ext_freemitbbs/hotforums && acl_a_board',
				],
			]],
		];
	}
}
