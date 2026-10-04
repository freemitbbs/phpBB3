<?php

namespace freemitbbs\newmemberguard\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	protected \phpbb\auth\auth $auth;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\language\language $language;
	protected \phpbb\template\template $template;
	protected \phpbb\controller\helper $helper;
	protected \freemitbbs\newmemberguard\service\analyzer $analyzer;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\phpbb\template\template $template,
		\phpbb\controller\helper $helper,
		\freemitbbs\newmemberguard\service\analyzer $analyzer
	)
	{
		$this->auth = $auth;
		$this->db = $db;
		$this->language = $language;
		$this->template = $template;
		$this->helper = $helper;
		$this->analyzer = $analyzer;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language',
			'core.mcp_queue_approve_details_template' => 'flag_new_member_post',
		];
	}

	public function load_language(): void
	{
		$this->language->add_lang('common', 'freemitbbs/newmemberguard');
	}

	/**
	 * Add a new-member panel to the moderation approval page for posts that look
	 * like they were filed into the wrong topic.
	 */
	public function flag_new_member_post($event): void
	{
		$post_id = (int) ($event['post_id'] ?? 0);
		$post_info = $event['post_info'] ?? [];
		if ($post_id <= 0 || !is_array($post_info) || empty($post_info))
		{
			return;
		}

		$visibility = (int) ($post_info['post_visibility'] ?? ITEM_APPROVED);
		if (!in_array($visibility, [ITEM_UNAPPROVED, ITEM_REAPPROVE], true))
		{
			return;
		}

		if (!$this->analyzer->is_new_member((int) ($post_info['poster_id'] ?? 0)))
		{
			return;
		}

		try
		{
			$analysis = $this->analyzer->analyze($post_id);
		}
		catch (\Throwable $e)
		{
			error_log('newmemberguard analysis failed: post_id=' . $post_id . ' error=' . $e->getMessage());
			return;
		}

		if (!$analysis)
		{
			return;
		}

		try
		{
			$move_action = $this->helper->route('freemitbbs_newmemberguard_move_post', ['post_id' => $post_id]);
		}
		catch (\Throwable $e)
		{
			error_log('newmemberguard route failed: post_id=' . $post_id . ' error=' . $e->getMessage());
			return;
		}

		$candidates = [];
		try
		{
			$candidates = $this->allowed_candidates($this->analyzer->candidate_topics($analysis['post_text'], (int) $analysis['post']['topic_id']));
		}
		catch (\Throwable $e)
		{
			error_log('newmemberguard candidate lookup failed: post_id=' . $post_id . ' error=' . $e->getMessage());
		}

		add_form_key('freemitbbs_newmemberguard_move', '_NEWMEMBERGUARD');

		foreach ($candidates as $candidate)
		{
			$this->template->assign_block_vars('newmemberguard_candidates', [
				'TOPIC_ID' => (int) $candidate['topic_id'],
				'TITLE' => $candidate['title'],
				'FORUM_NAME' => $candidate['forum_name'],
				'LABEL' => $this->language->lang('NEWMEMBERGUARD_TOPIC_LABEL', $candidate['title'], $candidate['forum_name']),
			]);
		}

		$post_data = $event['post_data'];
		$post_data['S_NEWMEMBERGUARD_PANEL'] = true;
		$post_data['S_NEWMEMBERGUARD_OFFTOPIC'] = (bool) $analysis['offtopic'];
		$post_data['S_NEWMEMBERGUARD_HAS_CANDIDATES'] = !empty($candidates);
		$post_data['NEWMEMBERGUARD_POST_ID'] = $post_id;
		$post_data['NEWMEMBERGUARD_SCORE'] = (string) round($analysis['score'] * 100);
		$post_data['NEWMEMBERGUARD_MOVE_ACTION'] = $move_action;
		$event['post_data'] = $post_data;
	}

	protected function allowed_candidates(array $candidates): array
	{
		$allowed = [];
		foreach ($candidates as $candidate)
		{
			$forum_id = (int) $candidate['forum_id'];
			if ($this->auth->acl_get('f_read', $forum_id) && $this->auth->acl_get('m_', $forum_id))
			{
				$allowed[] = $candidate;
			}
		}

		return $allowed;
	}
}
