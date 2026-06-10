<?php

namespace freemitbbs\newsscraper\migrations;

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
				$this->table_prefix . 'newsscraper_seen' => [
					'COLUMNS' => [
						'url_hash' => ['VCHAR:64', ''],
						'source_key' => ['VCHAR:64', ''],
						'status' => ['VCHAR:16', 'candidate'],
						'score' => ['UINT:3', 0],
						'first_seen' => ['TIMESTAMP', 0],
						'updated_time' => ['TIMESTAMP', 0],
						'topic_id' => ['UINT:8', 0],
						'url' => ['VCHAR:1024', ''],
						'title' => ['VCHAR:255', ''],
						'reason' => ['VCHAR:255', ''],
					],
					'PRIMARY_KEY' => ['url_hash'],
					'KEYS' => [
						'status_time' => ['INDEX', ['status', 'updated_time']],
						'source_time' => ['INDEX', ['source_key', 'updated_time']],
						'topic_id' => ['INDEX', 'topic_id'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'newsscraper_seen',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['newsscraper_version', '1.0.0']],
			['config.add', ['newsscraper_enabled', '0']],
			['config.add', ['newsscraper_digest_forum_id', '0']],
			['config.add', ['newsscraper_interval_seconds', '3600']],
			['config.add', ['newsscraper_candidates_per_run', '60']],
			['config.add', ['newsscraper_max_selected_per_run', '4']],
			['config.add', ['newsscraper_min_interest_score', '65']],
			['config.add', ['newsscraper_per_source_cap', '2']],
			['config.add', ['newsscraper_frontpage_count', '20']],
			['config.add', ['newsscraper_title_max_chars', '30']],
			['config.add', ['newsscraper_seen_retention_days', '30']],
			['config.add', ['newsscraper_enabled_sources', 'guardian,bbc,dw,cnbc,ars,zerohedge,foxnews,wenxuecity,zaobao,sina_world,sohu']],
			['config.add', ['newsscraper_api_endpoint', '']],
			['config.add', ['newsscraper_model', '']],
			['config.add', ['newsscraper_api_key', '']],
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_NEWSSCRAPER_GRP',
			]],
			['module.add', [
				'acp',
				'ACP_NEWSSCRAPER_GRP',
				[
					'module_basename' => '\freemitbbs\newsscraper\acp\acp_newsscraper_module',
					'module_mode' => ['main'],
					'module_auth' => 'ext_freemitbbs/newsscraper && acl_a_board',
				],
			]],
		];
	}
}
