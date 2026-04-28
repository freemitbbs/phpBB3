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

namespace stoker\topstats\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\template\template;
use phpbb\language\language;
use phpbb\auth\auth;
use phpbb\event\data;
use stoker\topstats\service\topstats_manager;
use stoker\topstats\service\recent_manager;

/**
 * Event listener for Top Stats extension.
 * Routes phpBB events to appropriate service methods.
 */
final class listener implements EventSubscriberInterface
{
	/** Event subscription map */
	private const SUBSCRIBED_EVENTS = [
		'core.user_setup' => 'load_language_on_setup',
		'core.page_header' => 'add_header_nav',
		'core.index_modify_page_title' => 'handle_index_display',
		'stoker.portal.main_controller_render_template_before' => 'handle_portal_display',
		'core.viewonline_overwrite_location' => 'viewonline_location',
		'core.permissions' => 'add_permissions',
	];

	/** @var config */
	protected $config;

	/** @var helper */
	protected $helper;

	/** @var template */
	protected $template;

	/** @var language */
	protected $language;

	/** @var auth */
	protected $auth;

	/** @var topstats_manager */
	protected $topstats_manager;

	/** @var recent_manager */
	protected $recent_manager;

	public function __construct(config $config, helper $helper, template $template, language $language, auth $auth, topstats_manager $topstats_manager, recent_manager $recent_manager)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->template = $template;
		$this->language = $language;
		$this->auth = $auth;
		$this->topstats_manager = $topstats_manager;
		$this->recent_manager = $recent_manager;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents(): array
	{
		return self::SUBSCRIBED_EVENTS;
	}

	/**
	 * Load extension language files on user setup.
	 */
	public function load_language_on_setup(data $event): void
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'stoker/topstats',
			'lang_set' => 'common',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Add header navigation links for custom pages.
	 * Runs on every page load to populate global navigation.
	 */
	public function add_header_nav(): void
	{
		$show_stats_page = (!empty($this->config['display_top_stats_custom']) || !empty($this->config['display_top_recent_custom'])) && $this->auth->acl_get('u_topstats_view');
		$show_posters_page = !empty($this->config['display_top_stats_topposter']) && $this->auth->acl_get('u_topposters_view');

		$this->template->assign_vars([
			'U_TOP_STATS' => $this->helper->route('stoker_topstats_page'),
			'DISPLAY_TOP_STATS_CUSTOM' => $show_stats_page,
			'DISPLAY_TOP_POSTERS' => $show_posters_page,
			'U_TOP_POSTERS' => $this->helper->route('stoker_topstats_topposter'),
		]);
	}

	/**
	 * Display Top Recent and/or Top Stats on forum index.
	 * Controlled by ACP toggles: display_top_recent_index, display_top_stats_index
	 */
	public function handle_index_display(data $event): void
	{
		if (!empty($this->config['display_top_recent_index']) && $this->auth->acl_get('u_topstats_view'))
		{
			$this->recent_manager->display_recent($event);
		}

		if (!empty($this->config['display_top_stats_index']) && $this->auth->acl_get('u_topstats_view'))
		{
			$this->topstats_manager->display_topstats($event, false);
		}
	}

	/**
	 * Display Top Recent and/or Top Stats on portal page.
	 * Controlled by ACP toggles: display_top_recent_portal, display_top_stats_portal
	 */
	public function handle_portal_display(data $event): void
	{
		if (!empty($this->config['display_top_recent_portal']) && $this->auth->acl_get('u_topstats_view'))
		{
			$this->recent_manager->display_recent_portal($event);
		}

		if (!empty($this->config['display_top_stats_portal']) && $this->auth->acl_get('u_topstats_view'))
		{
			$this->topstats_manager->display_topstats($event, true);
		}
	}

	/**
	 * Provide friendly location text in "Who is Online".
	 * Matches both rewritten and app.php routes for /top-stats and /top-posters.
	 */
	public function viewonline_location(data $event): void
	{
		$page = (string) ($event['row']['session_page'] ?? '');

		if (empty($page))
		{
			return;
		}

		if (strpos($page, 'top-stats') !== false)
		{
			$event['location'] = $this->language->lang('VIEWING_TOP_STATS');
			$event['location_url'] = $this->helper->route('stoker_topstats_page');
		}
		elseif (strpos($page, 'top-posters') !== false || strpos($page, 'topposter') !== false)
		{
			$event['location'] = $this->language->lang('VIEWING_TOP_POSTERS');
			$event['location_url'] = $this->helper->route('stoker_topstats_topposter');
		}
	}
	
	/**
	 * Add permissions language keys.
	 * 
	 * @param data $event
	 * @return void
	 */
	public function add_permissions(data $event): void
	{
		$permissions = (array) ($event['permissions'] ?? []);
		$permissions['u_topstats_view'] = [
			'lang' => 'ACL_U_TOPSTATS_VIEW',
			'cat'  => 'misc',
		];
		$permissions['u_topposters_view'] = [
			'lang' => 'ACL_U_TOPPOSTERS_VIEW',
			'cat'  => 'misc',
		];
		$event['permissions'] = $permissions;
	}
}
