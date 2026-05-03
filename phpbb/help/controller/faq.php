<?php
/**
 *
 * This file is part of the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * For full copyright and license information, please see
 * the docs/CREDITS.txt file.
 *
 */

namespace phpbb\help\controller;

/**
 * FAQ help page
 */
class faq extends controller
{
	/**
	 * @return string The title of the page
	 */
	public function display()
	{
		$this->language->add_lang('help/faq');

		$this->template->assign_block_vars('navlinks', array(
			'BREADCRUMB_NAME'	=> $this->language->lang('FAQ_EXPLAIN'),
			'U_BREADCRUMB'		=> $this->helper->route('phpbb_help_faq_controller'),
		));

		$faq_blocks = array(
			array(
				'HELP_FAQ_BLOCK_ABOUT',
				false,
				array(
					'HELP_FAQ_ABOUT_SITE_QUESTION' => 'HELP_FAQ_ABOUT_SITE_ANSWER',
					'HELP_FAQ_ABOUT_VALUES_QUESTION' => 'HELP_FAQ_ABOUT_VALUES_ANSWER',
					'HELP_FAQ_ABOUT_MANAGEMENT_QUESTION' => 'HELP_FAQ_ABOUT_MANAGEMENT_ANSWER',
					'HELP_FAQ_ABOUT_BOUNDARIES_QUESTION' => 'HELP_FAQ_ABOUT_BOUNDARIES_ANSWER',
				),
			),
			array(
				'HELP_FAQ_BLOCK_ACCOUNT_POSTING',
				false,
				array(
					'HELP_FAQ_ACCOUNT_READ_POST_QUESTION' => 'HELP_FAQ_ACCOUNT_READ_POST_ANSWER',
					'HELP_FAQ_ACCOUNT_POSTING_QUESTION' => 'HELP_FAQ_ACCOUNT_POSTING_ANSWER',
					'HELP_FAQ_ACCOUNT_FORMATTING_QUESTION' => 'HELP_FAQ_ACCOUNT_FORMATTING_ANSWER',
					'HELP_FAQ_ACCOUNT_SETTINGS_QUESTION' => 'HELP_FAQ_ACCOUNT_SETTINGS_ANSWER',
				),
			),
			array(
				'HELP_FAQ_BLOCK_DISCOVERY',
				true,
				array(
					'HELP_FAQ_DISCOVERY_TOP_RECENT_QUESTION' => 'HELP_FAQ_DISCOVERY_TOP_RECENT_ANSWER',
					'HELP_FAQ_DISCOVERY_DISLIKED_POSTS_QUESTION' => 'HELP_FAQ_DISCOVERY_DISLIKED_POSTS_ANSWER',
					'HELP_FAQ_DISCOVERY_MAIN_POST_QUESTION' => 'HELP_FAQ_DISCOVERY_MAIN_POST_ANSWER',
					'HELP_FAQ_DISCOVERY_PREFERENCES_QUESTION' => 'HELP_FAQ_DISCOVERY_PREFERENCES_ANSWER',
				),
			),
			array(
				'HELP_FAQ_BLOCK_FEEDBACK',
				false,
				array(
					'HELP_FAQ_FEEDBACK_LIKES_DISLIKES_QUESTION' => 'HELP_FAQ_FEEDBACK_LIKES_DISLIKES_ANSWER',
					'HELP_FAQ_FEEDBACK_REACTIONS_QUESTION' => 'HELP_FAQ_FEEDBACK_REACTIONS_ANSWER',
					'HELP_FAQ_FEEDBACK_CANNOT_DISLIKE_QUESTION' => 'HELP_FAQ_FEEDBACK_CANNOT_DISLIKE_ANSWER',
					'HELP_FAQ_FEEDBACK_RECORDS_QUESTION' => 'HELP_FAQ_FEEDBACK_RECORDS_ANSWER',
				),
			),
			array(
				'HELP_FAQ_BLOCK_REPUTATION',
				false,
				array(
					'HELP_FAQ_REPUTATION_WHAT_QUESTION' => 'HELP_FAQ_REPUTATION_WHAT_ANSWER',
					'HELP_FAQ_REPUTATION_BUILD_QUESTION' => 'HELP_FAQ_REPUTATION_BUILD_ANSWER',
					'HELP_FAQ_REPUTATION_GATES_QUESTION' => 'HELP_FAQ_REPUTATION_GATES_ANSWER',
				),
			),
		);

		foreach ($faq_blocks as $block)
		{
			$this->manager->add_block($block[0], $block[1], $block[2]);
		}

		return $this->language->lang('FAQ_EXPLAIN');
	}
}
