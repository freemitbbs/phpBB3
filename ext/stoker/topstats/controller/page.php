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
use phpbb\template\template;
use phpbb\auth\auth;
use stoker\topstats\service\recent_manager;
use stoker\topstats\service\topstats_manager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for custom Top Stats page.
 * Handles /top-stats route, displaying Recent Active and/or Top Stats blocks.
 */
class page
{
	/** @var config */
	protected $config;
	
	/** @var helper */
	protected $helper;

	/** @var language */
	protected $language;
	
	/** @var template */
	protected $template;
	
	/** @var auth */
	protected $auth;

	/** @var topstats_manager */
	protected $topstats_manager;
	
	/** @var recent_manager */
	protected $recent_manager;

	public function __construct(config $config, helper $helper, language $language, template $template, auth $auth, topstats_manager $topstats_manager, recent_manager $recent_manager)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->language = $language;
		$this->template = $template;
		$this->auth = $auth;
		$this->topstats_manager = $topstats_manager;
		$this->recent_manager = $recent_manager;
	}

	/**
	 * Handle the Top Stats custom page at /top-stats.
	 * Displays Recent Active and/or Top Stats based on ACP settings.
	 */
	public function handle(): Response
	{
		if (!$this->auth->acl_get('u_topstats_view'))
		{
			trigger_error($this->language->lang('NOT_AUTHORISED'));
		}
		
		$recent_enabled = !empty($this->config['display_top_recent_custom']);
		$stats_enabled = !empty($this->config['display_top_stats_custom']);

		if (!$recent_enabled && !$stats_enabled)
		{
			trigger_error($this->language->lang('TOPSTATS_DISABLED'));
		}

		$this->display_blocks($recent_enabled, $stats_enabled);
		$this->assign_template_vars($recent_enabled, $stats_enabled);
		$this->assign_breadcrumb();

		return $this->helper->render('topstats_page.html', $this->language->lang('TOP_STATS_PAGE_TITLE'));
	}

	/**
	 * Display Recent Active and/or Top Stats blocks based on flags.
	 */
	private function display_blocks(bool $recent_enabled, bool $stats_enabled): void
	{
		if ($recent_enabled)
		{
			$this->recent_manager->display_recent_custom();
		}

		if ($stats_enabled)
		{
			$this->topstats_manager->display_topstats_custom();
		}
	}

	/**
	 * Assign template variables for the custom page.
	 */
	private function assign_template_vars(bool $recent_enabled, bool $stats_enabled): void
	{
		$canonical_url = generate_board_url() . $this->helper->route('stoker_topstats_page');

		$this->template->assign_vars([
			'PAGE_TITLE'				=> $this->language->lang('TOP_STATS_PAGE_TITLE'),
			'S_IN_TOPSTATS'				=> true,
			'TOPSTATS_CREDIT'			=> '<a href="https://phpbb3bbcodes.com/viewtopic.php?t=2749">' . $this->language->lang('TOP_STATS_COPY') . '</a>',
			'U_CANONICAL'				=> $canonical_url,
			'DISPLAY_TOP_RECENT_CUSTOM'	=> $recent_enabled,
			'DISPLAY_TOP_STATS_CUSTOM'	=> $stats_enabled,
		]);
	}

	/**
	 * Assign breadcrumb navigation.
	 */
	private function assign_breadcrumb(): void
	{
		$this->template->assign_block_vars('navlinks', [
			'FORUM_NAME'	=> $this->language->lang('TOP_STATS_PAGE_TITLE'),
			'U_VIEW_FORUM'	=> $this->helper->route('stoker_topstats_page'),
		]);
	}
}
