<?php

namespace freemitbbs\modernsmiley\acp;

class acp_modernsmiley_info
{
	public function module()
	{
		return [
			'filename' => '\freemitbbs\modernsmiley\acp\acp_modernsmiley_module',
			'title' => 'ACP_MODERNSMILEY',
			'version' => '1.0.0',
			'modes' => [
				'main' => [
					'title' => 'ACP_MODERNSMILEY',
					'auth' => 'ext_freemitbbs/modernsmiley && acl_a_icons',
					'cat' => ['ACP_MESSAGES'],
				],
			],
		];
	}
}
