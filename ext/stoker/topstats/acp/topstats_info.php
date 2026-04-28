<?php
/**
 *
 * Top Stats extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 stoker - https://phpbb3bbcodes.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

declare(strict_types=1);

namespace stoker\topstats\acp;

/**
 * ACP module info for the Top Stats extension.
 * Defines the module structure and permissions for the ACP interface.
 */
final class topstats_info
{
	/**
	 * Return ACP module definition with three modes: Recent, Stats, and Top Poster.
	 *
	 * @return array{filename: string, title: string, modes: array<string, array{title: string, auth: string}>}
	 */
	public function module(): array
	{
		return [
			'filename' => '\stoker\topstats\acp\topstats_module',
			'title' => 'ACP_TOPSTATS',
			'modes' => [
				'recent' => [
					'title' => 'ACP_TS_RECENT',
					'auth' => 'ext_stoker/topstats && acl_a_board',
				],
				'stats' => [
					'title' => 'ACP_TS_STATS',
					'auth' => 'ext_stoker/topstats && acl_a_board',
				],
				'topposter' => [
					'title' => 'ACP_TS_TOPPOSTER',
					'auth' => 'ext_stoker/topstats && acl_a_board',
				],
			],
		];
	}
}
