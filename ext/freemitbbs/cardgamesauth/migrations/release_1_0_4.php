<?php

namespace freemitbbs\cardgamesauth\migrations;

class release_1_0_4 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\cardgamesauth\migrations\release_1_0_3',
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.4']],
			['config.add', ['cardgames_node_runtime_base_url', '']],
			['config.add', ['cardgames_node_runtime_service_id', 'phpbb-cardgamesauth']],
			['config.add', ['cardgames_node_runtime_service_secret', $this->generate_secret()]],
			['config.add', ['cardgames_node_runtime_enabled', 0]],
			['config.add', ['cardgames_node_runtime_timeout_ms', 10000]],
			['permission.add', ['m_cardgames_manage', true]],
			['permission.add', ['m_cardgames_replay_export', true]],
			['permission.permission_set', ['GLOBAL_MODERATORS', 'm_cardgames_manage', 'group']],
			['permission.permission_set', ['GLOBAL_MODERATORS', 'm_cardgames_replay_export', 'group']],
			['permission.permission_set', ['ADMINISTRATORS', 'm_cardgames_manage', 'group']],
			['permission.permission_set', ['ADMINISTRATORS', 'm_cardgames_replay_export', 'group']],
		];
	}

	public function revert_data()
	{
		return [
			['permission.remove', ['m_cardgames_replay_export', true]],
			['permission.remove', ['m_cardgames_replay_export', false]],
			['permission.remove', ['m_cardgames_manage', true]],
			['permission.remove', ['m_cardgames_manage', false]],
			['config.remove', ['cardgames_node_runtime_timeout_ms']],
			['config.remove', ['cardgames_node_runtime_enabled']],
			['config.remove', ['cardgames_node_runtime_service_secret']],
			['config.remove', ['cardgames_node_runtime_service_id']],
			['config.remove', ['cardgames_node_runtime_base_url']],
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
