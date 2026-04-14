<?php

namespace freemitbbs\riskwatch\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	protected \phpbb\language\language $language;

	public function __construct(\phpbb\language\language $language)
	{
		$this->language = $language;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language',
		];
	}

	public function load_language($event): void
	{
		$this->language->add_lang('riskwatch', 'freemitbbs/riskwatch');
	}
}
