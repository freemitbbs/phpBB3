<?php

namespace freemitbbs\cardgamesauth\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	private const TESTER_GROUP_NAME = 'CARD_GAME_TESTERS';

	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\language\language $language;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\controller\helper $helper,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\phpbb\template\template $template,
		\phpbb\user $user
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->helper = $helper;
		$this->db = $db;
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
		$testing_mode = $this->is_testing_mode();
		$is_tester = $testing_mode ? $this->is_tester() : false;
		$user_id = (int) ($this->user->data['user_id'] ?? ANONYMOUS);
		$user_type = (int) ($this->user->data['user_type'] ?? USER_IGNORE);
		$can_play = $enabled
			&& $user_id !== ANONYMOUS
			&& empty($this->user->data['is_bot'])
			&& $user_type !== USER_IGNORE
			&& $user_type !== USER_INACTIVE
			&& $this->auth->acl_get('u_cardgames_play')
			&& (!$testing_mode || $is_tester);

		$this->template->assign_vars([
			'S_CARDGAMES_NAV' => $enabled && $show_nav && (!$testing_mode || $is_tester),
			'S_CARDGAMES_CAN_PLAY' => $can_play,
			'U_CARDGAMES_LAUNCH' => $this->helper->route('freemitbbs_cardgamesauth_launch'),
		]);
	}

	protected function is_testing_mode(): bool
	{
		return (bool) ((int) ($this->config['cardgamesauth_testing_mode'] ?? 0));
	}

	protected function is_tester(): bool
	{
		$user_id = (int) ($this->user->data['user_id'] ?? ANONYMOUS);
		if ($user_id === ANONYMOUS)
		{
			return false;
		}

		$group_id = $this->tester_group_id();
		if ($group_id <= 0)
		{
			return false;
		}

		$sql = 'SELECT 1 AS is_tester
			FROM ' . USER_GROUP_TABLE . '
			WHERE group_id = ' . $group_id . '
				AND user_id = ' . $user_id . '
				AND user_pending = 0';
		$result = $this->db->sql_query_limit($sql, 1);
		$is_tester = (bool) $this->db->sql_fetchfield('is_tester');
		$this->db->sql_freeresult($result);

		return $is_tester;
	}

	protected function tester_group_id(): int
	{
		$group_id = (int) ($this->config['cardgamesauth_tester_group_id'] ?? 0);
		if ($group_id > 0)
		{
			return $group_id;
		}

		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name = '" . $this->db->sql_escape(self::TESTER_GROUP_NAME) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$group_id = (int) $this->db->sql_fetchfield('group_id');
		$this->db->sql_freeresult($result);
		if ($group_id > 0)
		{
			$this->config->set('cardgamesauth_tester_group_id', (string) $group_id);
		}

		return $group_id;
	}

	public function add_permissions($event): void
	{
		$permissions = $event['permissions'];
		$permissions['u_cardgames_play'] = ['lang' => 'ACL_U_CARDGAMES_PLAY', 'cat' => 'misc'];
		$permissions['m_cardgames_manage'] = ['lang' => 'ACL_M_CARDGAMES_MANAGE', 'cat' => 'misc'];
		$permissions['m_cardgames_replay_export'] = ['lang' => 'ACL_M_CARDGAMES_REPLAY_EXPORT', 'cat' => 'misc'];
		$event['permissions'] = $permissions;
	}
}
