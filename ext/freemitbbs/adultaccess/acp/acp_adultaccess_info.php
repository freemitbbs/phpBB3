<?php

namespace freemitbbs\adultaccess\acp;

class acp_adultaccess_info
{
	public function module()
	{
		return [
			'filename' => '\freemitbbs\adultaccess\acp\acp_adultaccess_module',
			'title' => 'ACP_ADULTACCESS',
			'version' => '1.0.0',
			'modes' => [
				'main' => [
					'title' => 'ACP_ADULTACCESS',
					'auth' => 'ext_freemitbbs/adultaccess && acl_a_board',
					'cat' => ['ACP_ADULTACCESS_GRP'],
				],
			],
		];
	}
}
