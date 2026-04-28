<?php
/**
 *
 * Top Stats extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 stoker - https://phpbb3bbcodes.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

declare(strict_types=1);

namespace stoker\topstats\controller;

use phpbb\cache\service as cache_service;
use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\extension\manager as ext_manager;
use phpbb\language\language;
use phpbb\log\log_interface;
use phpbb\request\request;
use phpbb\template\template;
use phpbb\user;

/**
 * ACP controller for Top Stats settings.
 * Handles three configuration screens: Recent, Stats, and Top Poster.
 */
class acp_controller
{
	/** Form key for CSRF */
	public const FORM_KEY = 'acp_topstats';

	/** Keep headroom below VARCHAR(255) */
	public const MAX_EXCLUDED_USERS_CHAR = 240;

	/** Keep headroom below VARCHAR(255) */
	public const MAX_EXCLUDED_FORUMS_CHAR = 240;

	/** Hard cap for recent manager limits */
	public const MAX_RECENT_LIMIT = 50;

	/** Hard cap for Top Stats limits (index/portal/custom) */
	public const MAX_STATS_LIMIT = 50;

	/** Hard cap for Top Poster page limits */
	public const MAX_TOPPOSTER_LIMIT = 50;

	/** Valid cache time options for recent topics (in minutes) */
	public const VALID_RECENT_CACHE_TIMES = [0, 1, 2, 3, 5, 10, 15, 30];

	/** Valid cache time options for top posters (in hours, -1 = rest of day) */
	public const VALID_TOPPOSTER_CACHE_TIMES = [0, 1, 2, 3, 4, 8, -1];

	/** @var cache_service */
	protected $cache;

	/** @var config */
	protected $config;

	/** @var driver_interface */
	protected $db;

	/** @var ext_manager */
	protected $ext_manager;

	/** @var language */
	protected $language;

	/** @var log_interface */
	protected $log;

	/** @var request */
	protected $request;

	/** @var template */
	protected $template;

	/** @var user */
	protected $user;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $u_action = '';

	/**
	 * @param cache_service    $cache
	 * @param config           $config
	 * @param driver_interface $db
	 * @param ext_manager      $ext_manager
	 * @param language         $language
	 * @param log_interface    $log
	 * @param request          $request
	 * @param template         $template
	 * @param user             $user
	 * @param string           $phpbb_root_path
	 */
	public function __construct(cache_service $cache, config $config, driver_interface $db, ext_manager $ext_manager, language $language, log_interface $log, request $request, template $template, user $user, string $phpbb_root_path)
	{
		$this->cache = $cache;
		$this->config = $config;
		$this->db = $db;
		$this->ext_manager = $ext_manager;
		$this->language = $language;
		$this->log = $log;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->root_path = $phpbb_root_path;
	}

	/**
	 * Set the ACP action URL.
	 *
	 * @param string $u_action
	 * @return void
	 */
	public function set_u_action(string $u_action): void
	{
		$this->u_action = $u_action;
	}

	/**
	 * Display ACP settings page for the given mode.
	 *
	 * @param string $mode One of: 'recent', 'stats', 'topposter'
	 * @return array{tpl_name: string, page_title: string}
	 */
	public function display_options(string $mode): array
	{
		$this->language->add_lang('acp_topstats', 'stoker/topstats');
		add_form_key(self::FORM_KEY);

		// Handle AJAX toggle
		if ($this->request->variable('action', '') === 'toggle')
		{
			if (!$this->request->is_ajax())
			{
				trigger_error('FORM_INVALID', E_USER_WARNING);
			}
			$this->ajax_toggle_setting();
			return ['tpl_name' => '', 'page_title' => ''];
		}

		$this->verify_copyright_compliance();

		$portal_installed = $this->ext_manager->is_enabled('stoker/portal') && !empty($this->config['acp_portal_enable']);

		$mode = $this->validate_mode($mode);
		$fields = $this->get_fields_for_mode($mode);

		if ($this->request->is_set_post('submit'))
		{
			$this->handle_main_form_submission($mode, $fields);
		}

		if ($this->mode_supports_exclusions($mode) && $this->request->is_set_post('submit_excluded_users'))
		{
			$this->handle_excluded_users_submission();
		}

		if ($this->mode_supports_exclusions($mode) && $this->request->is_set_post('submit_excluded_forums'))
		{
			$this->handle_excluded_forums_submission();
		}

		$this->assign_template_vars($mode, $fields, $portal_installed);

		return [
			'tpl_name' => $this->get_template_for_mode($mode),
			'page_title' => $this->language->lang($this->get_title_for_mode($mode)),
		];
	}

