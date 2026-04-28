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

use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\language\language;
use phpbb\request\request;
use phpbb\template\template;
use phpbb\auth\auth;
use stoker\topstats\service\topstats_manager;
use phpbb\cache\service as cache_interface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for Top Posters page with month navigation.
 * Handles /top-posters route and month picker functionality.
 */
class topposter
{
	/** @var int Cache TTL for month options (1 hour) */
	const CACHE_TTL_MONTH_OPTIONS = 3600;

	/** @var config */
	protected $config;

	/** @var helper */
	protected $helper;

	/** @var language */
	protected $language;

	/** @var request */
	protected $request;

	/** @var template */
	protected $template;

	/** @var auth */
	protected $auth;

	/** @var topstats_manager */
	protected $topstats_manager;
	
	/** @var cache_interface */
	protected $cache;

	/** @var \DateTimeZone|null */
	private $board_tz = null;

	public function __construct(config $config, helper $helper, language $language, request $request, template $template, auth $auth, topstats_manager $topstats_manager, cache_interface $cache)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->auth = $auth;
		$this->topstats_manager = $topstats_manager;
		$this->cache = $cache;
	}

	/**
	 * Handle /top-posters route.
	 * Supports optional ?ym=YYYY-MM parameter for month selection.
	 */
	public function handle(): Response
	{
		if (!$this->auth->acl_get('u_topposters_view'))
		{
			trigger_error($this->language->lang('NOT_AUTHORISED'));
		}
		
		if (empty($this->config['display_top_stats_topposter']))
		{
			trigger_error($this->language->lang('TOPPOSTERS_DISABLED'));
		}

		$board_start_ts = (int) ($this->config['board_startdate'] ?? time());
		$now = $this->get_board_now();

		$first_ym = (new \DateTimeImmutable('@' . $board_start_ts))->setTimezone($this->board_tz)->format('Y-m');
		$current_ym = $now->format('Y-m');

		$ym_raw = $this->request->variable('ym', '', true);
		$has_ym_param = ($ym_raw !== '');

		$ym = $this->validate_ym($ym_raw, $first_ym, $current_ym);

		$this->assign_month_picker($ym, $first_ym, $current_ym);
		$this->assign_navigation($ym, $first_ym, $current_ym, $has_ym_param);
		$this->assign_top_posters($ym, $current_ym, $has_ym_param);

		$page_title = $has_ym_param ? ($this->format_month_label($ym) . ' ' . $this->language->lang('TS_TOP_POSTERS')) : $this->language->lang('TS_TOP_POSTERS');

		$canonical_path = $this->helper->route('stoker_topstats_topposter', $has_ym_param ? ['ym' => $ym] : []);
		$canonical_url = generate_board_url() . $canonical_path;

		$this->template->assign_vars([
			'PAGE_TITLE'		=> $page_title,
			'S_IN_TOPPOSTER'	=> true,
			'TOPPOSTER_CREDIT'	=> '<a href="https://phpbb3bbcodes.com/viewtopic.php?t=2749">' . $this->language->lang('TS_TOP_COPY') . '</a>',
			'U_TOP_POSTERS'		=> $this->helper->route('stoker_topstats_topposter'),
			'U_CANONICAL'		=> $canonical_url,
		]);

		$this->template->assign_block_vars('navlinks', [
			'FORUM_NAME'		=> $this->language->lang('TS_TOP_POSTERS'),
			'U_VIEW_FORUM'		=> $this->helper->route('stoker_topstats_topposter'),
		]);

		return $this->helper->render('topposter_body.html', $page_title);
	}

	/**
	 * Get current board time with timezone handling.
	 * Cached per request. Falls back to UTC if board timezone is invalid.
	 * 
	 * @return \DateTimeImmutable Current time in board timezone
	 */
	private function get_board_now(): \DateTimeImmutable
	{
		if ($this->board_tz === null)
		{
			$tz_id = (string) ($this->config['board_timezone'] ?? 'UTC');
			try
			{
				$this->board_tz = new \DateTimeZone($tz_id);
			}
			catch (\Exception $e)
			{
				$this->board_tz = new \DateTimeZone('UTC');
			}
		}
		return new \DateTimeImmutable('now', $this->board_tz);
	}

	/**
	 * Validate and clamp year-month parameter to valid range.
	 * Returns current month if invalid format or out of bounds.
	 * 
	 * @param string $ym_raw User input in 'YYYY-MM' format
	 * @param string $first_ym First valid month (board start)
	 * @param string $current_ym Current month
	 * @return string Valid year-month string
	 */
	private function validate_ym(string $ym_raw, string $first_ym, string $current_ym): string
	{
		if (!preg_match('/^\d{4}-\d{2}$/', $ym_raw))
		{
			return $current_ym;
		}

		if ($ym_raw < $first_ym)
		{
			return $first_ym;
		}

		if ($ym_raw > $current_ym)
		{
			return $current_ym;
		}

		return $ym_raw;
	}

	/**
	 * Assign month picker dropdown options to template.
	 */
	private function assign_month_picker(string $selected_ym, string $first_ym, string $current_ym): void
	{
		$options = $this->build_month_options($first_ym, $current_ym, $selected_ym);

		foreach ($options as $opt)
		{
			$this->template->assign_block_vars('month_options', [
				'VALUE'		=> $opt['value'],
				'LABEL'		=> $opt['label'],
				'SELECTED'	=> $opt['selected'],
			]);
		}

		$this->template->assign_vars([
			'SELECTED_YM'	=> $selected_ym,
			'MONTH_LABEL'	=> $this->format_month_label($selected_ym),
		]);
	}

	/**
	 * Assign prev/next navigation links to template.
	 */
	private function assign_navigation(string $ym, string $first_ym, string $current_ym, bool $has_ym_param): void
	{
		$ym_dt = \DateTimeImmutable::createFromFormat('!Y-m', $ym, new \DateTimeZone('UTC'));
		if (!$ym_dt)
		{
			$ym_dt = new \DateTimeImmutable($ym . '-01', new \DateTimeZone('UTC'));
		}

		$prev_ym = $ym_dt->modify('-1 month')->format('Y-m');
		$next_ym = $ym_dt->modify('+1 month')->format('Y-m');

		$has_prev = ($prev_ym >= $first_ym);
		$has_next = ($next_ym <= $current_ym);

		$this->template->assign_vars([
			'U_MONTH_PREV'			=> $has_prev ? $this->helper->route('stoker_topstats_topposter', ['ym' => $prev_ym]) : '',
			'U_MONTH_NEXT'			=> $has_next ? $this->helper->route('stoker_topstats_topposter', ['ym' => $next_ym]) : '',
			'DISABLE_MONTH_PREV'	=> !$has_prev,
			'DISABLE_MONTH_NEXT'	=> !$has_next,
			'PREV_MONTH_LABEL'		=> $has_prev ? $this->format_month_label($prev_ym) : '',
			'NEXT_MONTH_LABEL'		=> $has_next ? $this->format_month_label($next_ym) : '',
		]);
	}

	/**
	 * Assign top posters data to template based on selected month.
	 */
	private function assign_top_posters(string $ym, string $current_ym, bool $has_ym_param): void
	{
		$this_month_limit = (int) ($this->config['tsttm_numbertp'] ?? 0);
		$last_month_limit = (int) ($this->config['tstlm_numbertp'] ?? 0);
		$selected_limit = (int) ($this->config['tsttm_numbertp'] ?? 0);

		$this->topstats_manager->assign_top_posters_for_month_custom($ym, max(0, $selected_limit), 'top_posters_selected');

		$show_this_month = ($this_month_limit > 0) && !($has_ym_param && $ym === $current_ym);
		$show_last_month = ($last_month_limit > 0) && !$has_ym_param;

		if ($show_this_month)
		{
			$this->topstats_manager->assign_top_posters_this_month_custom($this_month_limit);
		}

		if ($show_last_month)
		{
			$this->topstats_manager->assign_top_posters_last_month_custom($last_month_limit);
		}

		$this->template->assign_vars([
			'TSTTM_NUMBERTP'			=> $this_month_limit,
			'TSTLM_NUMBERTP'			=> $last_month_limit,
			'SUPPRESS_LAST_MONTH_BLOCK'	=> $has_ym_param,
		]);
	}

	/**
	 * Build month dropdown options from board start to current month.
	 * Results cached for 1 hour since list only changes once per month.
	 * 
	 * @param string $first_ym First month to include (board start)
	 * @param string $current_ym Last month to include (current)
	 * @param string $selected_ym Currently selected month
	 * @return array<int, array{value: string, label: string, selected: bool}>
	 */
	private function build_month_options(string $first_ym, string $current_ym, string $selected_ym): array
	{
		// crc32 used for cache key generation only (not security-sensitive)
		$cache_key = '_ts_month_opts_' . crc32($first_ym . $current_ym);
		$cached = $this->cache->get($cache_key);
		
		// If cache exists and is valid (array), use it
		if ($cached !== false && is_array($cached))
		{
			// Update selected flag for current selection
			foreach ($cached as &$opt)
			{
				$opt['selected'] = ($opt['value'] === $selected_ym);
			}
			return $cached;
		}

		// Build options from scratch
		$tz = new \DateTimeZone('UTC');
		$start = \DateTimeImmutable::createFromFormat('!Y-m', $first_ym, $tz);
		$end = \DateTimeImmutable::createFromFormat('!Y-m', $current_ym, $tz);

		if (!$start || !$end)
		{
			return [];
		}

		$end = $end->modify('first day of next month');

		$options = [];
		for ($dt = $start; $dt < $end; $dt = $dt->modify('+1 month'))
		{
			$ym = $dt->format('Y-m');
			$options[] = [
				'value' => $ym,
				'label' => $this->format_month_label($ym),
				'selected' => false, // Will be set correctly below
			];
		}

		$options = array_reverse($options);

		// Cache the base options (without selected flag)
		$this->cache->put($cache_key, $options, self::CACHE_TTL_MONTH_OPTIONS);

		// Set selected flag for current selection
		foreach ($options as &$opt)
		{
			$opt['selected'] = ($opt['value'] === $selected_ym);
		}

		return $options;
	}

	/**
	 * Format month label using localized month names.
	 * Falls back to English if translation not available.
	 * 
	 * @param string $ym Year-month string (e.g., '2025-01')
	 * @return string Formatted label (e.g., 'January 2025')
	 */
	private function format_month_label(string $ym): string
	{
		$parts = explode('-', $ym);
		if (count($parts) !== 2)
		{
			return $ym;
		}

		$year = $parts[0];
		$month_num = (int) $parts[1];

		$month_en = gmdate('F', gmmktime(0, 0, 0, $month_num, 1, (int) $year));
		$lang_key = 'TS_MONTH_' . strtoupper($month_en);

		$month = $this->language->is_set($lang_key) ? $this->language->lang($lang_key) : $month_en;

		return $month . ' ' . $year;
	}
}
