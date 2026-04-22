<?php

namespace freemitbbs\blog\ucp;

class main_info
{
	public function module()
	{
		return [
			'filename' => '\freemitbbs\blog\ucp\main_module',
			'title' => 'UCP_BLOG',
			'modes' => [
				'manage' => [
					'title' => 'UCP_BLOG_MANAGE',
					'auth' => 'ext_freemitbbs/blog && acl_u_blog_create',
					'cat' => ['UCP_MAIN'],
				],
			],
		];
	}
}
