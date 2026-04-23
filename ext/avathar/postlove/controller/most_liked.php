<?php
/**
 * Post Love extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Avathar.be
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace avathar\postlove\controller;

class most_liked
{
	private const USER_OPTION_HIDE_SUMMARY = 18;

	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\language\language $language;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;
	protected \avathar\postlove\service\most_liked_posts $most_liked_posts;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\controller\helper $helper,
		\phpbb\language\language $language,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\avathar\postlove\service\most_liked_posts $most_liked_posts
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->helper = $helper;
		$this->language = $language;
		$this->template = $template;
		$this->user = $user;
		$this->most_liked_posts = $most_liked_posts;
	}

	public function base()
	{
		$this->language->add_lang('postlove', 'avathar/postlove');

		if ($this->user->data['is_bot'] || !$this->auth->acl_get('u_postlove_summary') || $this->user_hides_summary())
		{
			trigger_error('NOT_AUTHORISED');
		}

		if ($this->user->data['is_registered'])
		{
			$this->user->get_profile_fields($this->user->data['user_id']);
			if (isset($this->user->profile_fields['pf_postlove_hide']) && $this->user->profile_fields['pf_postlove_hide'])
			{
				trigger_error('NOT_AUTHORISED');
			}
		}

		$limit = max(1, min(100, (int) ($this->config['postlove_most_liked_page_length'] ?? 10)));
		$forum_ids = $this->exclude_forum_ids_by_config(
			$this->most_liked_posts->get_readable_forum_ids(),
			(string) ($this->config['postlove_index_excluded_forum_ids'] ?? '')
		);
		$period_starts = $this->most_liked_posts->get_period_start_times();
		$periods = [
			[
				'block' => 'most_liked_week',
				'title_lang' => 'POSTLOVE_MOST_LIKED_THIS_WEEK',
				'start' => $period_starts['week'],
				'likes_lang' => 'LIKES_THIS_WEEK',
			],
			[
				'block' => 'most_liked_month',
				'title_lang' => 'POSTLOVE_MOST_LIKED_THIS_MONTH',
				'start' => $period_starts['month'],
				'likes_lang' => 'LIKES_THIS_MONTH',
			],
			[
				'block' => 'most_liked_year',
				'title_lang' => 'POSTLOVE_MOST_LIKED_THIS_YEAR',
				'start' => $period_starts['year'],
				'likes_lang' => 'LIKES_THIS_YEAR',
			],
			[
				'block' => 'most_liked_ever',
				'title_lang' => 'POSTLOVE_MOST_LIKED_TOTAL',
				'start' => $period_starts['ever'],
				'likes_lang' => 'LIKES_EVER',
			],
		];

		$shown_post_ids = [];
		foreach ($periods as $period)
		{
			$this->template->assign_block_vars('postlove_summary_sections', [
				'TITLE' => $this->language->lang($period['title_lang']),
			]);

			foreach ($this->most_liked_posts->get_top_posts($forum_ids, $limit, $period['start'], $period['likes_lang'], array_keys($shown_post_ids)) as $row)
			{
				$this->template->assign_block_vars($period['block'], $row);
				$this->template->assign_block_vars('postlove_summary_sections.posts', $row);
				if (!empty($row['POST_ID']))
				{
					$shown_post_ids[(int) $row['POST_ID']] = true;
				}
			}
		}

		$this->template->assign_vars([
			'POSTLOVE_MOST_LIKED_LIMIT' => $limit,
		]);

		return $this->helper->render(
			'@avathar_postlove/postlove_most_liked.html',
			$this->language->lang('POSTLOVE_MOST_LIKED_PAGE')
		);
	}

	protected function user_hides_summary(): bool
	{
		return phpbb_optionget(self::USER_OPTION_HIDE_SUMMARY, (int) $this->user->data['user_options']);
	}

	protected function exclude_forum_ids_by_config(array $forum_ids, string $configured_ids): array
	{
		$excluded_forum_ids = $this->parse_forum_id_map($configured_ids);
		$forum_ids = array_values(array_unique(array_filter(array_map('intval', $forum_ids), static function ($forum_id) {
			return $forum_id > 0;
		})));
		if (empty($forum_ids))
		{
			return [];
		}

		if (empty($excluded_forum_ids))
		{
			sort($forum_ids);
			return $forum_ids;
		}

		$filtered_forum_ids = [];
		foreach ($forum_ids as $forum_id)
		{
			if (!isset($excluded_forum_ids[$forum_id]))
			{
				$filtered_forum_ids[] = $forum_id;
			}
		}

		sort($filtered_forum_ids);
		return $filtered_forum_ids;
	}

	protected function parse_forum_id_map(string $configured_ids): array
	{
		$configured_ids = preg_replace('/\s+/', '', trim($configured_ids));
		if ($configured_ids === '')
		{
			return [];
		}

		$forum_ids = [];
		foreach (explode(',', $configured_ids) as $part)
		{
			$forum_id = (int) $part;
			if ($forum_id > 0)
			{
				$forum_ids[$forum_id] = true;
			}
		}

		return $forum_ids;
	}
}
