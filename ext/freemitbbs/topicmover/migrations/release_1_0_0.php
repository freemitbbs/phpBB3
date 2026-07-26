<?php

namespace freemitbbs\topicmover\migrations;

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
			['config.add', ['topicmover_threshold', '5']],
			['config.add', ['topicmover_interval_seconds', '3600']],
			['config.add', ['topicmover_api_endpoint', 'https://api.deepseek.com/chat/completions']],
			['config.add', ['topicmover_api_key', '']],
			['config.add', ['topicmover_excluded_forum_ids', '']],
			['config.add', ['topicmover_model', 'deepseek-v4-flash']],
			['config.add', ['topicmover_version', '1.0.0']],
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_TOPICMOVER_GRP',
			]],
			['module.add', [
				'acp',
				'ACP_TOPICMOVER_GRP',
				[
					'module_basename' => '\freemitbbs\topicmover\acp\acp_topicmover_module',
					'module_mode' => ['main'],
					'module_auth' => 'ext_freemitbbs/topicmover && acl_a_board',
				],
			]],
		];
	}
}
