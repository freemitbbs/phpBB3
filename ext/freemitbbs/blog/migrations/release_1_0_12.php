<?php

namespace freemitbbs\blog\migrations;

class release_1_0_12 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\blog\migrations\release_1_0_11',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['freemitbbs_blog_index_latest_limit', '10']],
			['config.add', ['freemitbbs_blog_index_latest_days', '0']],
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_BLOG_GRP',
			]],
			['module.add', [
				'acp',
				'ACP_BLOG_GRP',
				[
					'module_basename' => '\freemitbbs\blog\acp\acp_blog_module',
					'module_mode' => ['main'],
					'module_auth' => 'ext_freemitbbs/blog && acl_a_board',
				],
			]],
			['config.update', ['freemitbbs_blog_version', '1.0.12']],
		];
	}
}
