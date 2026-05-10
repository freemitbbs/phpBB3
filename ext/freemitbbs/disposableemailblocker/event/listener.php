<?php

namespace freemitbbs\disposableemailblocker\event;

use freemitbbs\disposableemailblocker\service\domain_blocklist;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	protected domain_blocklist $blocklist;
	protected \phpbb\language\language $language;
	protected \phpbb\user $user;

	public function __construct(
		domain_blocklist $blocklist,
		\phpbb\language\language $language,
		\phpbb\user $user
	)
	{
		$this->blocklist = $blocklist;
		$this->language = $language;
		$this->user = $user;
	}

	public static function getSubscribedEvents(): array
	{
		return [
			'core.ucp_register_data_after' => 'validate_registration_email',
			'core.ucp_profile_reg_details_validate' => 'validate_profile_email',
		];
	}

	public function validate_registration_email($event): void
	{
		$this->validate_event_email($event, false);
	}

	public function validate_profile_email($event): void
	{
		$this->validate_event_email($event, true);
	}

	protected function validate_event_email($event, bool $only_if_changed): void
	{
		if (empty($event['submit']))
		{
			return;
		}

		$data = $event['data'] ?? [];
		$email = strtolower(trim((string) ($data['email'] ?? '')));
		if ($email === '')
		{
			return;
		}

		if ($only_if_changed && $email === strtolower((string) ($this->user->data['user_email'] ?? '')))
		{
			return;
		}

		if (!$this->blocklist->is_disposable_email($email))
		{
			return;
		}

		$this->language->add_lang('common', 'freemitbbs/disposableemailblocker');
		$error = $event['error'] ?? [];
		$error[] = $this->language->lang('DISPOSABLEEMAILBLOCKER_EMAIL_BLOCKED');
		$event['error'] = $error;
	}
}