	/**
	 * Validate and normalize the mode parameter.
	 *
	 * @param string $mode
	 * @return string
	 */
	private function validate_mode(string $mode): string
	{
		$valid_modes = ['recent', 'stats', 'topposter'];
		return in_array($mode, $valid_modes, true) ? $mode : 'stats';
	}

	/**
	 * Get field definitions for a given mode.
	 *
	 * @param string $mode
	 * @return array<string,string>
	 */
	private function get_fields_for_mode(string $mode): array
	{
		$recent_fields = [
			'tsrat_number', 'tsrat_numberp', 'tsrat_numberc',
			'ts_jsscroll', 'ts_jsspeed', 'ts_jsinterval',
			'ts_jsscroll_direction', 'ts_jsscroll_pause', 'ts_jsscroll_navigation',
			'display_top_recent_index', 'display_top_recent_portal', 'display_top_recent_custom',
			'ts_recent_cache_time',
		];

		$stats_base = [
			'display_top_stats_index', 'display_top_stats_portal', 'display_top_stats_custom',
			'tsmvt_number', 'tsmrt_number', 'tsmau_number', 'tsmaf_number',
			'tslvb_number', 'tslru_number', 'tsttm_number', 'tstlm_number',
		];
		$stats_portal = [
			'tsmvt_numberp', 'tsmrt_numberp', 'tsmau_numberp', 'tsmaf_numberp',
			'tslvb_numberp', 'tslru_numberp', 'tsttm_numberp', 'tstlm_numberp',
		];
		$stats_custom = [
			'tsmvt_numberc', 'tsmrt_numberc', 'tsmau_numberc', 'tsmaf_numberc',
			'tslvb_numberc', 'tslru_numberc', 'tsttm_numberc', 'tstlm_numberc',
		];
		$stats_fields = array_merge($stats_base, $stats_portal, $stats_custom);

		$topposter_fields = [
			'display_top_stats_topposter',
			'tsttm_numbertp',
			'tstlm_numbertp',
			'ts_topposter_cache_time',
		];

		$fields_map = [
			'recent' => $recent_fields,
			'stats' => $stats_fields,
			'topposter' => $topposter_fields,
		];

		$field_names = $fields_map[$mode] ?? [];

		$typed_fields = [];
		foreach ($field_names as $name)
		{
			$typed_fields[$name] = 'int';
		}

		if ($mode === 'stats' || $mode === 'topposter')
		{
			$typed_fields['topstats_excluded_users'] = 'string';
			$typed_fields['topstats_excluded_forums'] = 'string';
		}

		return $typed_fields;
	}

	/**
	 * Get language key for page title.
	 *
	 * @param string $mode
	 * @return string
	 */
	private function get_title_for_mode(string $mode): string
	{
		$titles = [
			'recent' => 'ACP_TS_RECENT',
			'stats' => 'ACP_TS_STATS',
			'topposter' => 'ACP_TS_TOPPOSTER',
		];
		return $titles[$mode] ?? 'ACP_TS_STATS';
	}

