<?php

namespace freemitbbs\adultaccess\acp;

class acp_adultaccess_module
{
	private const FORM_KEY = 'freemitbbs/adultaccess';

	public string $tpl_name;
	public string $page_title;
	public string $u_action;

	public function main($id, $mode)
	{
		global $phpbb_container;

		/** @var \phpbb\template\template $template */
		$template = $phpbb_container->get('template');
		/** @var \phpbb\request\request $request */
		$request = $phpbb_container->get('request');
		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');
		/** @var \freemitbbs\adultaccess\service\manager $manager */
		$manager = $phpbb_container->get('freemitbbs.adultaccess.manager');

		$language->add_lang('common', 'freemitbbs/adultaccess');
		$language->add_lang('info_acp_adultaccess', 'freemitbbs/adultaccess');

		$this->tpl_name = 'acp_adultaccess';
		$this->page_title = 'ACP_ADULTACCESS';

		add_form_key(self::FORM_KEY);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$submitted_forum_ids = $request->variable('adultaccess_forum_ids', '', true);
			$parsed_forum_ids = $manager->parse_forum_ids($submitted_forum_ids);
			$valid_forum_ids = $manager->filter_valid_post_forum_ids($parsed_forum_ids);
			sort($valid_forum_ids);

			$invalid_forum_ids = array_values(array_diff($parsed_forum_ids, $valid_forum_ids));
			$old_forum_ids = $manager->get_forum_ids();
			$sync_result = $manager->sync_forum_permissions($old_forum_ids, $valid_forum_ids);
			$active_forum_ids = $sync_result['active_forum_ids'];
			$manager->set_forum_ids($active_forum_ids);

			$messages = [$language->lang('CONFIG_UPDATED')];
			if (!empty($invalid_forum_ids))
			{
				$messages[] = $language->lang('ADULTACCESS_CONFIG_UPDATED_INVALID', implode(', ', $invalid_forum_ids));
			}

			if (!empty($sync_result['skipped_forum_ids']))
			{
				$skipped_forum_details = [];
				foreach ($sync_result['skipped_forum_ids'] as $forum_id => $reason)
				{
					$skipped_forum_details[] = '#' . (int) $forum_id . ' (' . $language->lang((string) $reason) . ')';
				}

				$messages[] = $language->lang('ADULTACCESS_CONFIG_UPDATED_SKIPPED', implode(', ', $skipped_forum_details));
			}

			trigger_error(implode('<br />', $messages) . adm_back_link($this->u_action));
		}

		$forum_ids = $manager->get_forum_ids();
		$template->assign_vars([
			'U_ACTION' => $this->u_action,
			'ADULTACCESS_FORUM_IDS' => implode(',', $forum_ids),
			'ADULTACCESS_GROUP_NAME' => $manager->get_group_name(),
			'ADULTACCESS_GROUP_ID' => $manager->get_adult_group_id(),
			'S_ADULTACCESS_HAS_FORUMS' => !empty($forum_ids),
		]);

		foreach ($manager->get_forum_status_rows($forum_ids) as $row)
		{
			$template->assign_block_vars('forum_status', [
				'FORUM_ID' => $row['forum_id'],
				'FORUM_NAME' => $row['forum_name'],
				'S_FORUM_EXISTS' => $row['exists'],
				'S_ADULT_GROUP_HAS_ACCESS' => $row['adult_group_has_access'],
				'BLOCKED_GROUP_NAMES' => $row['blocked_group_names'],
				'OTHER_GROUP_NAMES' => $row['other_group_names'],
			]);
		}
	}
}
