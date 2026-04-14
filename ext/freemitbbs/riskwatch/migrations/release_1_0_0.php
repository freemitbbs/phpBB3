<?php

namespace freemitbbs\riskwatch\migrations;

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
				$this->table_prefix . 'user_risk_state' => [
					'COLUMNS' => [
						'user_id' => ['UINT:8', 0],
						'risk_score' => ['INT:11', 0],
						'risk_level' => ['TINT:2', 0],
						'warnings_count' => ['UINT:8', 0],
						'open_reporters_30d' => ['UINT:8', 0],
						'unapproved_posts_30d' => ['UINT:8', 0],
						'login_attempts' => ['UINT:8', 0],
						'active_ban' => ['BOOL', 0],
						'manual_adjustment' => ['INT:11', 0],
						'warning_points' => ['INT:11', 0],
						'report_points' => ['INT:11', 0],
						'unapproved_points' => ['INT:11', 0],
						'login_points' => ['INT:11', 0],
						'ban_points' => ['INT:11', 0],
						'details_json' => ['TEXT_UNI', ''],
						'computed_time' => ['TIMESTAMP', 0],
						'last_alert_level' => ['TINT:2', 0],
						'last_alert_time' => ['TIMESTAMP', 0],
						'last_alert_hash' => ['VCHAR:32', ''],
					],
					'PRIMARY_KEY' => 'user_id',
					'KEYS' => [
						'risk_score' => ['INDEX', 'risk_score'],
						'risk_level' => ['INDEX', 'risk_level'],
						'computed_time' => ['INDEX', 'computed_time'],
					],
				],
				$this->table_prefix . 'user_risk_manual' => [
					'COLUMNS' => [
						'manual_id' => ['UINT:8', null, 'auto_increment'],
						'user_id' => ['UINT:8', 0],
						'delta' => ['INT:11', 0],
						'reason' => ['VCHAR_UNI:255', ''],
						'created_by' => ['UINT:8', 0],
						'created_time' => ['TIMESTAMP', 0],
						'expires_at' => ['TIMESTAMP', 0],
						'is_active' => ['BOOL', 1],
					],
					'PRIMARY_KEY' => 'manual_id',
					'KEYS' => [
						'user_id' => ['INDEX', 'user_id'],
						'user_active' => ['INDEX', ['user_id', 'is_active']],
						'active_expires' => ['INDEX', ['is_active', 'expires_at']],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'user_risk_state',
				$this->table_prefix . 'user_risk_manual',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['riskwatch_version', '1.0.0']],
			['config.add', ['riskwatch_refresh_seconds', 300]],
			['config.add', ['riskwatch_refresh_batch_size', 500]],
			['config.add', ['riskwatch_alert_cooldown_seconds', 86400]],
			['config.add', ['riskwatch_reports_days', 30]],
			['config.add', ['riskwatch_unapproved_days', 30]],
			['config.add', ['riskwatch_ignore_new_reporters_days', 0]],
			['config.add', ['riskwatch_threshold_watch', 15]],
			['config.add', ['riskwatch_threshold_high', 30]],
			['config.add', ['riskwatch_threshold_critical', 50]],
			['config.add', ['riskwatch_weight_warnings', 8]],
			['config.add', ['riskwatch_weight_reports', 2]],
			['config.add', ['riskwatch_weight_unapproved', 2]],
			['config.add', ['riskwatch_weight_login', 1]],
			['config.add', ['riskwatch_weight_ban', 30]],
			['config.add', ['riskwatch_cap_reporters', 8]],
			['config.add', ['riskwatch_cap_unapproved', 10]],
			['config.add', ['riskwatch_cap_login', 10]],
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_RISKWATCH_GRP',
			]],
			['module.add', [
				'acp',
				'ACP_RISKWATCH_GRP',
				[
					'module_basename' => '\freemitbbs\riskwatch\acp\acp_riskwatch_module',
					'module_mode' => ['main'],
					'module_auth' => 'ext_freemitbbs/riskwatch && acl_a_board',
				],
			]],
		];
	}
}
