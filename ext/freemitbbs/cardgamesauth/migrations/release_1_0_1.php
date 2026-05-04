<?php

namespace freemitbbs\cardgamesauth\migrations;

class release_1_0_1 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\cardgamesauth\migrations\release_1_0_0',
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.1']],
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_CARDGAMESAUTH_GRP',
			]],
			['module.add', [
				'acp',
				'ACP_CARDGAMESAUTH_GRP',
				[
					'module_basename' => '\freemitbbs\cardgamesauth\acp\acp_cardgamesauth_module',
					'module_mode' => ['settings'],
					'module_auth' => 'ext_freemitbbs/cardgamesauth && acl_a_board',
				],
			]],
		];
	}
}
