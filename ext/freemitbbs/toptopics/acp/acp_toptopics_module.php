<?php

namespace freemitbbs\toptopics\acp;

class acp_toptopics_module
{
	private const FORM_KEY = 'freemitbbs/toptopics';

	private const SUMMARY_SETTINGS = [
		['key' => 'toptopics_index_limit', 'type' => 'int', 'default' => 10, 'min' => 0, 'max' => 100],
		['key' => 'toptopics_forum_limit', 'type' => 'int', 'default' => 5, 'min' => 0, 'max' => 100],
		['key' => 'toptopics_flat_topic_list_per_page', 'type' => 'int', 'default' => 50, 'min' => 1, 'max' => 999],
		['key' => 'toptopics_per_forum_limit', 'type' => 'int', 'default' => 3, 'min' => 0, 'max' => 100],
		['key' => 'toptopics_summary_cache_seconds', 'type' => 'int', 'default' => 600, 'min' => 0, 'max' => 86400],
		['key' => 'toptopics_index_excluded_forum_ids', 'type' => 'forum_ids', 'default' => '', 'size' => 40],
		['key' => 'toptopics_index_category_excluded_forum_ids', 'type' => 'forum_ids', 'default' => '', 'size' => 40],
	];

	private const DOWNVOTE_SETTINGS = [
		['key' => 'toptopics_downvote_min_posts', 'type' => 'int', 'default' => 5, 'min' => 0, 'max' => 1000000],
		['key' => 'toptopics_downvote_per_minute', 'type' => 'int', 'default' => 2, 'min' => 0, 'max' => 1000000],
		['key' => 'toptopics_downvote_per_day', 'type' => 'int', 'default' => 20, 'min' => 0, 'max' => 1000000],
		['key' => 'toptopics_post_collapse_dislike_threshold', 'type' => 'int', 'default' => 5, 'min' => 0, 'max' => 1000000],
	];

	private const REPUTATION_SETTINGS = [
		['key' => 'toptopics_min_reputation_dislike', 'type' => 'int', 'default' => 10, 'min' => 0, 'max' => 1000000],
		['key' => 'toptopics_min_reputation_report', 'type' => 'int', 'default' => 50, 'min' => 0, 'max' => 1000000],
		['key' => 'toptopics_reputation_dislike_weight', 'type' => 'float', 'default' => 0.35, 'min' => 0.0, 'max' => 1.0],
	];

	private const REPUTATION_MATERIALIZED_SETTINGS = [
		'toptopics_content_weight',
		'toptopics_reaction_weight',
		'toptopics_reputation_dislike_weight',
	];

