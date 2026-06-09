<?php

namespace freemitbbs\postarchive\ucp;

class main_module
{
	public $u_action;
	public $tpl_name;
	public $page_title;

	public function __construct()
	{
		global $user;

		$user->add_lang_ext('freemitbbs/postarchive', 'common');
		$user->add_lang_ext('freemitbbs/postarchive', 'info_ucp_postarchive');
	}

	public function main($id, $mode)
	{
		global $phpbb_container, $user;

		/** @var \phpbb\controller\helper $helper */
		$helper = $phpbb_container->get('controller.helper');
		/** @var \phpbb\template\template $template */
		$template = $phpbb_container->get('template');
		/** @var \freemitbbs\postarchive\controller\main $controller */
		$controller = $phpbb_container->get('freemitbbs.postarchive.controller');

		$this->tpl_name = 'ucp_postarchive';
		$this->page_title = 'UCP_POSTARCHIVE';

		add_form_key(\freemitbbs\postarchive\controller\main::FORM_KEY);

		$post_count = $controller->count_current_user_posts();
		$archive = $controller->latest_current_user_archive();
		$pending_job = $controller->latest_current_user_pending_job();
		$failed_job = $controller->latest_current_user_failed_job($archive !== null ? (int) $archive['created_time'] : 0);
		$has_pending_job = $pending_job !== null;
		$can_request_archive = $post_count > 0 && !$has_pending_job;

		$template->assign_vars([
			'U_POSTARCHIVE_CREATE' => $helper->route('freemitbbs_postarchive_create'),
			'POSTARCHIVE_POST_COUNT' => $post_count,
			'S_POSTARCHIVE_HAS_POSTS' => $post_count > 0,
			'S_POSTARCHIVE_CAN_REQUEST' => $can_request_archive,
			'S_POSTARCHIVE_ARCHIVE_READY' => $archive !== null,
			'S_POSTARCHIVE_JOB_PENDING' => $has_pending_job,
			'S_POSTARCHIVE_JOB_FAILED' => $failed_job !== null,
			'S_POSTARCHIVE_JOB_PROCESSING' => $has_pending_job && $pending_job['status'] === \freemitbbs\postarchive\service\manager::STATUS_PROCESSING,
			'POSTARCHIVE_ARCHIVE_CREATED' => $archive !== null ? $user->format_date($archive['created_time']) : '',
			'POSTARCHIVE_ARCHIVE_EXPIRES' => $archive !== null ? $user->format_date($archive['expires_time']) : '',
			'POSTARCHIVE_ARCHIVE_POST_COUNT' => $archive !== null ? $archive['post_count'] : 0,
			'POSTARCHIVE_ARCHIVE_SIZE' => $archive !== null ? get_formatted_filesize($archive['filesize']) : '',
			'POSTARCHIVE_JOB_STATUS' => $has_pending_job ? $user->lang($pending_job['status'] === \freemitbbs\postarchive\service\manager::STATUS_PROCESSING ? 'POSTARCHIVE_STATUS_PROCESSING' : 'POSTARCHIVE_STATUS_QUEUED') : '',
			'POSTARCHIVE_JOB_REQUESTED' => $has_pending_job ? $user->format_date($pending_job['requested_time']) : '',
			'POSTARCHIVE_JOB_STARTED' => $has_pending_job && $pending_job['started_time'] > 0 ? $user->format_date($pending_job['started_time']) : '',
			'POSTARCHIVE_FAILED_TIME' => $failed_job !== null ? $user->format_date($failed_job['completed_time']) : '',
			'POSTARCHIVE_CREATE_BUTTON_LABEL' => $has_pending_job ? $user->lang('POSTARCHIVE_QUEUED_BUTTON') : ($archive !== null ? $user->lang('POSTARCHIVE_RECREATE_BUTTON') : $user->lang('POSTARCHIVE_CREATE_BUTTON')),
			'U_POSTARCHIVE_DOWNLOAD' => $archive !== null ? $archive['download_url'] : '',
		]);
	}
}
