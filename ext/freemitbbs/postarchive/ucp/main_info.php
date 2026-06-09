<?php

namespace freemitbbs\postarchive\ucp;

class main_info
{
	public function module()
	{
		return [
			'filename' => '\freemitbbs\postarchive\ucp\main_module',
			'title' => 'UCP_POSTARCHIVE',
			'modes' => [
				'download' => [
					'title' => 'UCP_POSTARCHIVE',
					'auth' => 'ext_freemitbbs/postarchive',
					'cat' => ['UCP_MAIN'],
				],
			],
		];
	}
}
