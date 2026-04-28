<?php

namespace freemitbbs\adultaccess\ucp;

class main_info
{
	public function module()
	{
		return [
			'filename' => '\freemitbbs\adultaccess\ucp\main_module',
			'title' => 'UCP_ADULTACCESS',
			'modes' => [
				'settings' => [
					'title' => 'UCP_ADULTACCESS',
					'auth' => 'ext_freemitbbs/adultaccess',
					'cat' => ['UCP_PREFS'],
				],
			],
		];
	}
}
