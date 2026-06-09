<?php

namespace freemitbbs\postarchive\controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class main
{
	public const FORM_KEY = 'freemitbbs/postarchive';

	protected \phpbb\controller\helper $helper;
	protected \phpbb\language\language $language;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\user $user;
	protected \freemitbbs\postarchive\service\manager $manager;
	protected string $root_path;
	protected string $php_ext;

	public function __construct(
		\phpbb\controller\helper $helper,
		\phpbb\language\language $language,
		\phpbb\request\request_interface $request,
		\phpbb\user $user,
		\freemitbbs\postarchive\service\manager $manager,
		string $root_path,
		string $php_ext
	)
	{
		$this->helper = $helper;
		$this->language = $language;
		$this->request = $request;
		$this->user = $user;
		$this->manager = $manager;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public function create(): RedirectResponse
	{
		$this->boot_language();
		$this->require_registered_user($this->helper->route('freemitbbs_postarchive_create'));

		if (!$this->request->is_set_post('create_archive') || !check_form_key(self::FORM_KEY))
		{
			trigger_error($this->language->lang('FORM_INVALID'), E_USER_WARNING);
			throw new \RuntimeException('Invalid post archive form token.');
		}

		try
		{
			$this->manager->cleanup_expired_archives();
			$this->manager->enqueue_archive($this->current_user_id());
		}
		catch (\Throwable $e)
		{
			trigger_error($this->language->lang('POSTARCHIVE_QUEUE_FAILED'), E_USER_WARNING);
			throw new \RuntimeException('Unable to queue post archive.', 0, $e);
		}

		return new RedirectResponse($this->ucp_url(false));
	}

	public function download(int $archive): BinaryFileResponse
	{
		$this->boot_language();
		$this->require_registered_user($this->helper->route('freemitbbs_postarchive_download', ['archive' => $archive]));
		$this->manager->cleanup_expired_archives();

		$row = $this->manager->archive_for_user($archive, $this->current_user_id());
		if ($row === null)
		{
			trigger_error($this->language->lang('POSTARCHIVE_NOT_AVAILABLE'), E_USER_WARNING);
			throw new \RuntimeException('Post archive is not available.');
		}

		$archive_path = $this->manager->archive_file_path((string) $row['physical_filename']);
		if (!is_file($archive_path) || !is_readable($archive_path))
		{
			$this->manager->cleanup_expired_archives();
			trigger_error($this->language->lang('POSTARCHIVE_NOT_AVAILABLE'), E_USER_WARNING);
			throw new \RuntimeException('Post archive file is not available.');
		}

		$response = new BinaryFileResponse($archive_path, 200, [
			'Content-Type' => 'application/zip',
			'Cache-Control' => 'private, no-cache, no-store, max-age=0',
			'Pragma' => 'no-cache',
			'X-Content-Type-Options' => 'nosniff',
		], false);
		$response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, (string) $row['real_filename'], (string) $row['real_filename']);

		return $response;
	}

	public function count_current_user_posts(): int
	{
		if (!$this->is_registered_user())
		{
			return 0;
		}

		return $this->manager->count_visible_posts($this->current_user_id());
	}

	public function latest_current_user_archive(): ?array
	{
		if (!$this->is_registered_user())
		{
			return null;
		}

		$this->manager->cleanup_expired_archives();

		return $this->manager->latest_user_archive($this->current_user_id());
	}

	public function latest_current_user_pending_job(): ?array
	{
		if (!$this->is_registered_user())
		{
			return null;
		}

		return $this->manager->latest_user_pending_job($this->current_user_id());
	}

	public function latest_current_user_failed_job(int $after_time = 0): ?array
	{
		if (!$this->is_registered_user())
		{
			return null;
		}

		return $this->manager->latest_user_failed_job($this->current_user_id(), $after_time);
	}

	protected function boot_language(): void
	{
		$this->language->add_lang('common', 'freemitbbs/postarchive');
	}

	protected function require_registered_user(string $redirect): void
	{
		if (!$this->is_registered_user())
		{
			login_box($redirect, $this->language->lang('POSTARCHIVE_LOGIN_REQUIRED'));
		}
	}

	protected function is_registered_user(): bool
	{
		return !empty($this->user->data['is_registered']) && $this->current_user_id() > ANONYMOUS;
	}

	protected function current_user_id(): int
	{
		return (int) $this->user->data['user_id'];
	}

	protected function ucp_url(bool $html_ampersand = true): string
	{
		return append_sid(
			$this->root_path . 'ucp.' . $this->php_ext,
			'i=-freemitbbs-postarchive-ucp-main_module&mode=download',
			$html_ampersand
		);
	}
}
