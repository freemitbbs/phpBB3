<?php

namespace freemitbbs\topicmover\controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class author_move
{
	private const FORM_KEY = 'freemitbbs/topicmover_author_move';

	protected \phpbb\controller\helper $helper;
	protected \phpbb\language\language $language;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;
	protected \freemitbbs\topicmover\service\author_move $author_move;
	protected string $phpbb_root_path;
	protected string $php_ext;

	public function __construct(
		\phpbb\controller\helper $helper,
		\phpbb\language\language $language,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\freemitbbs\topicmover\service\author_move $author_move,
		string $phpbb_root_path,
		string $php_ext
	)
	{
		$this->helper = $helper;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->author_move = $author_move;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	public function move(int $topic_id): Response
	{
		$this->language->add_lang('author_move', 'freemitbbs/topicmover');
		$route = $this->helper->route('freemitbbs_topicmover_author_move', ['topic_id' => $topic_id]);

		if (empty($this->user->data['is_registered']))
		{
			login_box($route, $this->language->lang('LOGIN_REQUIRED'));
		}

		$topic = $this->author_move->topic($topic_id);
		if (!$topic)
		{
			trigger_error('NO_TOPIC');
		}
		if (!$this->author_move->can_move($topic, (int) $this->user->data['user_id']))
		{
			trigger_error('NOT_AUTHORISED');
		}
		if ($this->request->is_set_post('cancel'))
		{
			return new RedirectResponse($this->topic_url($topic_id));
		}

		add_form_key(self::FORM_KEY);
		$error = '';
		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				$error = $this->language->lang('FORM_INVALID');
			}
			else
			{
				$destination_forum_id = max(0, (int) $this->request->variable('to_forum_id', 0));
				try
				{
					$this->author_move->move(
						$topic_id,
						$destination_forum_id,
						(int) $this->user->data['user_id'],
						(string) $this->user->ip
					);

					return new RedirectResponse(append_sid(
						$this->phpbb_root_path . 'viewtopic.' . $this->php_ext,
						't=' . $topic_id
					));
				}
				catch (\InvalidArgumentException $e)
				{
					$error = $this->language->lang('TOPICMOVER_AUTHOR_MOVE_INVALID_DESTINATION');
				}
				catch (\RuntimeException $e)
				{
					if ($e->getMessage() === 'TOPICMOVER_AUTHOR_MOVE_NOT_ALLOWED')
					{
						trigger_error('NOT_AUTHORISED');
					}
					$error = $this->language->lang('TOPICMOVER_AUTHOR_MOVE_FAILED');
				}
				catch (\Throwable $e)
				{
					$error = $this->language->lang('TOPICMOVER_AUTHOR_MOVE_FAILED');
				}
			}
		}

		$this->assign_template_vars($topic, $route, $error);

		return $this->helper->render(
			'@freemitbbs_topicmover/topicmover_author_move.html',
			$this->language->lang('TOPICMOVER_AUTHOR_MOVE_TITLE')
		);
	}

	protected function assign_template_vars(array $topic, string $route, string $error): void
	{
		$forums = $this->author_move->destination_forums($topic);
		foreach ($forums as $forum)
		{
			$this->template->assign_block_vars('topicmover_forums', [
				'FORUM_ID' => (int) $forum['forum_id'],
				'FORUM_NAME' => (string) $forum['forum_name'],
				'LEVEL' => (int) $forum['level'],
				'S_IS_CAT' => (bool) $forum['is_category'],
			]);
		}

		$this->template->assign_vars([
			'TOPICMOVER_ERROR' => $error,
			'TOPICMOVER_TOPIC_TITLE' => (string) $topic['topic_title'],
			'TOPICMOVER_SOURCE_FORUM' => (string) $topic['forum_name'],
			'S_TOPICMOVER_HAS_DESTINATIONS' => count(array_filter($forums, static function (array $forum): bool {
				return empty($forum['is_category']);
			})) > 0,
			'U_TOPICMOVER_AUTHOR_MOVE' => $route,
		]);
	}

	protected function topic_url(int $topic_id): string
	{
		return append_sid(
			$this->phpbb_root_path . 'viewtopic.' . $this->php_ext,
			't=' . $topic_id
		);
	}
}
