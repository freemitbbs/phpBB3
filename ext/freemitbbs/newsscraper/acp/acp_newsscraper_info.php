<?php

namespace freemitbbs\newsscraper\acp;

class acp_newsscraper_info
{
	public function module()
	{
		return [
			'filename' => '\freemitbbs\newsscraper\acp\acp_newsscraper_module',
			'title' => 'ACP_NEWSSCRAPER',
			'version' => '1.0.1',
			'modes' => [
				'main' => [
					'title' => 'ACP_NEWSSCRAPER',
					'auth' => 'ext_freemitbbs/newsscraper && acl_a_board',
					'cat' => ['ACP_NEWSSCRAPER_GRP'],
				],
			],
		];
	}
}
