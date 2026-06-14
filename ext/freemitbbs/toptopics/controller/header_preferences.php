<?php

namespace freemitbbs\toptopics\controller;

use Symfony\Component\HttpFoundation\RedirectResponse;

class header_preferences
{
	private const USER_OPTION_HIDE_ENHANCED_TOPIC_LIST_VIEW = 22;
	private const USER_OPTION_SHOW_ENHANCED_TOPIC_LIST_VIEW = 23;
	private const HASH_NAME = 'freemitbbs_toptopics_header_preferences';
	private const THEME_GOLD = 'prosilver_fm';
	private const THEME_COOL = 'prosilver_fm_cool';
	private const TOPIC_LIST_CLASSIC = 'classic';
	private const TOPIC_LIST_ENHANCED = 'enhanced';

	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\user $user;
	protected string $php_ext;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\request\request_interface $request,
		\phpbb\user $user,
		string $php_ext
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->user = $user;
		$this->php_ext = $php_ext;
	}

	public function save(): RedirectResponse
	{
		$return_url = $this->safe_return_url($this->request->variable('return', '', true));
		$theme = $this->normalise_theme($this->request->variable('theme', ''));
		$topic_list = $this->normalise_topic_list($this->request->variable('topic_list', ''));
		$style_id = $theme !== '' ? $this->get_style_id($theme) : 0;
		$user_id = (int) ($this->user->data['user_id'] ?? ANONYMOUS);

		if ($user_id !== ANONYMOUS)
		{
			if (!check_link_hash($this->request->variable('hash', ''), self::HASH_NAME))
			{
				return new RedirectResponse($return_url);
			}

			$this->save_registered_user_preferences($user_id, $style_id, $topic_list);
		}

		$params = [];
		if ($style_id > 0 && $this->can_switch_style())
		{
			$params['style'] = (string) $style_id;
		}
		if ($topic_list !== '')
		{
			$params['toptopics_view'] = $topic_list;
		}

		return new RedirectResponse($this->add_query_params($return_url, $params));
	}

	protected function save_registered_user_preferences(int $user_id, int $style_id, string $topic_list): void
	{
		$sql_ary = [];
		if ($style_id > 0 && $this->can_switch_style())
		{
			$sql_ary['user_style'] = $style_id;
		}

		if ($topic_list !== '')
		{
			$show_enhanced = $topic_list === self::TOPIC_LIST_ENHANCED;
			$user_options = (int) ($this->user->data['user_options'] ?? 0);
			$user_options = phpbb_optionset(self::USER_OPTION_HIDE_ENHANCED_TOPIC_LIST_VIEW, !$show_enhanced, $user_options);
			$user_options = phpbb_optionset(self::USER_OPTION_SHOW_ENHANCED_TOPIC_LIST_VIEW, $show_enhanced, $user_options);
			$sql_ary['user_options'] = $user_options;
		}

		if (!$sql_ary)
		{
			return;
		}

		$sql = 'UPDATE ' . USERS_TABLE . '
			SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
			WHERE user_id = ' . $user_id;
		$this->db->sql_query($sql);
	}

	protected function normalise_theme(string $theme): string
	{
		return in_array($theme, [self::THEME_GOLD, self::THEME_COOL], true) ? $theme : '';
	}

	protected function normalise_topic_list(string $topic_list): string
	{
		return in_array($topic_list, [self::TOPIC_LIST_CLASSIC, self::TOPIC_LIST_ENHANCED], true) ? $topic_list : '';
	}

	protected function get_style_id(string $theme): int
	{
		$sql = 'SELECT style_id
			FROM ' . STYLES_TABLE . "
			WHERE style_active = 1
				AND style_path = '" . $this->db->sql_escape($theme) . "'";
		$result = $this->db->sql_query_limit($sql, 1, 0, 3600);
		$style_id = (int) $this->db->sql_fetchfield('style_id');
		$this->db->sql_freeresult($result);

		return $style_id;
	}

	protected function can_switch_style(): bool
	{
		return !$this->config['override_user_style'] || $this->auth->acl_get('a_styles');
	}

	protected function safe_return_url(string $return_url): string
	{
		$return_url = trim(html_entity_decode($return_url, ENT_QUOTES, 'UTF-8'));
		$return_url = str_replace(["\r", "\n"], '', $return_url);

		if ($return_url === ''
			|| preg_match('#^[a-z][a-z0-9+.-]*:#i', $return_url)
			|| str_starts_with($return_url, '//'))
		{
			return $this->default_return_url();
		}

		$parts = parse_url($return_url);
		if ($parts === false || isset($parts['scheme']) || isset($parts['host']))
		{
			return $this->default_return_url();
		}

		$path = (string) ($parts['path'] ?? '');
		if ($path === '')
		{
			$path = $this->default_return_url();
		}
		else if ($path[0] !== '/')
		{
			$path = $this->board_root_path() . ltrim($path, '/');
		}

		$query = (string) ($parts['query'] ?? '');
		$fragment = isset($parts['fragment']) ? '#' . rawurlencode((string) $parts['fragment']) : '';

		return $path . ($query !== '' ? '?' . $query : '') . $fragment;
	}

	protected function default_return_url(): string
	{
		return $this->board_root_path() . 'index.' . $this->php_ext;
	}

	protected function board_root_path(): string
	{
		$script_name = str_replace('\\', '/', (string) $this->request->server('SCRIPT_NAME', ''));
		$directory = rtrim(dirname($script_name), '/');

		return ($directory === '' || $directory === '.') ? '/' : $directory . '/';
	}

	protected function add_query_params(string $url, array $params): string
	{
		if (!$params)
		{
			return $url;
		}

		$parts = parse_url($url);
		if ($parts === false)
		{
			return $url;
		}

		$query_params = [];
		if (!empty($parts['query']))
		{
			parse_str((string) $parts['query'], $query_params);
		}
		foreach ($params as $key => $value)
		{
			$query_params[$key] = $value;
		}

		$path = (string) ($parts['path'] ?? $this->default_return_url());
		$query = http_build_query($query_params, '', '&', PHP_QUERY_RFC3986);
		$fragment = isset($parts['fragment']) ? '#' . rawurlencode((string) $parts['fragment']) : '';

		return $path . ($query !== '' ? '?' . $query : '') . $fragment;
	}
}
