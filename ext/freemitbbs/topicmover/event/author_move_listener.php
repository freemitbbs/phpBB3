<?php

namespace freemitbbs\topicmover\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class author_move_listener implements EventSubscriberInterface
{
	protected \phpbb\controller\helper $helper;
	protected \phpbb\language\language $language;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;
	protected \freemitbbs\topicmover\service\author_move $author_move;

	public function __construct(
		\phpbb\controller\helper $helper,
		\phpbb\language\language $language,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\freemitbbs\topicmover\service\author_move $author_move
	)
	{
		$this->helper = $helper;
		$this->language = $language;
		$this->template = $template;
		$this->user = $user;
		$this->author_move = $author_move;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language',
			'core.viewtopic_assign_template_vars_before' => 'assign_author_move',
		];
	}

	public function load_language(): void
	{
		$this->language->add_lang('author_move', 'freemitbbs/topicmover');
	}

	public function assign_author_move($event): void
	{
		$topic = $event['topic_data'];
		$user_id = (int) ($this->user->data['user_id'] ?? ANONYMOUS);
		if (empty($this->user->data['is_registered']) || !$this->author_move->can_move($topic, $user_id))
		{
			return;
		}

		$this->template->assign_vars([
			'S_TOPICMOVER_AUTHOR_MOVE' => true,
			'U_TOPICMOVER_AUTHOR_MOVE' => $this->helper->route('freemitbbs_topicmover_author_move', [
				'topic_id' => (int) $event['topic_id'],
			]),
		]);
	}
}
