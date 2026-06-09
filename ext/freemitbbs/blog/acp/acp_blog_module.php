<?php

namespace freemitbbs\blog\acp;

class acp_blog_module
{
	private const FORM_KEY = 'freemitbbs/blog';

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

		$language->add_lang('info_acp_blog', 'freemitbbs/blog');

		$this->tpl_name = 'acp_blog';
		$this->page_title = 'ACP_BLOG';

		add_form_key(self::FORM_KEY);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$config->set('freemitbbs_blog_index_latest_limit', (string) max(0, min(50, (int) $request->variable('freemitbbs_blog_index_latest_limit', 10))));
			$config->set('freemitbbs_blog_index_latest_days', (string) max(0, min(365, (int) $request->variable('freemitbbs_blog_index_latest_days', 0))));

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'U_ACTION' => $this->u_action,
			'BLOG_INDEX_LATEST_LIMIT' => (int) ($config['freemitbbs_blog_index_latest_limit'] ?? 10),
			'BLOG_INDEX_LATEST_DAYS' => (int) ($config['freemitbbs_blog_index_latest_days'] ?? 0),
		]);
	}
}
