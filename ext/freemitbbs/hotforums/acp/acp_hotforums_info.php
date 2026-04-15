<?php

namespace freemitbbs\hotforums\acp;

class acp_hotforums_info
{
	public function module()
	{
		return [
			'filename' => '\freemitbbs\hotforums\acp\acp_hotforums_module',
			'title' => 'ACP_HOTFORUMS',
			'version' => '1.0.0',
			'modes' => [
				'main' => [
					'title' => 'ACP_HOTFORUMS',
					'auth' => 'ext_freemitbbs/hotforums && acl_a_board',
					'cat' => ['ACP_HOTFORUMS_GRP'],
				],
			],
		];
	}
}
