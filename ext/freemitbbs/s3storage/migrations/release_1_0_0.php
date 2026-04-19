<?php

namespace freemitbbs\s3storage\migrations;

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
			['config.add', ['s3storage_version', '1.0.0']],
			['config.add', ['s3storage_endpoint', '']],
			['config.add', ['s3storage_region', 'us-east-1']],
			['config.add', ['s3storage_bucket', '']],
			['config.add', ['s3storage_access_key', '']],
			['config.add', ['s3storage_secret_key', '']],
			['config.add', ['s3storage_public_base_url', '']],
			['config.add', ['s3storage_use_path_style', 0]],
			['custom', [[$this, 'import_legacy_videoupload_config']]],
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_S3STORAGE_GRP',
			]],
			['module.add', [
				'acp',
				'ACP_S3STORAGE_GRP',
				[
					'module_basename' => '\freemitbbs\s3storage\acp\acp_s3storage_module',
					'module_mode' => ['main'],
					'module_auth' => 'ext_freemitbbs/s3storage && acl_a_board',
				],
			]],
		];
	}

	public function import_legacy_videoupload_config()
	{
		$shared_keys = [
			's3storage_endpoint' => ['source' => 'videoupload_s3_endpoint', 'default' => ''],
			's3storage_region' => ['source' => 'videoupload_s3_region', 'default' => 'us-east-1'],
			's3storage_bucket' => ['source' => 'videoupload_s3_bucket', 'default' => ''],
			's3storage_access_key' => ['source' => 'videoupload_s3_access_key', 'default' => ''],
			's3storage_secret_key' => ['source' => 'videoupload_s3_secret_key', 'default' => ''],
			's3storage_public_base_url' => ['source' => 'videoupload_s3_public_base_url', 'default' => ''],
			's3storage_use_path_style' => ['source' => 'videoupload_s3_use_path_style', 'default' => '0'],
		];

		foreach ($shared_keys as $target_key => $mapping)
		{
			$source_key = $mapping['source'];
			$default = (string) $mapping['default'];
			$target = (string) ($this->config[$target_key] ?? '');
			$source = (string) ($this->config[$source_key] ?? '');

			if (($target === '' || $target === $default) && $source !== '')
			{
				$this->config->set($target_key, $source);
			}
		}

		return true;
	}
}
