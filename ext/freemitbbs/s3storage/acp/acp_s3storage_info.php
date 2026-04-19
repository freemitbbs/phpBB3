<?php

namespace freemitbbs\s3storage\acp;

class acp_s3storage_info
{
	public function module()
	{
		return [
			'filename' => '\freemitbbs\s3storage\acp\acp_s3storage_module',
			'title' => 'ACP_S3STORAGE',
			'modes' => [
				'main' => [
					'title' => 'ACP_S3STORAGE',
					'auth' => 'ext_freemitbbs/s3storage && acl_a_board',
					'cat' => ['ACP_S3STORAGE_GRP'],
				],
			],
		];
	}
}
