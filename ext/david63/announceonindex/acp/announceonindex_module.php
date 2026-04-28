<?php
/**
*
* @package Announcements on index
* @copyright (c) 2015 david63
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace david63\announceonindex\acp;

class announceonindex_module
{
	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\config\config */
	protected $config;

	public $u_action;

	function main($id, $mode)
	{
		global $user, $template, $request, $config, $phpbb_log;

		$this->user			= $user;
		$this->template		= $template;
		$this->request		= $request;
		$this->config		= $config;
		$this->phpbb_log	= $phpbb_log;

		$this->tpl_name		= 'announce_on_index';
		$this->page_title	= $this->user->lang('ANNOUNCE_ON_INDEX');
		$form_key			= 'announce_on_index';
		add_form_key($form_key);

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($this->user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$this->config->set('announce_announcement_on_index', $this->request->variable('announce_announcement_on_index', 0));
			$this->config->set('announce_event', $this->request->variable('announce_event', 0));
			$this->config->set('announce_global_on_index', $this->request->variable('announce_global_on_index', 0));
			$this->config->set('announce_guest', $this->request->variable('announce_guest', 0));
			$this->config->set('announce_on_index_enable', $this->request->variable('announce_on_index_enable', 0));

			$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'GLOBAL_ON_INDEX_LOG');
			trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
		}

		$this->template->assign_vars(array(
			'ALLOW_EVENTS'				=> isset($this->config['announce_event']) ? $this->config['announce_event'] : '',
			'ALLOW_GUESTS'				=> isset($this->config['announce_guest']) ? $this->config['announce_guest'] : '',
			'ANNOUNCE_ON_INDEX_ENABLED'	=> isset($this->config['announce_on_index_enable']) ? $this->config['announce_on_index_enable'] : '',
			'SHOW_ANNOUNCEMENTS'		=> isset($this->config['announce_announcement_on_index']) ? $this->config['announce_announcement_on_index'] : '',
			'SHOW_GLOBALS'				=> isset($this->config['announce_global_on_index']) ? $this->config['announce_global_on_index'] : '',

			'U_ACTION'					=> $this->u_action,
		));
	}
}