	private const RANKING_SETTINGS = [
		['key' => 'toptopics_lookback_days', 'type' => 'int', 'default' => 365, 'min' => 1, 'max' => 3650],
		['key' => 'toptopics_candidate_pool_limit', 'type' => 'int', 'default' => 2000, 'min' => 50, 'max' => 20000],
		['key' => 'toptopics_age_offset_hours', 'type' => 'float', 'default' => 2.0, 'min' => 0.1, 'max' => 168.0],
		['key' => 'toptopics_gravity', 'type' => 'float', 'default' => 1.8, 'min' => 0.1, 'max' => 5.0],
		['key' => 'toptopics_content_weight', 'type' => 'float', 'default' => 0.35, 'min' => 0.0, 'max' => 10.0],
		['key' => 'toptopics_reply_weight', 'type' => 'float', 'default' => 0.75, 'min' => 0.0, 'max' => 10.0],
		['key' => 'toptopics_view_weight', 'type' => 'float', 'default' => 0.15, 'min' => 0.0, 'max' => 10.0],
		['key' => 'toptopics_manual_boost_multiplier', 'type' => 'float', 'default' => 2.0, 'min' => 1.0, 'max' => 100.0],
		['key' => 'toptopics_manual_demote_multiplier', 'type' => 'float', 'default' => 0.3, 'min' => 0.0, 'max' => 1.0],
		['key' => 'toptopics_early_window_hours', 'type' => 'int', 'default' => 24, 'min' => 1, 'max' => 720],
		['key' => 'toptopics_early_like_minimum', 'type' => 'int', 'default' => 3, 'min' => 1, 'max' => 1000],
		['key' => 'toptopics_early_velocity_threshold', 'type' => 'float', 'default' => 0.5, 'min' => 0.01, 'max' => 100.0],
		['key' => 'toptopics_velocity_boost', 'type' => 'float', 'default' => 1.2, 'min' => 0.01, 'max' => 10.0],
		['key' => 'toptopics_discussion_reply_minimum', 'type' => 'int', 'default' => 10, 'min' => 0, 'max' => 100000],
		['key' => 'toptopics_discussion_reply_like_ratio', 'type' => 'float', 'default' => 4.0, 'min' => 0.1, 'max' => 100.0],
		['key' => 'toptopics_discussion_penalty', 'type' => 'float', 'default' => 0.8, 'min' => 0.01, 'max' => 1.0],
		['key' => 'toptopics_flag_warning_threshold', 'type' => 'int', 'default' => 1, 'min' => 0, 'max' => 1000],
		['key' => 'toptopics_flag_warning_penalty', 'type' => 'float', 'default' => 0.7, 'min' => 0.01, 'max' => 1.0],
		['key' => 'toptopics_flag_hard_threshold', 'type' => 'int', 'default' => 2, 'min' => 0, 'max' => 1000],
		['key' => 'toptopics_flag_hard_penalty', 'type' => 'float', 'default' => 0.3, 'min' => 0.01, 'max' => 1.0],
		['key' => 'toptopics_hide_flag_threshold', 'type' => 'int', 'default' => 3, 'min' => 0, 'max' => 1000],
		['key' => 'toptopics_hide_point_threshold', 'type' => 'int', 'default' => -5, 'min' => -1000000, 'max' => 1000000],
		['key' => 'toptopics_trust_boost_cap', 'type' => 'float', 'default' => 0.1, 'min' => 0.0, 'max' => 1.0],
	];

	public string $tpl_name;
	public string $page_title;
	public string $u_action;

