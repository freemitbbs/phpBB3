<?php

namespace freemitbbs\cardgamesauth\migrations;

class release_1_0_7 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\cardgamesauth\migrations\release_1_0_6',
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.7']],
			['config.remove', ['cardgamesauth_sentry_enabled']],
			['config.remove', ['cardgamesauth_sentry_dsn']],
			['config.remove', ['cardgamesauth_sentry_environment']],
			['config.remove', ['cardgamesauth_sentry_release']],
			['config.remove', ['cardgamesauth_sentry_cdn_url']],
			['config.remove', ['cardgamesauth_sentry_sample_rate']],
			['config.remove', ['cardgamesauth_sentry_traces_sample_rate']],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.6']],
		];
	}
}
