<?php

namespace freemitbbs\topicmover\acp;

class acp_topicmover_info
{
	public function module()
	{
		return [
			'filename' => '\freemitbbs\topicmover\acp\acp_topicmover_module',
			'title' => 'ACP_TOPICMOVER',
			'version' => '1.0.2',
			'modes' => [
				'main' => [
					'title' => 'ACP_TOPICMOVER',
					'auth' => 'ext_freemitbbs/topicmover && acl_a_board',
					'cat' => ['ACP_TOPICMOVER_GRP'],
				],
			],
		];
	}
}
