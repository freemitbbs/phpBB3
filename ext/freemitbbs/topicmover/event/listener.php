<?php

namespace freemitbbs\topicmover\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	protected \phpbb\language\language $language;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;

	public function __construct(
		\phpbb\language\language $language,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		\phpbb\user $user
	)
	{
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language',
			'core.ucp_prefs_personal_data' => 'ucp_prefs_personal_data',
			'core.ucp_prefs_personal_update_data' => 'ucp_prefs_personal_update_data',
		];
	}

	public function load_language(): void
	{
		$this->language->add_lang('common', 'freemitbbs/topicmover');
	}

	public function ucp_prefs_personal_data($event): void
	{
		$data = $event['data'];
		$data['topicmover_no_move'] = $this->request->variable(
			'topicmover_no_move',
			(bool) ((int) ($this->user->data['topicmover_no_move'] ?? 0))
		);
		$event['data'] = $data;

		$this->template->assign_var('S_TOPICMOVER_NO_MOVE', (bool) $data['topicmover_no_move']);
	}

	public function ucp_prefs_personal_update_data($event): void
	{
		$data = $event['data'];
		$sql_ary = $event['sql_ary'];
		$sql_ary['topicmover_no_move'] = !empty($data['topicmover_no_move']) ? 1 : 0;
		$event['sql_ary'] = $sql_ary;
	}
}
