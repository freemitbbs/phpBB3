<?php

namespace freemitbbs\blog\migrations;

class release_1_0_0 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\phpbb\db\migration\data\v33x\v3310',
		];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'blog_topics' => [
					'COLUMNS' => [
						'topic_id' => ['UINT:8', 0],
						'user_id' => ['UINT:8', 0],
						'source_post_id' => ['UINT:8', 0],
						'source_topic_id' => ['UINT:8', 0],
						'created_time' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'topic_id',
					'KEYS' => [
						'user_source' => ['INDEX', ['user_id', 'source_post_id']],
						'source_topic' => ['INDEX', ['source_topic_id']],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'blog_topics',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['freemitbbs_blog_version', '1.0.0']],
			['config.add', ['freemitbbs_blog_forum_id', 0]],
			['permission.add', ['u_blog_create', true]],
			['permission.permission_set', ['REGISTERED', 'u_blog_create', 'group']],
			['permission.permission_set', ['REGISTERED_COPPA', 'u_blog_create', 'group']],
			['permission.permission_set', ['ADMINISTRATORS', 'u_blog_create', 'group']],
			['module.add', [
				'ucp',
				'UCP_MAIN',
				[
					'module_basename' => '\freemitbbs\blog\ucp\main_module',
					'module_mode' => ['manage'],
					'module_auth' => 'ext_freemitbbs/blog && acl_u_blog_create',
				],
			]],
		];
	}
}
