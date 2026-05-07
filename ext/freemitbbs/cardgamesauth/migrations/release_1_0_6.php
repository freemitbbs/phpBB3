<?php

namespace freemitbbs\cardgamesauth\migrations;

class release_1_0_6 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\cardgamesauth\migrations\release_1_0_5',
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.6']],
			['config.add', ['cardgamesauth_sentry_enabled', 0]],
			['config.add', ['cardgamesauth_sentry_dsn', '']],
			['config.add', ['cardgamesauth_sentry_environment', 'production']],
			['config.add', ['cardgamesauth_sentry_release', '']],
			['config.add', ['cardgamesauth_sentry_cdn_url', 'https://browser.sentry-cdn.com/10.45.0/bundle.min.js']],
			['config.add', ['cardgamesauth_sentry_sample_rate', '1']],
			['config.add', ['cardgamesauth_sentry_traces_sample_rate', '0']],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.5']],
			['config.remove', ['cardgamesauth_sentry_enabled']],
			['config.remove', ['cardgamesauth_sentry_dsn']],
			['config.remove', ['cardgamesauth_sentry_environment']],
			['config.remove', ['cardgamesauth_sentry_release']],
			['config.remove', ['cardgamesauth_sentry_cdn_url']],
			['config.remove', ['cardgamesauth_sentry_sample_rate']],
			['config.remove', ['cardgamesauth_sentry_traces_sample_rate']],
		];
	}
}
