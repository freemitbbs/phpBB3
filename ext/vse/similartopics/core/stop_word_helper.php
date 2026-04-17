<?php
/**
 *
 * Precise Similar Topics
 *
 * @copyright (c) 2025 Matt Friedman
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace vse\similartopics\core;

use phpbb\cache\driver\driver_interface as cache_driver;
use phpbb\extension\manager as ext_manager;
use phpbb\user;

/**
 * A helper class to clean text and remove stop words (localized and additional)
 * for topic titles or other search-related strings.
 */
class stop_word_helper
{
	/** @var cache_driver */
	protected $cache;

	/** @var ext_manager */
	protected $extension_manager;

	/** @var user */
	protected $user;

	/** @var string */
	protected $php_ext;

	/** @var array|null Lookup table for fast filtering */
	protected $ignore_lookup;

	/** @var bool Whether localized ignore words should be loaded */
	protected $use_localized = false;

	/** @var string Additional ignore words string */
	protected $additional_ignore = '';

	/** @var bool Whether ignore words need to be reloaded */
	protected $needs_reload = true;

	/**
	 * Constructor
	 *
	 * @param cache_driver $cache
	 * @param ext_manager $extension_manager
	 * @param user $user
	 * @param string $php_ext
	 */
	public function __construct(cache_driver $cache, ext_manager $extension_manager, user $user, $php_ext)
	{
		$this->cache = $cache;
		$this->extension_manager = $extension_manager;
		$this->user = $user;
		$this->php_ext = $php_ext;
	}

	/**
	 * Set additional ignore words
	 *
	 * @param string $words
	 */
	public function set_additional_ignore_words($words)
	{
		if ($this->additional_ignore !== $words)
		{
			$this->additional_ignore = $words;
			$this->needs_reload = true;
		}
	}

	/**
	 * Set whether to use localized ignore words
	 *
	 * @param bool $value
	 */
	public function set_use_localized($value)
	{
		$value = (bool) $value;
		if ($this->use_localized !== $value)
		{
			$this->use_localized = $value;
			$this->needs_reload = true;
		}
	}

	/**
	 * Clean text (strip quotes, ampersands, stop words)
	 *
	 * @param string $text
	 * @return string
	 */
	public function clean_text($text)
	{
		return implode(' ', $this->get_search_terms($text, true));
	}

	/**
	 * Get normalized search terms for a string of text.
	 *
	 * @param string $text
	 * @param bool $filter_short Whether to filter out short non-CJK words
	 * @return array
	 */
	public function get_search_terms($text, $filter_short = false)
	{
		$terms = $this->make_word_array($text, $filter_short);

		if ($this->use_localized || !empty($this->additional_ignore))
		{
			$this->load_ignore_words();
			$terms = array_filter($terms, [$this, 'filter_ignore_words']);
		}

		return array_values(array_unique($terms));
	}

	/**
	 * Check whether the text contains any CJK characters.
	 *
	 * @param string $text
	 * @return bool
	 */
	public function has_cjk_characters($text)
	{
		return search_title_builder::has_cjk_characters($text);
	}

	/**
	 * Build normalized text for the shadow full-text search column.
	 *
	 * @param string $text
	 * @return string
	 */
	public function build_index_text($text)
	{
		$terms = $this->make_word_array($text, false);

		if ($this->use_localized || !empty($this->additional_ignore))
		{
			$this->load_ignore_words();
			$terms = array_filter($terms, [$this, 'filter_ignore_words']);
		}

		return implode(' ', array_values(array_unique($terms)));
	}

	/**
	 * Load ignore words into memory and build a lookup table
	 */
	protected function load_ignore_words()
	{
		if ($this->needs_reload || $this->ignore_lookup === null)
		{
			// The cache will be invalidated when language, localized setting, or additional words change
			$cache_key = '_pst_ignore_' . md5($this->user->lang_name . '|' . (int) $this->use_localized . '|' . $this->additional_ignore);
			$this->ignore_lookup = $this->cache->get($cache_key);

			if ($this->ignore_lookup === false)
			{
				// Load localized ignore words (if needed)
				$words = $this->use_localized ? $this->load_localized_words() : [];

				// Load additional ignore words (if defined)
				if (!empty($this->additional_ignore))
				{
					$words = array_merge($words, $this->make_word_array($this->additional_ignore));
				}

				$this->ignore_lookup = array_flip(array_unique($words));
				$this->cache->put($cache_key, $this->ignore_lookup);
			}

			$this->needs_reload = false;
		}
	}

	/**
	 * Load localized ignore words
	 *
	 * @return array An array of ignore-words from the user's language pack
	 */
	protected function load_localized_words()
	{
		$words = [];
		$finder = $this->extension_manager->get_finder();
		$files = $finder
			->set_extensions(['vse/similartopics'])
			->prefix('search_ignore_words')
			->suffix('.' . $this->php_ext)
			->extension_directory("/language/{$this->user->lang_name}")
			->core_path("language/{$this->user->lang_name}/")
			->get_files();

		if (current($files))
		{
			include current($files);
		}

		$txt_path = $this->extension_manager->get_extension_path('vse/similartopics', true)
			. 'language/' . $this->user->lang_name . '/search_ignore_bigrams.txt';

		if (file_exists($txt_path))
		{
			$words = array_merge($words, $this->load_word_list_file($txt_path));
		}

		return $words;
	}

	/**
	 * Load whitespace-delimited ignore words from a UTF-8 text file.
	 *
	 * Lines beginning with "#" are treated as comments.
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

	/**
	 * Split text into a word array
	 *
	 * @param string $text A string of text
	 * @param bool $filter_short Whether to filter out words < 3 characters
	 * @return array The original string of text, filtered into an array of individual words
	 */
	protected function make_word_array($text, $filter_short = false)
	{
		return search_title_builder::tokenize($text, $filter_short);
	}

	/**
	 * Filter callback for array_filter to exclude stop words
	 *
	 * @param string $word Word to check
	 * @return bool True to keep a word, false to remove it
	 */
	protected function filter_ignore_words($word)
	{
		return !isset($this->ignore_lookup[$word]);
	}
}
