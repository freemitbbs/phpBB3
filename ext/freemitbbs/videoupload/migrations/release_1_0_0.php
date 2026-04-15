<?php

namespace freemitbbs\videoupload\migrations;

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
			['config.add', ['videoupload_version', '1.0.0']],
			['config.add', ['videoupload_enabled', 0]],
			['config.add', ['videoupload_max_size_mb', 64]],
			['config.add', ['videoupload_s3_endpoint', '']],
			['config.add', ['videoupload_s3_region', 'us-east-1']],
			['config.add', ['videoupload_s3_bucket', '']],
			['config.add', ['videoupload_s3_access_key', '']],
			['config.add', ['videoupload_s3_secret_key', '']],
			['config.add', ['videoupload_s3_path_prefix', 'videos']],
			['config.add', ['videoupload_s3_public_base_url', '']],
			['config.add', ['videoupload_s3_use_path_style', 0]],
			['config.add', ['videoupload_s3_acl', 'public-read']],
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_VIDEOUPLOAD_GRP',
			]],
			['module.add', [
				'acp',
				'ACP_VIDEOUPLOAD_GRP',
				[
					'module_basename' => '\freemitbbs\videoupload\acp\acp_videoupload_module',
					'module_mode' => ['main'],
					'module_auth' => 'ext_freemitbbs/videoupload && acl_a_board',
				],
			]],
		];
	}
}
