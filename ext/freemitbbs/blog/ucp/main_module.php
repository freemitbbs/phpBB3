<?php

namespace freemitbbs\blog\ucp;

class main_module
{
	public $u_action;
	public $tpl_name;
	public $page_title;

	public function __construct()
	{
		global $user;
		$user->add_lang_ext('freemitbbs/blog', 'common');
	}

	public function main($id, $mode)
	{
		global $phpbb_container;

		$this->tpl_name = 'blog_ucp';
		$this->page_title = 'UCP_BLOG_MANAGE';

		if (!$phpbb_container->has('freemitbbs.blog.controller'))
		{
			trigger_error('BLOG_CONTROLLER_NOT_FOUND', E_USER_WARNING);
		}

		$phpbb_container->get('freemitbbs.blog.controller')->ucp($this->u_action);
	}
}
