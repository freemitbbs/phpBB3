<?php

namespace freemitbbs\cardgamesauth\migrations;

class release_1_0_5 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return [
			'\freemitbbs\cardgamesauth\migrations\release_1_0_4',
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.5']],
			['custom', [[$this, 'increase_runtime_timeout']]],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['cardgamesauth_version', '1.0.4']],
		];
	}

	public function increase_runtime_timeout(): void
	{
		if ((int) ($this->config['cardgames_node_runtime_timeout_ms'] ?? 0) <= 3000)
		{
			$this->config->set('cardgames_node_runtime_timeout_ms', '10000');
		}
	}
}
