<?php
/**
 *
 * Precise Similar Topics
 *
 * @copyright (c) 2026 Matt Friedman
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace vse\similartopics\migrations\release_1_8_x;

class native_search_cjk_bigrams extends \phpbb\db\migration\container_aware_migration
{
	protected const BATCH_SIZE = 20;

	public function effectively_installed()
	{
		return !empty($this->config['similar_topics_native_search_cjk_bigrams_reindexed']);
	}

	public static function depends_on()
	{
		return [
			'\vse\similartopics\migrations\release_1_8_x\search_title_stopwords',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['similar_topics_native_search_cjk_bigrams_reindexed', 0]],
			['custom', [[$this, 'reindex_native_search_bigrams']]],
			['config.update', ['similar_topics_native_search_cjk_bigrams_reindexed', 1]],
		];
	}

	public function reindex_native_search_bigrams($last_post_id = 0)
	{
		if (($this->config['search_type'] ?? '') !== '\\phpbb\\search\\fulltext_native'
			|| empty($this->config['fulltext_native_load_upd'])
			|| !class_exists('\\phpbb\\search\\fulltext_native'))
		{
			return true;
		}

		$last_post_id = (int) $last_post_id;

		$sql = 'SELECT post_id, forum_id, poster_id, post_subject, post_text
			FROM ' . POSTS_TABLE . '
			WHERE post_id > ' . $last_post_id . '
			ORDER BY post_id ASC';
		$result = $this->db->sql_query_limit($sql, self::BATCH_SIZE);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		if (!count($rows))
		{
			return true;
		}

		$search = $this->get_native_search_backend();

		foreach ($rows as $row)
		{
			$last_post_id = (int) $row['post_id'];
			if (!$this->has_cjk_text((string) $row['post_subject'] . ' ' . (string) $row['post_text']))
			{
				continue;
			}

			$message = (string) $row['post_text'];
			$subject = (string) $row['post_subject'];
			$search->index('edit', (int) $row['post_id'], $message, $subject, (int) $row['poster_id'], (int) $row['forum_id']);
		}

		return $last_post_id;
	}

	protected function get_native_search_backend()
	{
		$error = false;

		return new \phpbb\search\fulltext_native(
			$error,
			$this->phpbb_root_path,
			$this->php_ext,
			$this->container->get('auth'),
			$this->config,
			$this->db,
			$this->container->get('user'),
			$this->container->get('dispatcher')
		);
	}

	protected function has_cjk_text($text)
	{
		return (bool) preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $text);
	}
}
