<?php
/**
 * Post Love extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Avathar.be
 * @license GNU General Public License, version 2 (GPL-2.0)
 */
namespace avathar\postlove\migrations;

class release_2_2_6_forum_exclusions extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\avathar\postlove\migrations\release_2_2_5_most_liked_page',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['postlove_index_excluded_forum_ids', '']],
			['config.add', ['postlove_forum_excluded_forum_ids', '']],
		];
	}
}
