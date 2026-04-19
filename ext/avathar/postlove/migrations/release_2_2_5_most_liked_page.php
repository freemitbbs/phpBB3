<?php
/**
 * Post Love extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Avathar.be
 * @license GNU General Public License, version 2 (GPL-2.0)
 */
namespace avathar\postlove\migrations;

class release_2_2_5_most_liked_page extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\avathar\postlove\migrations\release_2_2_4_guest_summary',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['postlove_most_liked_page_length', 10]],
		];
	}
}
