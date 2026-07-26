<?php

namespace freemitbbs\newsscraper\acp;

class acp_newsscraper_module
{
	private const FORM_KEY = 'freemitbbs/newsscraper';

	private const DEFAULT_ENDPOINT = 'https://api.deepseek.com/chat/completions';
	private const DEFAULT_MODEL = 'deepseek-v4-flash';
	private const DEPRECATED_MODELS = ['deepseek-chat', 'deepseek-reasoner'];

	private const SOURCES = [
		'guardian' => 'The Guardian',
		'bbc' => 'BBC',
		'dw' => 'DW',
		'cnbc' => 'CNBC',
		'dailymail' => 'Daily Mail',
		'ars' => 'Ars Technica',
		'zerohedge' => 'ZeroHedge',
		'foxnews' => 'Fox News',
		'wenxuecity' => 'Wenxuecity',
		'zaobao' => 'Zaobao',
		'sina_world' => 'Sina World',
		'sohu' => 'Sohu',
		'xinhua_world' => 'Xinhua World',
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
		/** @var \phpbb\request\request_interface $request */
		$request = $phpbb_container->get('request');
		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');

		$language->add_lang('info_acp_newsscraper', 'freemitbbs/newsscraper');

		$this->tpl_name = 'acp_newsscraper';
		$this->page_title = 'ACP_NEWSSCRAPER';

		add_form_key(self::FORM_KEY);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$source_input = $request->variable('newsscraper_sources', ['' => ''], true);
			$enabled_sources = $this->normalize_sources(array_keys($source_input));
			$api_endpoint = trim((string) $request->variable('newsscraper_api_endpoint', '', true));
			$model = $this->normalize_model((string) $request->variable('newsscraper_model', '', true));
			$api_key = trim((string) $request->variable('newsscraper_api_key', '', true));
			$clear_api_key = (int) $request->variable('newsscraper_api_key_clear', 0);

			$config->set('newsscraper_enabled', (string) (int) $request->variable('newsscraper_enabled', 0));
			$config->set('newsscraper_digest_forum_id', (string) max(0, (int) $request->variable('newsscraper_digest_forum_id', 0)));
			$config->set('newsscraper_interval_seconds', (string) max(300, min(86400, (int) $request->variable('newsscraper_interval_seconds', 3600))));
			$config->set('newsscraper_candidates_per_run', (string) max(1, min(200, (int) $request->variable('newsscraper_candidates_per_run', 60))));
			$config->set('newsscraper_max_selected_per_run', (string) max(1, min(50, (int) $request->variable('newsscraper_max_selected_per_run', 4))));
			$config->set('newsscraper_min_interest_score', (string) max(0, min(100, (int) $request->variable('newsscraper_min_interest_score', 65))));
			$config->set('newsscraper_per_source_cap', (string) max(1, min(20, (int) $request->variable('newsscraper_per_source_cap', 2))));
			$config->set('newsscraper_frontpage_count', (string) max(0, min(50, (int) $request->variable('newsscraper_frontpage_count', 20))));
			$config->set('newsscraper_title_max_chars', (string) max(8, min(60, (int) $request->variable('newsscraper_title_max_chars', 30))));
			$config->set('newsscraper_seen_retention_days', (string) max(1, min(365, (int) $request->variable('newsscraper_seen_retention_days', 30))));
			$config->set('newsscraper_enabled_sources', $enabled_sources);
			$config->set('newsscraper_api_endpoint', $api_endpoint);
			$config->set('newsscraper_model', $model);

			if ($clear_api_key)
			{
				$config->set('newsscraper_api_key', '');
			}
			elseif ($api_key !== '')
			{
				$config->set('newsscraper_api_key', $api_key);
			}

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$enabled_source_map = $this->source_map((string) ($config['newsscraper_enabled_sources'] ?? ''));
		foreach (self::SOURCES as $key => $label)
		{
			$template->assign_block_vars('sources', [
				'KEY' => $key,
				'LABEL' => $label,
				'S_CHECKED' => isset($enabled_source_map[$key]),
			]);
		}

		$template->assign_vars([
			'U_ACTION' => $this->u_action,
			'NEWSSCRAPER_ENABLED' => (int) ($config['newsscraper_enabled'] ?? 0),
			'NEWSSCRAPER_DIGEST_FORUM_ID' => (int) ($config['newsscraper_digest_forum_id'] ?? 0),
			'NEWSSCRAPER_INTERVAL_SECONDS' => (int) ($config['newsscraper_interval_seconds'] ?? 3600),
			'NEWSSCRAPER_CANDIDATES_PER_RUN' => (int) ($config['newsscraper_candidates_per_run'] ?? 60),
			'NEWSSCRAPER_MAX_SELECTED_PER_RUN' => (int) ($config['newsscraper_max_selected_per_run'] ?? 4),
			'NEWSSCRAPER_MIN_INTEREST_SCORE' => (int) ($config['newsscraper_min_interest_score'] ?? 65),
			'NEWSSCRAPER_PER_SOURCE_CAP' => (int) ($config['newsscraper_per_source_cap'] ?? 2),
			'NEWSSCRAPER_FRONTPAGE_COUNT' => (int) ($config['newsscraper_frontpage_count'] ?? 20),
			'NEWSSCRAPER_TITLE_MAX_CHARS' => (int) ($config['newsscraper_title_max_chars'] ?? 30),
			'NEWSSCRAPER_SEEN_RETENTION_DAYS' => (int) ($config['newsscraper_seen_retention_days'] ?? 30),
			'NEWSSCRAPER_API_ENDPOINT' => (string) ($config['newsscraper_api_endpoint'] ?? ''),
			'NEWSSCRAPER_MODEL' => $this->normalize_model((string) ($config['newsscraper_model'] ?? '')),
			'NEWSSCRAPER_DEFAULT_API_ENDPOINT' => self::DEFAULT_ENDPOINT,
			'NEWSSCRAPER_DEFAULT_MODEL' => self::DEFAULT_MODEL,
			'S_NEWSSCRAPER_API_KEY_CONFIGURED' => trim((string) ($config['newsscraper_api_key'] ?? '')) !== '',
			'S_NEWSSCRAPER_TOPICMOVER_API_KEY_CONFIGURED' => trim((string) ($config['topicmover_api_key'] ?? '')) !== '',
		]);
	}

	protected function normalize_model(string $model): string
	{
		$model = trim($model);

		return in_array($model, self::DEPRECATED_MODELS, true) ? self::DEFAULT_MODEL : $model;
	}

	protected function normalize_sources(array $source_keys): string
	{
		$allowed = array_fill_keys(array_keys(self::SOURCES), true);
		$normalized = [];
		foreach ($source_keys as $key)
		{
			$key = trim((string) $key);
			if (isset($allowed[$key]))
			{
				$normalized[$key] = $key;
			}
		}

		return implode(',', array_values($normalized));
	}

	protected function source_map(string $value): array
	{
		$map = [];
		foreach (preg_split('/[,\s]+/', $value) ?: [] as $key)
		{
			$key = trim((string) $key);
			if ($key !== '')
			{
				$map[$key] = true;
			}
		}

		return $map;
	}
}
