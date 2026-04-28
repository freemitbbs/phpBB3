<?php

namespace freemitbbs\adultaccess\ucp;

class main_module
{
	private const FORM_KEY = 'freemitbbs/adultaccess';

	public $u_action;
	public $tpl_name;
	public $page_title;

	public function __construct()
	{
		global $user;

		$user->add_lang_ext('freemitbbs/adultaccess', 'common');
		$user->add_lang_ext('freemitbbs/adultaccess', 'info_ucp_adultaccess');
	}

	public function main($id, $mode)
	{
		global $phpbb_container, $phpbb_root_path, $phpEx, $user;

		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');
		/** @var \phpbb\request\request $request */
		$request = $phpbb_container->get('request');
		/** @var \phpbb\template\template $template */
		$template = $phpbb_container->get('template');
		/** @var \freemitbbs\adultaccess\service\manager $manager */
		$manager = $phpbb_container->get('freemitbbs.adultaccess.manager');

		$this->tpl_name = 'ucp_adultaccess';
		$this->page_title = 'UCP_ADULTACCESS';

		add_form_key(self::FORM_KEY);

		$user_id = (int) $user->data['user_id'];
		$has_configured_forums = !empty($manager->get_forum_ids());
		$is_opted_in = $manager->is_user_opted_in($user_id);

		if ($request->is_set_post('confirm_opt_in'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID'));
			}

			$manager->opt_in_user($user_id);

			meta_refresh(3, $this->u_action);
			trigger_error(
				$language->lang('ADULTACCESS_OPT_IN_SAVED')
				. '<br /><br />'
				. $language->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>')
			);
		}

		if ($request->is_set_post('disable_access'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID'));
			}

			$manager->opt_out_user($user_id);

			meta_refresh(3, $this->u_action);
			trigger_error(
				$language->lang('ADULTACCESS_OPT_OUT_SAVED')
				. '<br /><br />'
				. $language->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>')
			);
		}

		$opt_in_time = $manager->get_user_opt_in_time($user_id);
		$template->assign_vars([
			'U_ACTION' => $this->u_action,
			'U_CANCEL' => append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=ucp_prefs&mode=personal'),
			'S_ADULTACCESS_CONFIGURED' => $has_configured_forums,
			'S_ADULTACCESS_OPTED_IN' => $is_opted_in,
			'S_ADULTACCESS_GROUP_READY' => $manager->get_adult_group_id() > 0,
			'ADULTACCESS_LAST_CONFIRM_TIME' => $opt_in_time > 0 ? $user->format_date($opt_in_time) : '',
		]);
	}
}