	/**
	 * Get template filename for mode.
	 *
	 * @param string $mode
	 * @return string
	 */
	private function get_template_for_mode(string $mode): string
	{
		$templates = [
			'recent' => 'acp_topstats_recent',
			'stats' => 'acp_topstats_stats',
			'topposter' => 'acp_topstats_topposter',
		];
		return $templates[$mode] ?? 'acp_topstats_stats';
	}

	/**
	 * Check if mode supports excluded users/forums forms.
	 *
	 * @param string $mode
	 * @return bool
	 */
	private function mode_supports_exclusions(string $mode): bool
	{
		return in_array($mode, ['stats', 'topposter'], true);
	}

	/**
	 * Handle main settings form submission with validation.
	 *
	 * @param string $mode
	 * @param array  $fields
	 * @return void
	 */
	private function handle_main_form_submission(string $mode, array $fields): void
	{
		if (!check_form_key(self::FORM_KEY))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$this->validate_limits_for_mode($mode);

		// Exclude fields that should NOT be saved by main form (AJAX toggles only)
		$ajax_only_fields = [
			'display_top_recent_index',
			'display_top_recent_portal',
			'display_top_recent_custom',
			'display_top_stats_index',
			'display_top_stats_portal',
			'display_top_stats_custom',
			'display_top_stats_topposter',
			'ts_jsscroll',
			'ts_jsscroll_pause',
			'ts_jsscroll_navigation',
			'topstats_excluded_users',
			'topstats_excluded_forums',
		];

		$fields_to_save = array_diff_key($fields, array_flip($ajax_only_fields));

		foreach ($fields_to_save as $key => $type)
		{
			$val = ($type === 'int') ? (int) $this->request->variable($key, 0) : trim($this->request->variable($key, '', true));
			$this->config->set($key, $val);
		}

		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_TOPSTATS_SETTINGS');

		trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
	}

