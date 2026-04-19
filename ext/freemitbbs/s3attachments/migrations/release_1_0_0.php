<?php

namespace freemitbbs\s3attachments\migrations;

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
			['config.add', ['s3attachments_version', '1.0.0']],
			['config.add', ['s3attachments_enabled', 0]],
			['config.add', ['s3attachments_path_prefix', 'attachments']],
			['config.add', ['s3attachments_signed_urls', 1]],
			['config.add', ['s3attachments_signed_url_ttl', 300]],
			['config.add', ['s3attachments_acl', 'private']],
		];
	}
}
