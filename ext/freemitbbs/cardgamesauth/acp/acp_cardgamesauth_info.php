<?php

namespace freemitbbs\cardgamesauth\acp;

class acp_cardgamesauth_info
{
	public function module()
	{
		return [
			'filename' => '\freemitbbs\cardgamesauth\acp\acp_cardgamesauth_module',
			'title' => 'ACP_CARDGAMESAUTH',
			'modes' => [
				'settings' => [
					'title' => 'ACP_CARDGAMESAUTH_SETTINGS',
					'auth' => 'ext_freemitbbs/cardgamesauth && acl_a_board',
					'cat' => ['ACP_CARDGAMESAUTH_GRP'],
				],
			],
		];
	}
}
