<?php

namespace freemitbbs\cardgamesauth\migrations;

class release_1_0_0 extends \phpbb\db\migration\migration
{
	public function update_data()
	{
		return [
			['config.add', ['cardgamesauth_version', '1.0.0']],
			['config.add', ['cardgamesauth_enabled', 1]],
			['config.add', ['cardgamesauth_nav_enabled', 1]],
			['config.add', ['cardgamesauth_launch_redirect', 0]],
			['config.add', ['cardgamesauth_token_ttl', 120]],
			['config.add', ['cardgamesauth_token_rate_limit', 20]],
			['config.add', ['cardgamesauth_token_rate_window', 60]],
			['config.add', ['cardgamesauth_token_secret', $this->generate_secret()]],
			['config.add', ['cardgamesauth_token_issuer', 'freemitbbs-cardgamesauth']],
			['config.add', ['cardgamesauth_token_audience', 'freemitbbs-cardgames-server']],
			['config.add', ['cardgamesauth_client_url', '']],
			['config.add', ['cardgamesauth_ws_url', '']],
			['permission.add', ['u_cardgames_play', true]],
			['permission.permission_set', ['REGISTERED', 'u_cardgames_play', 'group']],
			['permission.permission_set', ['REGISTERED_COPPA', 'u_cardgames_play', 'group']],
			['permission.permission_set', ['NEWLY_REGISTERED', 'u_cardgames_play', 'group']],
			['permission.permission_set', ['GLOBAL_MODERATORS', 'u_cardgames_play', 'group']],
			['permission.permission_set', ['ADMINISTRATORS', 'u_cardgames_play', 'group']],
		];
	}

	protected function generate_secret(): string
	{
		try
		{
			return bin2hex(random_bytes(32));
		}
		catch (\Exception $e)
		{
			return sha1(uniqid((string) mt_rand(), true) . microtime(true));
		}
	}
}
