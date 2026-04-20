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

class native_search_cjk_bigrams_common_repair extends \phpbb\db\migration\container_aware_migration
{
	protected const BATCH_SIZE = 50;
	protected const REPAIR_CONFIG = 'similar_topics_native_search_cjk_bigrams_common_repaired';

	public function effectively_installed()
	{
		return !empty($this->config[self::REPAIR_CONFIG]);
	}

	public static function depends_on()
	{
		return [
			'\vse\similartopics\migrations\release_1_8_x\native_search_cjk_bigrams',
		];
	}

	public function update_data()
	{
		return [
			['config.add', [self::REPAIR_CONFIG, 0]],
			['custom', [[$this, 'repair_native_search_bigrams']]],
			['config.update', [self::REPAIR_CONFIG, 1]],
		];
	}

	public function repair_native_search_bigrams($last_post_id = 0)
	{
		if (($this->config['search_type'] ?? '') !== '\\phpbb\\search\\fulltext_native'
			|| empty($this->config['fulltext_native_load_upd'])
			|| !class_exists('\\phpbb\\search\\fulltext_native'))
		{
			$this->finalize_common_cjk_bigrams();
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
			$this->finalize_common_cjk_bigrams();
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

	protected function finalize_common_cjk_bigrams()
	{
		$word_ids = $this->get_common_cjk_bigram_ids();
		if (count($word_ids))
		{
			$sql = 'UPDATE ' . SEARCH_WORDLIST_TABLE . '
				SET word_common = 0
				WHERE ' . $this->db->sql_in_set('word_id', $word_ids);
			$this->db->sql_query($sql);

			$this->recalculate_word_counts($word_ids);
		}

		if (defined('SEARCH_RESULTS_TABLE'))
		{
			$this->db->sql_query('DELETE FROM ' . SEARCH_RESULTS_TABLE);
		}
	}

	protected function get_common_cjk_bigram_ids()
	{
		$word_ids = [];

		$sql = 'SELECT word_id, word_text
			FROM ' . SEARCH_WORDLIST_TABLE . '
			WHERE word_common = 1';
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			if ($this->is_cjk_bigram($row['word_text']))
			{
				$word_ids[] = (int) $row['word_id'];
			}
		}
		$this->db->sql_freeresult($result);

		return $word_ids;
	}

	protected function recalculate_word_counts(array $word_ids)
	{
		foreach ($word_ids as $word_id)
		{
			$sql = 'SELECT COUNT(*) AS match_count
				FROM ' . SEARCH_WORDMATCH_TABLE . '
				WHERE word_id = ' . (int) $word_id;
			$result = $this->db->sql_query($sql);
			$match_count = (int) $this->db->sql_fetchfield('match_count');
			$this->db->sql_freeresult($result);

			$sql = 'UPDATE ' . SEARCH_WORDLIST_TABLE . '
				SET word_count = ' . $match_count . '
				WHERE word_id = ' . (int) $word_id;
			$this->db->sql_query($sql);
		}
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

	protected function is_cjk_bigram($word)
	{
		return (bool) preg_match('#^[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]{2}$#u', $word);
	}
}
