<?php

namespace freemitbbs\toptopics\acp;

class acp_toptopics_info
{
	public function module()
	{
		return [
			'filename' => '\freemitbbs\toptopics\acp\acp_toptopics_module',
			'title' => 'ACP_TOPTOPICS',
				'version' => '1.1.2',
			'modes' => [
				'main' => [
					'title' => 'ACP_TOPTOPICS',
					'auth' => 'ext_freemitbbs/toptopics && acl_a_board',
					'cat' => ['ACP_TOPTOPICS_GRP'],
				],
			],
		];
	}
}
