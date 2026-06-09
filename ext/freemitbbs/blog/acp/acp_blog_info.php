<?php

namespace freemitbbs\blog\acp;

class acp_blog_info
{
	public function module()
	{
		return [
			'filename' => '\freemitbbs\blog\acp\acp_blog_module',
			'title' => 'ACP_BLOG',
			'version' => '1.0.12',
			'modes' => [
				'main' => [
					'title' => 'ACP_BLOG',
					'auth' => 'ext_freemitbbs/blog && acl_a_board',
					'cat' => ['ACP_BLOG_GRP'],
				],
			],
		];
	}
}