	public function main($id, $mode)
	{
		global $phpbb_container;

		/** @var \phpbb\config\config $config */
		$config = $phpbb_container->get('config');
		/** @var \phpbb\template\template $template */
		$template = $phpbb_container->get('template');
		/** @var \phpbb\request\request $request */
		$request = $phpbb_container->get('request');
		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');

		$language->add_lang('info_acp_toptopics', 'freemitbbs/toptopics');

		$this->tpl_name = 'acp_toptopics';
		$this->page_title = 'ACP_TOPTOPICS';

		add_form_key(self::FORM_KEY);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$submitted = $request->variable('toptopics', ['' => ''], true);
			$old_values = [];
			$new_values = [];
			foreach ($this->get_all_settings() as $setting)
			{
				$key = $setting['key'];
				$old_values[$key] = isset($config[$key]) ? (string) $config[$key] : null;
				$value = $submitted[$key] ?? (isset($config[$key]) ? (string) $config[$key] : (string) $setting['default']);
				$new_values[$key] = $this->normalize_value((string) $value, $setting);
				$config->set($key, $new_values[$key]);
			}

			$min_dislike = isset($config['toptopics_min_reputation_dislike']) ? (int) $config['toptopics_min_reputation_dislike'] : 0;
			$min_report = isset($config['toptopics_min_reputation_report']) ? (int) $config['toptopics_min_reputation_report'] : 0;
			if ($min_report > 0 && $min_report < $min_dislike)
			{
				$config->set('toptopics_min_reputation_report', (string) $min_dislike);
			}

			if ($phpbb_container->has('freemitbbs.toptopics.ranker'))
			{
				/** @var \freemitbbs\toptopics\service\ranker $ranker */
				$ranker = $phpbb_container->get('freemitbbs.toptopics.ranker');
				$ranker->invalidate_all();
			}

			if ($this->reputation_materialization_settings_changed($old_values, $new_values) && $phpbb_container->has('freemitbbs.toptopics.reputation'))
			{
				/** @var \freemitbbs\toptopics\service\reputation $reputation */
				$reputation = $phpbb_container->get('freemitbbs.toptopics.reputation');
				$reputation->invalidate_all();
			}

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'U_ACTION' => $this->u_action,
			'SUMMARY_POSITION' => isset($config['postlove_summary_position']) ? (int) $config['postlove_summary_position'] : 0,
			'TOPTOPICS_SUMMARY_POSITION_LABEL' => $language->lang((isset($config['postlove_summary_position']) ? (int) $config['postlove_summary_position'] : 0) ? 'TOPTOPICS_SUMMARY_POSITION_BELOW' : 'TOPTOPICS_SUMMARY_POSITION_ABOVE'),
		]);

		$this->assign_settings_block($template, $language, $config, 'summary_settings', self::SUMMARY_SETTINGS);
		$this->assign_settings_block($template, $language, $config, 'downvote_settings', self::DOWNVOTE_SETTINGS);
		$this->assign_settings_block($template, $language, $config, 'reputation_settings', self::REPUTATION_SETTINGS);
		$this->assign_settings_block($template, $language, $config, 'ranking_settings', self::RANKING_SETTINGS);
	}

	protected function assign_settings_block(\phpbb\template\template $template, \phpbb\language\language $language, \phpbb\config\config $config, string $block_name, array $settings): void
	{
		foreach ($settings as $setting)
		{
			$template->assign_block_vars($block_name, [
				'NAME' => $setting['key'],
				'LABEL' => $language->lang(strtoupper($setting['key'])),
				'EXPLAIN' => $language->lang(strtoupper($setting['key']) . '_EXPLAIN'),
				'VALUE' => isset($config[$setting['key']]) ? $config[$setting['key']] : $setting['default'],
				'SIZE' => (int) ($setting['size'] ?? 8),
			]);
		}
	}

	protected function get_all_settings(): array
	{
		return array_merge(self::SUMMARY_SETTINGS, self::DOWNVOTE_SETTINGS, self::REPUTATION_SETTINGS, self::RANKING_SETTINGS);
	}

	protected function normalize_value(string $value, array $setting): string
	{
		if (($setting['type'] ?? '') === 'forum_ids')
		{
			return $this->normalize_forum_id_list($value);
		}

		if ($setting['type'] === 'int')
		{
			$normalized = (int) $value;

			if (isset($setting['min']))
			{
				$normalized = max((int) $setting['min'], $normalized);
			}

			if (isset($setting['max']))
			{
				$normalized = min((int) $setting['max'], $normalized);
			}

			return (string) $normalized;
		}

		$normalized = is_numeric($value) ? (float) $value : (float) $setting['default'];

		if (isset($setting['min']))
		{
			$normalized = max((float) $setting['min'], $normalized);
		}

		if (isset($setting['max']))
		{
			$normalized = min((float) $setting['max'], $normalized);
		}

		return $this->format_float($normalized);
	}

	protected function reputation_materialization_settings_changed(array $old_values, array $new_values): bool
	{
		foreach (self::REPUTATION_MATERIALIZED_SETTINGS as $key)
		{
			if (!array_key_exists($key, $new_values))
			{
				continue;
			}

			if (($old_values[$key] ?? null) !== $new_values[$key])
			{
				return true;
			}
		}

		return false;
	}

	protected function normalize_forum_id_list(string $value): string
	{
		$trimmed = preg_replace('/\s+/', '', trim($value));
		if ($trimmed === '')
		{
			return '';
		}

		$forum_ids = [];
		foreach (explode(',', $trimmed) as $part)
		{
			$forum_id = (int) $part;
			if ($forum_id > 0)
			{
				$forum_ids[$forum_id] = true;
			}
		}

		$normalized_ids = array_keys($forum_ids);
		sort($normalized_ids);

		return implode(',', $normalized_ids);
	}

	protected function format_float(float $value): string
	{
		$formatted = number_format($value, 4, '.', '');
		return rtrim(rtrim($formatted, '0'), '.');
	}
}
