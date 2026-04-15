<?php

namespace freemitbbs\hotforums\acp;

class acp_hotforums_module
{
	private const FORM_KEY = 'freemitbbs/hotforums';

	private const SETTINGS = [
		['key' => 'hotforums_index_limit', 'default' => 8, 'min' => 1, 'max' => 100],
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

		$language->add_lang('info_acp_hotforums', 'freemitbbs/hotforums');

		$this->tpl_name = 'acp_hotforums';
		$this->page_title = 'ACP_HOTFORUMS';

		add_form_key(self::FORM_KEY);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$submitted = $request->variable('hotforums', ['' => 0]);
			foreach (self::SETTINGS as $setting)
			{
				$value = (int) ($submitted[$setting['key']] ?? $setting['default']);
				$value = max((int) $setting['min'], min((int) $setting['max'], $value));
				$config->set($setting['key'], (string) $value);
			}

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$template->assign_var('U_ACTION', $this->u_action);

		foreach (self::SETTINGS as $setting)
		{
			$key = $setting['key'];
			$template->assign_block_vars('settings', [
				'NAME' => $key,
				'LABEL' => $language->lang(strtoupper($key)),
				'EXPLAIN' => $language->lang(strtoupper($key) . '_EXPLAIN'),
				'VALUE' => isset($config[$key]) ? $config[$key] : $setting['default'],
			]);
		}
	}
}
