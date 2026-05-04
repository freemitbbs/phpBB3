<?php

namespace freemitbbs\cardgamesauth\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\language\language $language;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\controller\helper $helper,
		\phpbb\language\language $language,
		\phpbb\template\template $template,
		\phpbb\user $user
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->helper = $helper;
		$this->language = $language;
		$this->template = $template;
		$this->user = $user;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language',
			'core.page_header' => 'assign_header_links',
			'core.permissions' => 'add_permissions',
		];
	}

	public function load_language(): void
	{
		$this->language->add_lang('common', 'freemitbbs/cardgamesauth');
	}

	public function assign_header_links(): void
	{
		$enabled = (bool) ((int) ($this->config['cardgamesauth_enabled'] ?? 1));
		$show_nav = (bool) ((int) ($this->config['cardgamesauth_nav_enabled'] ?? 1));
		$user_id = (int) ($this->user->data['user_id'] ?? ANONYMOUS);
		$user_type = (int) ($this->user->data['user_type'] ?? USER_IGNORE);
		$can_play = $enabled
			&& $user_id !== ANONYMOUS
			&& empty($this->user->data['is_bot'])
			&& $user_type !== USER_IGNORE
			&& $user_type !== USER_INACTIVE
			&& $this->auth->acl_get('u_cardgames_play');

		$this->template->assign_vars([
			'S_CARDGAMES_NAV' => $enabled && $show_nav,
			'S_CARDGAMES_CAN_PLAY' => $can_play,
			'U_CARDGAMES_LAUNCH' => $this->helper->route('freemitbbs_cardgamesauth_launch'),
		]);
	}

	public function add_permissions($event): void
	{
		$permissions = $event['permissions'];
		$permissions['u_cardgames_play'] = ['lang' => 'ACL_U_CARDGAMES_PLAY', 'cat' => 'misc'];
		$event['permissions'] = $permissions;
	}
}
