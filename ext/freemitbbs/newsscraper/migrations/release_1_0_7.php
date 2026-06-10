<?php

namespace freemitbbs\newsscraper\migrations;

class release_1_0_7 extends \phpbb\db\migration\migration
{
	private const OLD_NAME = '新闻文摘';
	private const NEW_NAME = '新闻摘要';

	public static function depends_on()
	{
		return [
			'\freemitbbs\newsscraper\migrations\release_1_0_6',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'rename_existing_digest_posters']]],
			['config.update', ['newsscraper_version', '1.0.7']],
		];
	}

	public function rename_existing_digest_posters(): void
	{
		$forum_id = (int) $this->get_config_value('newsscraper_digest_forum_managed');
		if ($forum_id <= 0)
		{
			return;
		}

		$sql = 'UPDATE ' . POSTS_TABLE . "
			SET post_username = '" . $this->db->sql_escape(self::NEW_NAME) . "'
			WHERE forum_id = " . $forum_id . "
				AND post_username = '" . $this->db->sql_escape(self::OLD_NAME) . "'";
		$this->db->sql_query($sql);

		$sql = 'UPDATE ' . TOPICS_TABLE . "
			SET topic_first_poster_name = CASE
					WHEN topic_first_poster_name = '" . $this->db->sql_escape(self::OLD_NAME) . "'
					THEN '" . $this->db->sql_escape(self::NEW_NAME) . "'
					ELSE topic_first_poster_name
				END,
				topic_last_poster_name = CASE
					WHEN topic_last_poster_name = '" . $this->db->sql_escape(self::OLD_NAME) . "'
					THEN '" . $this->db->sql_escape(self::NEW_NAME) . "'
					ELSE topic_last_poster_name
				END
			WHERE forum_id = " . $forum_id . "
				AND (topic_first_poster_name = '" . $this->db->sql_escape(self::OLD_NAME) . "'
					OR topic_last_poster_name = '" . $this->db->sql_escape(self::OLD_NAME) . "')";
		$this->db->sql_query($sql);

		$sql = 'UPDATE ' . FORUMS_TABLE . "
			SET forum_last_poster_name = '" . $this->db->sql_escape(self::NEW_NAME) . "'
			WHERE forum_id = " . $forum_id . "
				AND forum_last_poster_name = '" . $this->db->sql_escape(self::OLD_NAME) . "'";
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
