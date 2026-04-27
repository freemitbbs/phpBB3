<?php
/**
 * Post Love extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Avathar.be
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace avathar\postlove\migrations;

class release_2_2_7_performance extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\avathar\postlove\migrations\release_2_2_6_forum_exclusions',
		];
	}

	public function update_schema()
	{
		return [
			'add_index' => [
				$this->table_prefix . 'posts_likes' => [
					'postlove_liketime_post' => [
						'liketime',
						'post_id',
					],
					'postlove_post_liketime_user' => [
						'post_id',
						'liketime',
						'user_id',
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_keys' => [
				$this->table_prefix . 'posts_likes' => [
					'postlove_liketime_post',
					'postlove_post_liketime_user',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['postlove_summary_cache_version', '0']],
		];
	}
}
