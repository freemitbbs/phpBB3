<?php
/**
*
* @package MoT DIM v0.1.0
* @copyright (c) 2024 Mike-on-Tour
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace mot\dim\acp;

class mot_dim_acp_info
{
	public function module()
	{
		return [
			'filename'	=> '\mot\dim\acp\mot_dim_acp_module',
			'title'		=> 'ACP_MOT_DIM',
			'modes'		=> [
				'settings'			=> [
					'title'	=> 'ACP_MOT_DIM_SETTINGS',
					'auth'	=> 'ext_mot/dim && acl_a_board',
					'cat'	=> ['ACP_MOT_DIM'],
				],
			],
		];
	}
}
