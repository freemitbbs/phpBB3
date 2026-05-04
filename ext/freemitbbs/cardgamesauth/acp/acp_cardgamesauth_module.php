<?php

namespace freemitbbs\cardgamesauth\acp;

class acp_cardgamesauth_module
{
	private const FORM_KEY = 'freemitbbs/cardgamesauth';

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

		$language->add_lang('info_acp_cardgamesauth', 'freemitbbs/cardgamesauth');

		$this->tpl_name = 'acp_cardgamesauth';
		$this->page_title = 'ACP_CARDGAMESAUTH_SETTINGS';

		add_form_key(self::FORM_KEY);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$config->set('cardgamesauth_enabled', (string) ((int) $request->variable('cardgamesauth_enabled', 0) ? 1 : 0));
			$config->set('cardgamesauth_nav_enabled', (string) ((int) $request->variable('cardgamesauth_nav_enabled', 0) ? 1 : 0));
			$config->set('cardgamesauth_launch_redirect', (string) ((int) $request->variable('cardgamesauth_launch_redirect', 0) ? 1 : 0));
			$config->set('cardgamesauth_client_url', trim((string) $request->variable('cardgamesauth_client_url', '', true)));
			$config->set('cardgamesauth_ws_url', trim((string) $request->variable('cardgamesauth_ws_url', '', true)));
			$config->set('cardgamesauth_token_ttl', (string) $this->bounded_int($request->variable('cardgamesauth_token_ttl', 120), 30, 600));
			$config->set('cardgamesauth_token_rate_limit', (string) $this->bounded_int($request->variable('cardgamesauth_token_rate_limit', 20), 1, 120));
			$config->set('cardgamesauth_token_rate_window', (string) $this->bounded_int($request->variable('cardgamesauth_token_rate_window', 60), 10, 3600));

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'U_ACTION' => $this->u_action,
			'CARDGAMESAUTH_ENABLED' => (int) ($config['cardgamesauth_enabled'] ?? 1),
			'CARDGAMESAUTH_NAV_ENABLED' => (int) ($config['cardgamesauth_nav_enabled'] ?? 1),
			'CARDGAMESAUTH_LAUNCH_REDIRECT' => (int) ($config['cardgamesauth_launch_redirect'] ?? 0),
			'CARDGAMESAUTH_CLIENT_URL' => (string) ($config['cardgamesauth_client_url'] ?? ''),
			'CARDGAMESAUTH_WS_URL' => (string) ($config['cardgamesauth_ws_url'] ?? ''),
			'CARDGAMESAUTH_TOKEN_TTL' => (int) ($config['cardgamesauth_token_ttl'] ?? 120),
			'CARDGAMESAUTH_TOKEN_RATE_LIMIT' => (int) ($config['cardgamesauth_token_rate_limit'] ?? 20),
			'CARDGAMESAUTH_TOKEN_RATE_WINDOW' => (int) ($config['cardgamesauth_token_rate_window'] ?? 60),
		]);
	}

	protected function bounded_int($value, int $min, int $max): int
	{
		return max($min, min($max, (int) $value));
	}
}
