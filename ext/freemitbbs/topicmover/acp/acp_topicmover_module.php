<?php

namespace freemitbbs\topicmover\acp;

class acp_topicmover_module
{
	private const FORM_KEY = 'freemitbbs/topicmover';
	private const DEFAULT_MODEL = 'deepseek-v4-flash';
	private const DEPRECATED_MODELS = ['deepseek-chat', 'deepseek-reasoner'];

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

		$language->add_lang('info_acp_topicmover', 'freemitbbs/topicmover');

		$this->tpl_name = 'acp_topicmover';
		$this->page_title = 'ACP_TOPICMOVER';

		add_form_key(self::FORM_KEY);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$threshold = max(0, min(100000, (int) $request->variable('topicmover_threshold', 5)));
			$min_latest_reply_age_hours = max(0, min(8760, (int) $request->variable('topicmover_min_latest_reply_age_hours', 12)));
			$interval_seconds = max(300, min(86400, (int) $request->variable('topicmover_interval_seconds', 3600)));
			$api_endpoint = trim((string) $request->variable('topicmover_api_endpoint', '', true));
			$model = $this->normalize_model((string) $request->variable('topicmover_model', self::DEFAULT_MODEL, true));
			$api_key = trim((string) $request->variable('topicmover_api_key', '', true));
			$clear_api_key = (int) $request->variable('topicmover_api_key_clear', 0);
			$excluded_forum_ids = $this->normalize_forum_ids((string) $request->variable('topicmover_excluded_forum_ids', '', true));
			$excluded_user_ids = $this->normalize_user_ids((string) $request->variable('topicmover_excluded_user_ids', '', true));

			$config->set('topicmover_threshold', (string) $threshold);
			$config->set('topicmover_min_latest_reply_age_hours', (string) $min_latest_reply_age_hours);
			$config->set('topicmover_interval_seconds', (string) $interval_seconds);
			$config->set('topicmover_api_endpoint', $api_endpoint !== '' ? $api_endpoint : 'https://api.deepseek.com/chat/completions');
			$config->set('topicmover_model', $model !== '' ? $model : self::DEFAULT_MODEL);
			$config->set('topicmover_excluded_forum_ids', $excluded_forum_ids);
			$config->set('topicmover_excluded_user_ids', $excluded_user_ids);

			if ($clear_api_key)
			{
				$config->set('topicmover_api_key', '');
			}
			elseif ($api_key !== '')
			{
				$config->set('topicmover_api_key', $api_key);
			}

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'U_ACTION' => $this->u_action,
			'TOPICMOVER_THRESHOLD' => (int) ($config['topicmover_threshold'] ?? 5),
			'TOPICMOVER_MIN_LATEST_REPLY_AGE_HOURS' => (int) ($config['topicmover_min_latest_reply_age_hours'] ?? 12),
			'TOPICMOVER_INTERVAL_SECONDS' => (int) ($config['topicmover_interval_seconds'] ?? 3600),
			'TOPICMOVER_API_ENDPOINT' => (string) ($config['topicmover_api_endpoint'] ?? 'https://api.deepseek.com/chat/completions'),
			'TOPICMOVER_MODEL' => $this->normalize_model((string) ($config['topicmover_model'] ?? self::DEFAULT_MODEL)),
			'TOPICMOVER_EXCLUDED_FORUM_IDS' => (string) ($config['topicmover_excluded_forum_ids'] ?? ''),
			'TOPICMOVER_EXCLUDED_USER_IDS' => (string) ($config['topicmover_excluded_user_ids'] ?? ''),
			'S_TOPICMOVER_API_KEY_CONFIGURED' => trim((string) ($config['topicmover_api_key'] ?? '')) !== '',
		]);
	}

	protected function normalize_model(string $model): string
	{
		$model = trim($model);

		return $model === '' || in_array($model, self::DEPRECATED_MODELS, true) ? self::DEFAULT_MODEL : $model;
	}

	protected function normalize_forum_ids(string $value): string
	{
		$ids = [];
		foreach (preg_split('/[,\s]+/', $value) ?: [] as $part)
		{
			$id = (int) trim($part);
			if ($id > 0)
			{
				$ids[$id] = $id;
			}
		}

		sort($ids, SORT_NUMERIC);

		return implode(',', $ids);
	}

	protected function normalize_user_ids(string $value): string
	{
		$ids = [];
		foreach (preg_split('/[,\s]+/', $value) ?: [] as $part)
		{
			$id = (int) trim($part);
			if ($id > 0)
			{
				$ids[$id] = $id;
			}
		}

		sort($ids, SORT_NUMERIC);

		return implode(',', $ids);
	}
}
