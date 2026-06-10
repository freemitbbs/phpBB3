<?php

namespace freemitbbs\newsscraper\migrations;

class release_1_0_6 extends \phpbb\db\migration\migration
{
	private const OLD_DIGEST_FORUM_NAME = '新闻文摘';
	private const NEW_DIGEST_FORUM_NAME = '新闻摘要';
	private const NEW_DIGEST_FORUM_DESC = 'AI 生成的新闻摘要。';

	public static function depends_on()
	{
		return [
			'\freemitbbs\newsscraper\migrations\release_1_0_5',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'rename_managed_digest_forum']]],
			['config.update', ['newsscraper_version', '1.0.6']],
		];
	}

	public function rename_managed_digest_forum(): void
	{
		$forum_id = (int) $this->get_config_value('newsscraper_digest_forum_managed');
		if ($forum_id <= 0)
		{
			return;
		}

		$sql_ary = [
			'forum_name' => self::NEW_DIGEST_FORUM_NAME,
			'forum_desc' => self::NEW_DIGEST_FORUM_DESC,
		];

		$sql = 'UPDATE ' . FORUMS_TABLE . '
			SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
			WHERE forum_id = ' . $forum_id . "
				AND forum_name = '" . $this->db->sql_escape(self::OLD_DIGEST_FORUM_NAME) . "'";
		$this->db->sql_query($sql);
	}

	protected function get_config_value(string $config_name): string
	{
		$sql = 'SELECT config_value
			FROM ' . CONFIG_TABLE . "
			WHERE config_name = '" . $this->db->sql_escape($config_name) . "'";
		$result = $this->db->sql_query($sql);
		$config_value = (string) $this->db->sql_fetchfield('config_value');
		$this->db->sql_freeresult($result);

		return $config_value;
	}
}
