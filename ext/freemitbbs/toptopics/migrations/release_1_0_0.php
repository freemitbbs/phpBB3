<?php

namespace freemitbbs\toptopics\migrations;

class release_1_0_0 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\avathar\postlove\migrations\release_2_2_3',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'posts_dislikes' => [
					'COLUMNS' => [
						'post_id' => ['UINT:8', 0],
						'user_id' => ['UINT:8', 0],
						'disliketime' => ['TIMESTAMP', 0],
						'disliked_user_id' => ['UINT:8', 0],
					],
					'PRIMARY_KEY' => ['post_id', 'user_id'],
					'KEYS' => [
						'disliketime' => ['INDEX', 'disliketime'],
						'user_id' => ['INDEX', 'user_id'],
						'disliked_user_id' => ['INDEX', 'disliked_user_id'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'posts_dislikes',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['toptopics_version', '1.0.0']],
			['config.add', ['toptopics_index_limit', 10]],
			['config.add', ['toptopics_forum_limit', 5]],
			['config.add', ['toptopics_downvote_min_posts', 5]],
			['config.add', ['toptopics_downvote_per_minute', 2]],
			['config.add', ['toptopics_downvote_per_day', 20]],
			['permission.add', ['u_toptopics_dislike', true]],
			['permission.permission_set', ['REGISTERED', 'u_toptopics_dislike', 'group']],
			['permission.permission_set', ['REGISTERED_COPPA', 'u_toptopics_dislike', 'group']],
			['permission.permission_set', ['NEWLY_REGISTERED', 'u_toptopics_dislike', 'group']],
			['permission.permission_set', ['GLOBAL_MODERATORS', 'u_toptopics_dislike', 'group']],
			['permission.permission_set', ['ADMINISTRATORS', 'u_toptopics_dislike', 'group']],
		];
	}
}
