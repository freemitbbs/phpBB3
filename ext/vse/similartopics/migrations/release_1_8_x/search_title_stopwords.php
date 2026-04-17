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

use vse\similartopics\core\search_title_builder;
use vse\similartopics\core\similar_topics;

class search_title_stopwords extends \phpbb\db\migration\container_aware_migration
{
	public function effectively_installed()
	{
		return !empty($this->config['similar_topics_search_title_stopwords']);
	}

	public static function depends_on()
	{
		return [
			'\vse\similartopics\migrations\release_1_8_x\guest_permission',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['similar_topics_search_title_stopwords', 0]],
			['custom', [[$this, 'rebuild_search_title_index']]],
			['config.update', ['similar_topics_search_title_stopwords', 1]],
		];
	}

	public function rebuild_search_title_index()
	{
		$config_text = $this->container->get('config_text');
		$ext_manager = $this->container->get('ext.manager');
		$lang_name = $this->config->offsetExists('default_lang') && $this->config['default_lang']
			? $this->config['default_lang']
			: 'en';
		$ignore_lookup = $this->load_ignore_lookup($ext_manager->get_extension_path('vse/similartopics', true), $lang_name, $config_text->get('similar_topics_words') ?: '');

		$sql = 'SELECT topic_id, topic_title
			FROM ' . TOPICS_TABLE;
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$terms = search_title_builder::tokenize($row['topic_title'], false);
			$terms = array_filter($terms, function ($term) use ($ignore_lookup)
			{
				return !isset($ignore_lookup[$term]);
			});
			$search_title = implode(' ', array_values(array_unique($terms)));
			$update_sql = 'UPDATE ' . TOPICS_TABLE . "
				SET " . similar_topics::SEARCH_TITLE_COLUMN . " = '" . $this->db->sql_escape($search_title) . "'
				WHERE topic_id = " . (int) $row['topic_id'];
			$this->db->sql_query($update_sql);
		}

		$this->db->sql_freeresult($result);
	}

	/**
	 * Build an ignore-word lookup for the target language.
	 *
	 * @param string $extension_path
	 * @param string $lang_name
	 * @param string $additional_ignore_words
	 * @return array
	 */
	protected function load_ignore_lookup($extension_path, $lang_name, $additional_ignore_words)
	{
		$words = [];
		$php_path = $extension_path . 'language/' . $lang_name . '/search_ignore_words.php';
		$txt_path = $extension_path . 'language/' . $lang_name . '/search_ignore_bigrams.txt';

		if (file_exists($php_path))
		{
			include $php_path;
		}

		if (file_exists($txt_path))
		{
			$words = array_merge($words, $this->load_word_list_file($txt_path));
		}

		if ($additional_ignore_words !== '')
		{
			$words = array_merge($words, search_title_builder::tokenize($additional_ignore_words, false));
		}

		return array_flip(array_unique($words));
	}

	/**
	 * Load whitespace-delimited ignore words from a UTF-8 text file.
	 *
	 * @param string $path
	 * @return array
	 */
	protected function load_word_list_file($path)
	{
		$text = @file_get_contents($path);
		if ($text === false || $text === '')
		{
			return [];
		}

		$text = preg_replace('/^\s*#.*$/m', '', $text);

		return preg_split('/[\s\x{3000}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
	}
}
