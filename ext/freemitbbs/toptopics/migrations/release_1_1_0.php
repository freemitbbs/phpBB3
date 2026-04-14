<?php

namespace freemitbbs\toptopics\migrations;

class release_1_1_0 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\toptopics\migrations\release_1_0_0',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['toptopics_lookback_days', 365]],
			['config.add', ['toptopics_age_offset_hours', '2.0']],
			['config.add', ['toptopics_gravity', '1.8']],
			['config.add', ['toptopics_early_window_hours', 24]],
			['config.add', ['toptopics_early_like_minimum', 3]],
			['config.add', ['toptopics_early_velocity_threshold', '0.5']],
			['config.add', ['toptopics_velocity_boost', '1.2']],
			['config.add', ['toptopics_discussion_reply_minimum', 10]],
			['config.add', ['toptopics_discussion_reply_like_ratio', '4.0']],
			['config.add', ['toptopics_discussion_penalty', '0.8']],
			['config.add', ['toptopics_flag_warning_threshold', 1]],
			['config.add', ['toptopics_flag_warning_penalty', '0.7']],
			['config.add', ['toptopics_flag_hard_threshold', 2]],
			['config.add', ['toptopics_flag_hard_penalty', '0.3']],
			['config.add', ['toptopics_hide_flag_threshold', 3]],
			['config.add', ['toptopics_hide_point_threshold', -5]],
			['config.add', ['toptopics_trust_boost_cap', '0.1']],
			['config.update', ['toptopics_version', '1.1.0']],
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_TOPTOPICS_GRP',
			]],
			['module.add', [
				'acp',
				'ACP_TOPTOPICS_GRP',
				[
					'module_basename' => '\freemitbbs\toptopics\acp\acp_toptopics_module',
					'module_mode' => ['main'],
					'module_auth' => 'ext_freemitbbs/toptopics && acl_a_board',
				],
			]],
		];
	}
}
