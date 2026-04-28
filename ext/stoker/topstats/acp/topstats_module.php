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

namespace stoker\topstats\acp;

/**
 * ACP module wrapper for Top Stats settings.
 * Delegates to the acp_controller service.
 */
class topstats_module
{
	/** @var string Action URL injected by ACP */
	public $u_action = '';

	/** @var string Template filename (set per mode) */
	public $tpl_name = '';

	/** @var string Page title (set per mode) */
	public $page_title = '';

	/**
	 * ACP entrypoint. Delegates to the acp_controller service.
	 *
	 * @param string|int $id Module ID
	 * @param string $mode One of: 'recent', 'stats', 'topposter'
	 * @return void
	 */
	public function main($id, string $mode): void
	{
		global $phpbb_container;

		$controller = $phpbb_container->get('stoker.topstats.controller.acp');
		$controller->set_u_action($this->u_action);

		$result = $controller->display_options($mode);

		$this->tpl_name = $result['tpl_name'];
		$this->page_title = $result['page_title'];
	}
}
