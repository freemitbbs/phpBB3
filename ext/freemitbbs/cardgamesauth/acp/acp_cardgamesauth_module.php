<?php

namespace freemitbbs\cardgamesauth\acp;

class acp_cardgamesauth_module
{
	private const FORM_KEY = 'freemitbbs/cardgamesauth';
	private const TESTER_GROUP_NAME = 'CARD_GAME_TESTERS';
	private const TESTER_GROUP_DESC = 'Users allowed to access card games while testing mode is enabled.';
	private const SENTRY_DEFAULT_CDN_URL = 'https://browser.sentry-cdn.com/10.45.0/bundle.min.js';

	public string $tpl_name;
	public string $page_title;
	public string $u_action;

	public function main($id, $mode)
	{
		global $phpbb_container;

		/** @var \phpbb\config\config $config */
		$config = $phpbb_container->get('config');
		/** @var \phpbb\db\driver\driver_interface $db */
		$db = $phpbb_container->get('dbal.conn');
		/** @var \phpbb\template\template $template */
		$template = $phpbb_container->get('template');
		/** @var \phpbb\request\request $request */
		$request = $phpbb_container->get('request');
		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');
		/** @var \phpbb\controller\helper $helper */
		$helper = $phpbb_container->get('controller.helper');

		$language->add_lang('info_acp_cardgamesauth', 'freemitbbs/cardgamesauth');

		$this->tpl_name = 'acp_cardgamesauth';
		$this->page_title = 'ACP_CARDGAMESAUTH_SETTINGS';

		add_form_key(self::FORM_KEY);
		$tester_group_id = $this->ensure_tester_group($db, $config);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$config->set('cardgamesauth_enabled', (string) ((int) $request->variable('cardgamesauth_enabled', 0) ? 1 : 0));
			$config->set('cardgamesauth_nav_enabled', (string) ((int) $request->variable('cardgamesauth_nav_enabled', 0) ? 1 : 0));
			$config->set('cardgamesauth_testing_mode', (string) ((int) $request->variable('cardgamesauth_testing_mode', 1) ? 1 : 0));
			$config->set('cardgamesauth_launch_redirect', (string) ((int) $request->variable('cardgamesauth_launch_redirect', 0) ? 1 : 0));
			$config->set('cardgamesauth_client_url', trim((string) $request->variable('cardgamesauth_client_url', '', true)));
			$config->set('cardgamesauth_ws_url', trim((string) $request->variable('cardgamesauth_ws_url', '', true)));
			$config->set('cardgamesauth_sentry_enabled', (string) ((int) $request->variable('cardgamesauth_sentry_enabled', 0) ? 1 : 0));
			$config->set('cardgamesauth_sentry_dsn', trim((string) $request->variable('cardgamesauth_sentry_dsn', '', true)));
			$config->set('cardgamesauth_sentry_environment', $this->sentry_environment($request->variable('cardgamesauth_sentry_environment', 'production', true)));
			$config->set('cardgamesauth_sentry_release', trim((string) $request->variable('cardgamesauth_sentry_release', '', true)));
			$config->set('cardgamesauth_sentry_cdn_url', $this->sentry_cdn_url($request->variable('cardgamesauth_sentry_cdn_url', self::SENTRY_DEFAULT_CDN_URL, true)));
			$config->set('cardgamesauth_sentry_sample_rate', $this->bounded_float($request->variable('cardgamesauth_sentry_sample_rate', '1', true), 0.0, 1.0));
			$config->set('cardgamesauth_sentry_traces_sample_rate', $this->bounded_float($request->variable('cardgamesauth_sentry_traces_sample_rate', '0', true), 0.0, 1.0));
			$config->set('cardgamesauth_token_ttl', (string) $this->bounded_int($request->variable('cardgamesauth_token_ttl', 120), 30, 600));
			$config->set('cardgamesauth_token_rate_limit', (string) $this->bounded_int($request->variable('cardgamesauth_token_rate_limit', 20), 1, 120));
			$config->set('cardgamesauth_token_rate_window', (string) $this->bounded_int($request->variable('cardgamesauth_token_rate_window', 60), 10, 3600));
			$config->set('cardgamesauth_token_clock_tolerance', (string) $this->bounded_int($request->variable('cardgamesauth_token_clock_tolerance', 10), 0, 300));
			$token_secret = trim((string) $request->variable('cardgamesauth_token_secret', '', true));
			if ($token_secret !== '')
			{
				$config->set('cardgamesauth_token_secret', $token_secret);
			}
			$config->set('cardgamesauth_proxy_enabled', (string) ((int) $request->variable('cardgamesauth_proxy_enabled', 0) ? 1 : 0));
			$proxy_secret = trim((string) $request->variable('cardgamesauth_proxy_secret', '', true));
			if ($proxy_secret !== '')
			{
				$config->set('cardgamesauth_proxy_secret', $proxy_secret);
			}
			$config->set('cardgamesauth_proxy_clock_skew', (string) $this->bounded_int($request->variable('cardgamesauth_proxy_clock_skew', 300), 30, 3600));
			$config->set('cardgamesauth_proxy_nonce_ttl', (string) $this->bounded_int($request->variable('cardgamesauth_proxy_nonce_ttl', 300), 30, 3600));
			$config->set('cardgamesauth_proxy_max_body_bytes', (string) $this->bounded_int($request->variable('cardgamesauth_proxy_max_body_bytes', 262144), 1024, 1048576));
			$config->set('cardgames_node_runtime_enabled', (string) ((int) $request->variable('cardgames_node_runtime_enabled', 0) ? 1 : 0));
			$config->set('cardgames_node_runtime_base_url', trim((string) $request->variable('cardgames_node_runtime_base_url', '', true)));
			$runtime_service_id = trim((string) $request->variable('cardgames_node_runtime_service_id', 'phpbb-cardgamesauth', true));
			$config->set('cardgames_node_runtime_service_id', $runtime_service_id !== '' ? $runtime_service_id : 'phpbb-cardgamesauth');
			$runtime_service_secret = trim((string) $request->variable('cardgames_node_runtime_service_secret', '', true));
			if ($runtime_service_secret !== '')
			{
				$config->set('cardgames_node_runtime_service_secret', $runtime_service_secret);
			}
			$config->set('cardgames_node_runtime_timeout_ms', (string) $this->bounded_int($request->variable('cardgames_node_runtime_timeout_ms', 10000), 1000, 30000));
			$tester_error = $this->add_tester_usernames(
				$db,
				$tester_group_id,
				$request->variable('cardgamesauth_add_tester_usernames', '', true),
				$language
			);
			if ($tester_error !== '')
			{
				trigger_error($tester_error . adm_back_link($this->u_action), E_USER_WARNING);
			}

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'U_ACTION' => $this->u_action,
			'U_CARDGAMES_ADMIN' => $helper->route('freemitbbs_cardgamesauth_admin'),
			'CARDGAMESAUTH_ENABLED' => (int) ($config['cardgamesauth_enabled'] ?? 1),
			'CARDGAMESAUTH_NAV_ENABLED' => (int) ($config['cardgamesauth_nav_enabled'] ?? 1),
			'CARDGAMESAUTH_TESTING_MODE' => (int) ($config['cardgamesauth_testing_mode'] ?? 1),
			'CARDGAMESAUTH_LAUNCH_REDIRECT' => (int) ($config['cardgamesauth_launch_redirect'] ?? 0),
			'CARDGAMESAUTH_CLIENT_URL' => (string) ($config['cardgamesauth_client_url'] ?? ''),
			'CARDGAMESAUTH_WS_URL' => (string) ($config['cardgamesauth_ws_url'] ?? ''),
			'CARDGAMESAUTH_SENTRY_ENABLED' => (int) ($config['cardgamesauth_sentry_enabled'] ?? 0),
			'CARDGAMESAUTH_SENTRY_DSN' => (string) ($config['cardgamesauth_sentry_dsn'] ?? ''),
			'CARDGAMESAUTH_SENTRY_ENVIRONMENT' => (string) ($config['cardgamesauth_sentry_environment'] ?? 'production'),
			'CARDGAMESAUTH_SENTRY_RELEASE' => (string) ($config['cardgamesauth_sentry_release'] ?? ''),
			'CARDGAMESAUTH_SENTRY_CDN_URL' => (string) ($config['cardgamesauth_sentry_cdn_url'] ?? self::SENTRY_DEFAULT_CDN_URL),
			'CARDGAMESAUTH_SENTRY_SAMPLE_RATE' => (string) ($config['cardgamesauth_sentry_sample_rate'] ?? '1'),
			'CARDGAMESAUTH_SENTRY_TRACES_SAMPLE_RATE' => (string) ($config['cardgamesauth_sentry_traces_sample_rate'] ?? '0'),
			'CARDGAMESAUTH_TOKEN_TTL' => (int) ($config['cardgamesauth_token_ttl'] ?? 120),
			'CARDGAMESAUTH_TOKEN_RATE_LIMIT' => (int) ($config['cardgamesauth_token_rate_limit'] ?? 20),
			'CARDGAMESAUTH_TOKEN_RATE_WINDOW' => (int) ($config['cardgamesauth_token_rate_window'] ?? 60),
			'CARDGAMESAUTH_TOKEN_CLOCK_TOLERANCE' => (int) ($config['cardgamesauth_token_clock_tolerance'] ?? 10),
			'CARDGAMESAUTH_TOKEN_SECRET' => $this->ensure_secret_config($config, 'cardgamesauth_token_secret'),
			'CARDGAMESAUTH_PROXY_ENABLED' => (int) ($config['cardgamesauth_proxy_enabled'] ?? 1),
			'CARDGAMESAUTH_PROXY_SECRET' => (string) ($config['cardgamesauth_proxy_secret'] ?? ''),
			'CARDGAMESAUTH_PROXY_CLOCK_SKEW' => (int) ($config['cardgamesauth_proxy_clock_skew'] ?? 300),
			'CARDGAMESAUTH_PROXY_NONCE_TTL' => (int) ($config['cardgamesauth_proxy_nonce_ttl'] ?? 300),
			'CARDGAMESAUTH_PROXY_MAX_BODY_BYTES' => (int) ($config['cardgamesauth_proxy_max_body_bytes'] ?? 262144),
			'CARDGAMES_NODE_RUNTIME_ENABLED' => (int) ($config['cardgames_node_runtime_enabled'] ?? 0),
			'CARDGAMES_NODE_RUNTIME_BASE_URL' => (string) ($config['cardgames_node_runtime_base_url'] ?? ''),
			'CARDGAMES_NODE_RUNTIME_SERVICE_ID' => (string) ($config['cardgames_node_runtime_service_id'] ?? 'phpbb-cardgamesauth'),
			'CARDGAMES_NODE_RUNTIME_SERVICE_SECRET' => $this->ensure_secret_config($config, 'cardgames_node_runtime_service_secret'),
			'CARDGAMES_NODE_RUNTIME_TIMEOUT_MS' => (int) ($config['cardgames_node_runtime_timeout_ms'] ?? 10000),
			'CARDGAMESAUTH_TESTER_GROUP_NAME' => self::TESTER_GROUP_NAME,
			'CARDGAMESAUTH_TESTER_MEMBERS' => $this->tester_members_text($db, $tester_group_id),
		]);
	}

	protected function bounded_int($value, int $min, int $max): int
	{
		return max($min, min($max, (int) $value));
	}

	protected function bounded_float($value, float $min, float $max): string
	{
		$number = (float) $value;
		if (!is_finite($number))
		{
			$number = $min;
		}

		$number = max($min, min($max, $number));
		return rtrim(rtrim(sprintf('%.4F', $number), '0'), '.');
	}

	protected function sentry_environment(string $value): string
	{
		$environment = trim($value);
		$environment = preg_replace('#[\s/]+#', '-', $environment) ?: 'production';
		return substr($environment, 0, 64);
	}

	protected function sentry_cdn_url(string $value): string
	{
		$url = trim($value);
		return preg_match('#^https://#i', $url) ? $url : self::SENTRY_DEFAULT_CDN_URL;
	}

	protected function ensure_secret_config(\phpbb\config\config $config, string $name): string
	{
		$secret = trim((string) ($config[$name] ?? ''));
		if ($secret !== '')
		{
			return $secret;
		}

		$secret = $this->generate_secret();
		$config->set($name, $secret);

		return $secret;
	}

	protected function generate_secret(): string
	{
		try
		{
			return bin2hex(random_bytes(32));
		}
		catch (\Exception $e)
		{
			return sha1(uniqid((string) mt_rand(), true) . microtime(true));
		}
	}

	protected function ensure_tester_group(\phpbb\db\driver\driver_interface $db, \phpbb\config\config $config): int
	{
		$group_id = (int) ($config['cardgamesauth_tester_group_id'] ?? 0);
		if ($group_id > 0 && $this->group_exists($db, $group_id))
		{
			return $group_id;
		}

		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name = '" . $db->sql_escape(self::TESTER_GROUP_NAME) . "'";
		$result = $db->sql_query_limit($sql, 1);
		$group_id = (int) $db->sql_fetchfield('group_id');
		$db->sql_freeresult($result);

		if ($group_id <= 0)
		{
			$sql = 'INSERT INTO ' . GROUPS_TABLE . ' ' . $db->sql_build_array('INSERT', [
				'group_name' => self::TESTER_GROUP_NAME,
				'group_desc' => self::TESTER_GROUP_DESC,
				'group_desc_uid' => '',
				'group_desc_bitfield' => '',
				'group_type' => GROUP_HIDDEN,
				'group_colour' => '',
				'group_legend' => 0,
				'group_founder_manage' => 0,
				'group_skip_auth' => 0,
			]);
			$db->sql_query($sql);
			$group_id = (int) $db->sql_nextid();
		}

		if ($group_id > 0)
		{
			$config->set('cardgamesauth_tester_group_id', (string) $group_id);
		}

		return $group_id;
	}

	protected function group_exists(\phpbb\db\driver\driver_interface $db, int $group_id): bool
	{
		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . '
			WHERE group_id = ' . $group_id;
		$result = $db->sql_query_limit($sql, 1);
		$exists = (bool) $db->sql_fetchfield('group_id');
		$db->sql_freeresult($result);

		return $exists;
	}

	protected function add_tester_usernames(\phpbb\db\driver\driver_interface $db, int $group_id, string $usernames, \phpbb\language\language $language): string
	{
		if ($group_id <= 0 || trim($usernames) === '')
		{
			return '';
		}

		$name_ary = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $usernames) ?: []), static function ($name) {
			return $name !== '';
		}));
		if (empty($name_ary))
		{
			return '';
		}

		$requested_by_clean = [];
		foreach ($name_ary as $username)
		{
			$clean = utf8_clean_string($username);
			if ($clean !== '' && !isset($requested_by_clean[$clean]))
			{
				$requested_by_clean[$clean] = $username;
			}
		}
		if (empty($requested_by_clean))
		{
			return '';
		}

		$sql = 'SELECT user_id, username_clean
			FROM ' . USERS_TABLE . '
			WHERE ' . $db->sql_in_set('username_clean', array_keys($requested_by_clean)) . '
				AND ' . $db->sql_in_set('user_type', [USER_NORMAL, USER_FOUNDER]);
		$result = $db->sql_query($sql);
		$user_ids = [];
		$found_by_clean = [];
		while ($row = $db->sql_fetchrow($result))
		{
			$found_by_clean[(string) $row['username_clean']] = true;
			$user_ids[] = (int) $row['user_id'];
		}
		$db->sql_freeresult($result);

		$missing = array_diff_key($requested_by_clean, $found_by_clean);
		if (!empty($missing))
		{
			return $language->lang('CARDGAMESAUTH_TESTER_USERS_INVALID', implode(', ', array_values($missing)));
		}

		$this->ensure_user_functions_loaded();
		$error = group_user_add($group_id, $user_ids, false, self::TESTER_GROUP_NAME);
		if ($error !== false && $error !== 'GROUP_USERS_EXIST')
		{
			return $language->lang('CARDGAMESAUTH_TESTER_ADD_FAILED', $language->lang((string) $error));
		}

		return '';
	}

	protected function tester_members_text(\phpbb\db\driver\driver_interface $db, int $group_id): string
	{
		if ($group_id <= 0)
		{
			return '';
		}

		$sql = 'SELECT u.user_id, u.username, u.user_colour
			FROM ' . USER_GROUP_TABLE . ' ug
			INNER JOIN ' . USERS_TABLE . ' u
				ON u.user_id = ug.user_id
			WHERE ug.group_id = ' . $group_id . '
				AND ug.user_pending = 0
			ORDER BY u.username_clean ASC';
		$result = $db->sql_query($sql);
		$usernames = [];
		while ($row = $db->sql_fetchrow($result))
		{
			$usernames[] = get_username_string('no_profile', (int) $row['user_id'], (string) $row['username'], (string) $row['user_colour']);
		}
		$db->sql_freeresult($result);

		return implode(', ', $usernames);
	}

	protected function ensure_user_functions_loaded(): void
	{
		global $phpbb_root_path, $phpEx;

		if (!function_exists('group_user_add'))
		{
			include_once($phpbb_root_path . 'includes/functions_user.' . $phpEx);
		}
	}
}
