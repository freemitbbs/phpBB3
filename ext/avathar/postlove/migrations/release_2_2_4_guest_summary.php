<?php
/**
 * Post Love extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Avathar.be
 * @license GNU General Public License, version 2 (GPL-2.0)
 */
namespace avathar\postlove\migrations;

/**
 * Allows anonymous visitors to see the most-liked-posts summary.
 *
 * Guests must not receive u_postlove itself, because all guests share the
 * anonymous user id and cannot safely toggle likes. This grants only the
 * read-only summary permission.
 */
class release_2_2_4_guest_summary extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\avathar\postlove\migrations\release_2_2_3',
			'\avathar\postlove\migrations\release_2_2_0_add_summary_permission',
			'\avathar\postlove\migrations\release_2_2_0_deny_guest_like',
		];
	}

	public function update_data()
	{
		return [
			['permission.permission_set', ['GUESTS', 'u_postlove_summary', 'group']],
			['permission.permission_unset', ['GUESTS', 'u_postlove', 'group']],
		];
	}
}
