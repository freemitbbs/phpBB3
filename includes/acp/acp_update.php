<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

class acp_update
{
	var $u_action;

	function main($id, $mode)
	{
		global $config, $user, $template, $request;
		global $phpbb_root_path, $phpEx, $phpbb_container;

		$user->add_lang('install');

		$this->tpl_name = 'acp_update';
		$this->page_title = 'ACP_VERSION_CHECK';

		$update_link = $phpbb_root_path . 'install/app.' . $phpEx;

		$template_ary = [
			'S_UP_TO_DATE'				=> true,
			'S_VERSIONCHECK_DISABLED'	=> true,
			'U_ACTION'					=> $this->u_action,
			'U_VERSIONCHECK_FORCE'		=> '',

			'CURRENT_VERSION'			=> $config['version'],

			'UPDATE_INSTRUCTIONS'		=> $user->lang('UPDATE_INSTRUCTIONS', $update_link),
			'S_VERSION_UPGRADEABLE'		=> false,
			'UPGRADE_INSTRUCTIONS'		=> false,
		];

		$template->assign_vars($template_ary);

		// Incomplete update?
		if (phpbb_version_compare($config['version'], PHPBB_VERSION, '<'))
		{
			$database_update_link = $phpbb_root_path . 'install/app.php/update';

			$template->assign_vars(array(
				'S_UPDATE_INCOMPLETE'		=> true,
				'FILES_VERSION'				=> PHPBB_VERSION,
				'INCOMPLETE_INSTRUCTIONS'	=> $user->lang('UPDATE_INCOMPLETE_EXPLAIN', $database_update_link),
			));
		}
	}
}
