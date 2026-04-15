<?php

namespace freemitbbs\videoupload\acp;

class acp_videoupload_info
{
	public function module()
	{
		return [
			'filename' => '\freemitbbs\videoupload\acp\acp_videoupload_module',
			'title' => 'ACP_VIDEOUPLOAD',
			'version' => '1.0.0',
			'modes' => [
				'main' => [
					'title' => 'ACP_VIDEOUPLOAD',
					'auth' => 'ext_freemitbbs/videoupload && acl_a_board',
					'cat' => ['ACP_VIDEOUPLOAD_GRP'],
				],
			],
		];
	}
}
