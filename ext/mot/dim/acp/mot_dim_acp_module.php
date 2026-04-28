<?php
/**
*
* @package MoT DIM v1.2.0
* @copyright (c) 2024 - 2025 Mike-on-Tour
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace mot\dim\acp;

class mot_dim_acp_module
{
	public $u_action;
	public $tpl_name;
	public $page_title;

	/**
	 * Main ACP module
	 *
	 * @param	$id		The module identifier (\mot\dim\acp\mot_dim_acp_module)
	 *		$mode	The module mode (settings)
	 *
	 * @throws \Exception
	 */
	public function main(string $id, string $mode)
	{
		global $phpbb_container;

		/** @var \mot.pages.controller.acp $acp_config_controller */
		$acp_controller = $phpbb_container->get('mot.dim.controller.mot_dim_acp');

		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');

		// Load a template from adm/style for our ACP page
		$this->tpl_name = 'acp_mot_dim_' . $mode;

		// Set the page title for our ACP page
		$this->page_title = $language->lang('ACP_MOT_DIM') . ' - ' . $language->lang('ACP_MOT_DIM_' . mb_strtoupper($mode));

		// Make the $u_action url available in our ACP controller
		$acp_controller->set_page_url($this->u_action)->{$mode}();
	}
}