	/**
	 * Validate limit fields based on mode.
	 *
	 * @param string $mode
	 * @return void
	 */
	private function validate_limits_for_mode(string $mode): void
	{
		if ($mode === 'recent')
		{
			$this->validate_limits(
				['tsrat_number', 'tsrat_numberp', 'tsrat_numberc'],
				self::MAX_RECENT_LIMIT,
				'TS_RECENT_LIMIT_RANGE'
			);

			$cache_time = (int) $this->request->variable('ts_recent_cache_time', 0);
			if (!in_array($cache_time, self::VALID_RECENT_CACHE_TIMES, true))
			{
				trigger_error($this->language->lang('TS_RECENT_CACHE_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}
		}
		elseif ($mode === 'stats')
		{
			$stats_keys = [
				'tsmvt_number', 'tsmrt_number', 'tsmau_number', 'tsmaf_number',
				'tslvb_number', 'tslru_number', 'tsttm_number', 'tstlm_number',
				'tsmvt_numberp', 'tsmrt_numberp', 'tsmau_numberp', 'tsmaf_numberp',
				'tslvb_numberp', 'tslru_numberp', 'tsttm_numberp', 'tstlm_numberp',
				'tsmvt_numberc', 'tsmrt_numberc', 'tsmau_numberc', 'tsmaf_numberc',
				'tslvb_numberc', 'tslru_numberc', 'tsttm_numberc', 'tstlm_numberc',
			];
			$this->validate_limits(
				$stats_keys,
				self::MAX_STATS_LIMIT,
				'TS_STATS_LIMIT_RANGE'
			);
		}
		elseif ($mode === 'topposter')
		{
			$this->validate_limits(
				['tsttm_numbertp', 'tstlm_numbertp'],
				self::MAX_TOPPOSTER_LIMIT,
				'TS_TOPPOSTER_LIMIT_RANGE'
			);

			$cache_time = (int) $this->request->variable('ts_topposter_cache_time', -1);
			if (!in_array($cache_time, self::VALID_TOPPOSTER_CACHE_TIMES, true))
			{
				trigger_error($this->language->lang('TS_TOPPOSTER_CACHE_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}
		}
	}

	/**
	 * Validate an array of limit fields against a maximum value.
	 *
	 * @param array  $keys
	 * @param int    $max_limit
	 * @param string $lang_key
	 * @return void
	 */
	private function validate_limits(array $keys, int $max_limit, string $lang_key): void
	{
		foreach ($keys as $k)
		{
			$v = (int) $this->request->variable($k, 0);
			if ($v < 0 || $v > $max_limit)
			{
				trigger_error($this->language->lang($lang_key, $max_limit) . adm_back_link($this->u_action), E_USER_WARNING);
			}
		}
	}

	/**
	 * Handle excluded users form submission with validation.
	 *
	 * @return void
	 */
	private function handle_excluded_users_submission(): void
	{
		if (!check_form_key(self::FORM_KEY))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$raw = trim($this->request->variable('topstats_excluded_users', '', true));

		if (strlen($raw) > self::MAX_EXCLUDED_USERS_CHAR)
		{
			trigger_error($this->language->lang('EXCLUDED_USERS_TOO_LONG', self::MAX_EXCLUDED_USERS_CHAR) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		if ($raw === '')
		{
			$this->config->set('topstats_excluded_users', '');
			$this->clear_top_poster_caches();
			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_TOPSTATS_SETTINGS');
			trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$parts = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);

		foreach ($parts as $p)
		{
			if (!ctype_digit($p))
			{
				trigger_error($this->language->lang('INVALID_EXCLUDED_USERS') . adm_back_link($this->u_action), E_USER_WARNING);
			}
		}

		$ids = array_values(array_unique(array_filter(array_map('intval', $parts), static function($v) {
			return $v > 0;
		})));
		sort($ids, SORT_NUMERIC);

		if (empty($ids))
		{
			$this->config->set('topstats_excluded_users', '');
			$this->clear_top_poster_caches();
			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_TOPSTATS_SETTINGS');
			trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$sql = 'SELECT user_id
			FROM ' . USERS_TABLE . '
			WHERE ' . $this->db->sql_in_set('user_id', $ids);
		$result = $this->db->sql_query($sql);

		$existing = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$existing[] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		$missing = array_values(array_diff($ids, $existing));

		if (!empty($missing))
		{
			trigger_error($this->language->lang('EXCLUDED_USER_NOT_EXIST', (int) $missing[0]) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$canonical = implode(',', $ids);

		if (strlen($canonical) > self::MAX_EXCLUDED_USERS_CHAR)
		{
			trigger_error($this->language->lang('EXCLUDED_USERS_TOO_LONG', self::MAX_EXCLUDED_USERS_CHAR) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$this->config->set('topstats_excluded_users', $canonical);
		$this->clear_top_poster_caches();
		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_TOPSTATS_SETTINGS');
		trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
	}

	/**
	 * Handle excluded forums form submission with validation.
	 *
	 * @return void
	 */
	private function handle_excluded_forums_submission(): void
	{
		if (!check_form_key(self::FORM_KEY))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$raw = trim($this->request->variable('topstats_excluded_forums', '', true));

		if (strlen($raw) > self::MAX_EXCLUDED_FORUMS_CHAR)
		{
			trigger_error($this->language->lang('EXCLUDED_FORUMS_TOO_LONG', self::MAX_EXCLUDED_FORUMS_CHAR) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		if ($raw === '')
		{
			$this->config->set('topstats_excluded_forums', '');
			$this->clear_top_poster_caches();
			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_TOPSTATS_SETTINGS');
			trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$parts = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);

		foreach ($parts as $p)
		{
			if (!ctype_digit($p))
			{
				trigger_error($this->language->lang('INVALID_EXCLUDED_FORUMS') . adm_back_link($this->u_action), E_USER_WARNING);
			}
		}

		$ids = array_values(array_unique(array_filter(array_map('intval', $parts), static function($v) {
			return $v > 0;
		})));
		sort($ids, SORT_NUMERIC);

		if (empty($ids))
		{
			$this->config->set('topstats_excluded_forums', '');
			$this->clear_top_poster_caches();
			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_TOPSTATS_SETTINGS');
			trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$sql = 'SELECT forum_id
			FROM ' . FORUMS_TABLE . '
			WHERE ' . $this->db->sql_in_set('forum_id', $ids);
		$result = $this->db->sql_query($sql);

		$existing = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$existing[] = (int) $row['forum_id'];
		}
		$this->db->sql_freeresult($result);

		$missing = array_values(array_diff($ids, $existing));

		if (!empty($missing))
		{
			trigger_error($this->language->lang('EXCLUDED_FORUM_NOT_EXIST', (int) $missing[0]) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$canonical = implode(',', $ids);

		if (strlen($canonical) > self::MAX_EXCLUDED_FORUMS_CHAR)
		{
			trigger_error($this->language->lang('EXCLUDED_FORUMS_TOO_LONG', self::MAX_EXCLUDED_FORUMS_CHAR) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$this->config->set('topstats_excluded_forums', $canonical);
		$this->clear_top_poster_caches();
		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_TOPSTATS_SETTINGS');
		trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
	}

	/**
	 * Clear top poster caches when exclusions change.
	 * Builds exact cache keys using board timezone, all configured limits,
	 * and current forum exclusion suffix.
	 *
	 * @return void
	 */
	private function clear_top_poster_caches(): void
	{
		$tz_id = (string) ($this->config['board_timezone'] ?? 'UTC');
		try
		{
			$tz = new \DateTimeZone($tz_id);
		}
		catch (\Exception $e)
		{
			$tz = new \DateTimeZone('UTC');
		}
		$now = new \DateTimeImmutable('now', $tz);

		$months = [
			$now->format('Y-m'),
			$now->modify('first day of last month')->format('Y-m'),
		];

		// Collect all distinct limit values that may have been cached
		$number_keys = [
			'tsttm_number', 'tsttm_numberp', 'tsttm_numberc', 'tsttm_numbertp',
			'tstlm_number', 'tstlm_numberp', 'tstlm_numberc', 'tstlm_numbertp',
		];
		$numbers = [];
		foreach ($number_keys as $k)
		{
			$n = (int) ($this->config[$k] ?? 0);
			if ($n > 0)
			{
				$numbers[$n] = true;
			}
		}

		// Build forum exclusion suffixes: always clear without suffix,
		// and with current suffix (covers add/remove exclusion scenarios)
		$forum_suffixes = [''];
		$excluded_forums = (string) ($this->config['topstats_excluded_forums'] ?? '');
		if ($excluded_forums !== '')
		{
			$ids = array_map('intval', explode(',', $excluded_forums));
			sort($ids, SORT_NUMERIC);
			$forum_suffixes[] = '_xf' . crc32(implode(',', $ids));
		}

		foreach ($months as $month)
		{
			$base = '_ts_tp_' . $month . '_';
			foreach (array_keys($numbers) as $n)
			{
				foreach ($forum_suffixes as $suffix)
				{
					$this->cache->destroy($base . $n . $suffix . '_v10');
				}
			}
		}
	}

	/**
	 * Assign all template variables for the current mode.
	 *
	 * @param string $mode
	 * @param array  $fields
	 * @param bool   $portal_installed
	 * @return void
	 */
	private function assign_template_vars(string $mode, array $fields, bool $portal_installed): void
	{
		$tpl_vars = [
			'PORTAL_INSTALLED_ENABLED' => $portal_installed,
			'U_ACTION' => $this->u_action,
		];

		foreach ($fields as $key => $type)
		{
			$value = $this->config[$key] ?? (($type === 'int') ? 0 : '');
			$tpl_vars[strtoupper($key)] = $value;
		}

		if ($mode === 'recent')
		{
			$tpl_vars['DISPLAY_TOP_RECENT_INDEX'] = !empty($this->config['display_top_recent_index']);
			$tpl_vars['DISPLAY_TOP_RECENT_PORTAL'] = !empty($this->config['display_top_recent_portal']);
			$tpl_vars['DISPLAY_TOP_RECENT_CUSTOM'] = !empty($this->config['display_top_recent_custom']);
			$tpl_vars['TS_JSSCROLL'] = !empty($this->config['ts_jsscroll']);
			$tpl_vars['TS_JSSCROLL_DIRECTION'] = !empty($this->config['ts_jsscroll_direction']);
			$tpl_vars['TS_JSSCROLL_PAUSE'] = !empty($this->config['ts_jsscroll_pause']);
			$tpl_vars['TS_JSSCROLL_NAVIGATION'] = !empty($this->config['ts_jsscroll_navigation']);

			$tpl_vars['U_TOGGLE_RECENT_INDEX'] = $this->u_action . '&amp;action=toggle&amp;setting=display_top_recent_index&amp;hash=' . generate_link_hash('toggledisplay_top_recent_index');
			$tpl_vars['U_TOGGLE_RECENT_PORTAL'] = $this->u_action . '&amp;action=toggle&amp;setting=display_top_recent_portal&amp;hash=' . generate_link_hash('toggledisplay_top_recent_portal');
			$tpl_vars['U_TOGGLE_RECENT_CUSTOM'] = $this->u_action . '&amp;action=toggle&amp;setting=display_top_recent_custom&amp;hash=' . generate_link_hash('toggledisplay_top_recent_custom');
			$tpl_vars['U_TOGGLE_JSSCROLL'] = $this->u_action . '&amp;action=toggle&amp;setting=ts_jsscroll&amp;hash=' . generate_link_hash('togglets_jsscroll');
			$tpl_vars['U_TOGGLE_JSSCROLL_PAUSE'] = $this->u_action . '&amp;action=toggle&amp;setting=ts_jsscroll_pause&amp;hash=' . generate_link_hash('togglets_jsscroll_pause');
			$tpl_vars['U_TOGGLE_JSSCROLL_NAVIGATION'] = $this->u_action . '&amp;action=toggle&amp;setting=ts_jsscroll_navigation&amp;hash=' . generate_link_hash('togglets_jsscroll_navigation');
		}
		elseif ($mode === 'stats')
		{
			$tpl_vars['DISPLAY_TOP_STATS_INDEX'] = !empty($this->config['display_top_stats_index']);
			$tpl_vars['DISPLAY_TOP_STATS_PORTAL'] = !empty($this->config['display_top_stats_portal']);
			$tpl_vars['DISPLAY_TOP_STATS_CUSTOM'] = !empty($this->config['display_top_stats_custom']);

			$tpl_vars['U_TOGGLE_STATS_INDEX'] = $this->u_action . '&amp;action=toggle&amp;setting=display_top_stats_index&amp;hash=' . generate_link_hash('toggledisplay_top_stats_index');
			$tpl_vars['U_TOGGLE_STATS_PORTAL'] = $this->u_action . '&amp;action=toggle&amp;setting=display_top_stats_portal&amp;hash=' . generate_link_hash('toggledisplay_top_stats_portal');
			$tpl_vars['U_TOGGLE_STATS_CUSTOM'] = $this->u_action . '&amp;action=toggle&amp;setting=display_top_stats_custom&amp;hash=' . generate_link_hash('toggledisplay_top_stats_custom');
		}
		elseif ($mode === 'topposter')
		{
			$tpl_vars['DISPLAY_TOP_STATS_TOPPOSTER'] = !empty($this->config['display_top_stats_topposter']);

			$tpl_vars['U_TOGGLE_TOPPOSTER'] = $this->u_action . '&amp;action=toggle&amp;setting=display_top_stats_topposter&amp;hash=' . generate_link_hash('toggledisplay_top_stats_topposter');
		}

		$this->template->assign_vars($tpl_vars);
	}

	/**
	 * Handle AJAX toggle for boolean settings
	 *
	 * @return void
	 */
	private function ajax_toggle_setting(): void
	{
		$setting = $this->request->variable('setting', '');

		$valid_settings = [
			'display_top_recent_index',
			'display_top_recent_portal',
			'display_top_recent_custom',
			'display_top_stats_index',
			'display_top_stats_portal',
			'display_top_stats_custom',
			'display_top_stats_topposter',
			'ts_jsscroll',
			'ts_jsscroll_pause',
			'ts_jsscroll_navigation',
		];

		if (!in_array($setting, $valid_settings, true))
		{
			trigger_error('FORM_INVALID', E_USER_WARNING);
		}

		if (!check_link_hash($this->request->variable('hash', ''), 'toggle' . $setting))
		{
			trigger_error('FORM_INVALID', E_USER_WARNING);
		}

		$new_value = (int) $this->config[$setting] ? 0 : 1;
		$this->config->set($setting, $new_value);

		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_TOPSTATS_SETTINGS');

		$json_response = new \phpbb\json_response();
		$json_response->send(['success' => true]);
	}

	/**
	 * Verify copyright compliance
	 *
	 * @return void
	 */
	private function verify_copyright_compliance(): void
	{
		$stoker_file = $this->root_path . 'ext/stoker/topstats/styles/prosilver/template/event/overall_footer_copyright_prepend.html';
		$page_controller = $this->root_path . 'ext/stoker/topstats/controller/page.php';
		$topposter_controller = $this->root_path . 'ext/stoker/topstats/controller/topposter.php';
		$violation = false;

		// Check template file exists and has minimum size (230 bytes)
		if (!is_file($stoker_file) || filesize($stoker_file) < 230)
		{
			$violation = true;
		}

		// Check template file content
		if (!$violation)
		{
			$file_content = file_get_contents($stoker_file);
			if ($file_content === false || strpos($file_content, 'TOPSTATS_CREDIT') === false || strpos($file_content, 'TOPPOSTER_CREDIT') === false)
			{
				$violation = true;
			}
		}

		// Check page controller file content
		if (!$violation && is_file($page_controller))
		{
			$page_content = file_get_contents($page_controller);
			if ($page_content === false || strpos($page_content, "'TOPSTATS_CREDIT'") === false || strpos($page_content, 'phpbb3bbcodes.com') === false)
			{
				$violation = true;
			}
		}
		else if (!$violation)
		{
			$violation = true;
		}

		// Check topposter controller file content
		if (!$violation && is_file($topposter_controller))
		{
			$topposter_content = file_get_contents($topposter_controller);
			if ($topposter_content === false || strpos($topposter_content, "'TOPPOSTER_CREDIT'") === false || strpos($topposter_content, 'phpbb3bbcodes.com') === false)
			{
				$violation = true;
			}
		}
		else if (!$violation)
		{
			$violation = true;
		}

		// Check language keys are properly defined
		if (!$violation)
		{
			$credit_text1 = $this->language->lang('TOP_STATS_COPY');
			$credit_text2 = $this->language->lang('TS_TOP_COPY');
			if ($credit_text1 === 'TOP_STATS_COPY' || $credit_text2 === 'TS_TOP_COPY')
			{
				$violation = true;
			}
		}

		// Disable all features if copyright violation detected
		if ($violation)
		{
			$this->config->set('display_top_stats_custom', 0);
			$this->config->set('display_top_recent_custom', 0);
			$this->config->set('display_top_recent_index', 0);
			$this->config->set('display_top_stats_index', 0);
			$this->config->set('display_top_recent_portal', 0);
			$this->config->set('display_top_stats_portal', 0);
			$this->config->set('display_top_stats_topposter', 0);
			trigger_error($this->language->lang('ACP_TOPSTATS_COPYRIGHT_VIOLATION'), E_USER_WARNING);
		}
	}
}
