<?php

namespace freemitbbs\riskwatch\acp;

class acp_riskwatch_info
{
	public function module()
	{
		return [
			'filename' => '\freemitbbs\riskwatch\acp\acp_riskwatch_module',
			'title' => 'ACP_RISKWATCH',
			'version' => '1.0.0',
			'modes' => [
				'main' => [
					'title' => 'ACP_RISKWATCH',
					'auth' => 'ext_freemitbbs/riskwatch && acl_a_board',
					'cat' => ['ACP_RISKWATCH_GRP'],
				],
			],
		];
	}
}
