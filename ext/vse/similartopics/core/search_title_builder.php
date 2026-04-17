<?php
/**
 *
 * Precise Similar Topics
 *
 * @copyright (c) 2026 Matt Friedman
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace vse\similartopics\core;

class search_title_builder
{
	/**
	 * Check whether the text contains any CJK characters.
	 *
	 * @param string $text
	 * @return bool
	 */
	public static function has_cjk_characters($text)
	{
		return (bool) preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $text);
	}

	/**
	 * Build a normalized token string for the shadow full-text column.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function build_index_text($text)
	{
		return implode(' ', array_values(array_unique(static::tokenize($text, false))));
	}

	/**
	 * Tokenize text into search terms.
	 *
	 * @param string $text
	 * @param bool $filter_short
	 * @return array
	 */
	public static function tokenize($text, $filter_short = false)
	{
		$text = str_replace(['&quot;', '&amp;'], '', $text);
		$text = trim(preg_replace('#[^\p{L}\p{N}]+#u', ' ', $text));
		if ($text === '')
		{
			return [];
		}

		$segments = preg_split('/\s+/u', utf8_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
		$words = [];

		foreach ($segments as $segment)
		{
			$words = array_merge($words, static::tokenize_segment($segment, $filter_short));
		}

		return $words;
	}

	/**
	 * Tokenize a segment into search terms.
	 *
	 * @param string $segment
	 * @param bool $filter_short
	 * @return array
	 */
	protected static function tokenize_segment($segment, $filter_short = false)
	{
		$parts = [];
		preg_match_all('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]+|[^\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\s]+/u', $segment, $parts);

		$tokens = [];
		foreach ($parts[0] as $part)
		{
			if (static::has_cjk_characters($part))
			{
				$tokens = array_merge($tokens, static::make_cjk_ngrams($part));
				continue;
			}

			if (!$filter_short || utf8_strlen($part) >= 3)
			{
				$tokens[] = $part;
			}
		}

		return $tokens;
	}

	/**
	 * Build overlapping bigrams for a contiguous CJK string.
	 *
	 * @param string $text
	 * @return array
	 */
	protected static function make_cjk_ngrams($text)
	{
		$chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
		$count = count($chars);

		if ($count <= 1)
		{
			return $chars;
		}

		if ($count === 2)
		{
			return [$text];
		}

		$ngrams = [];
		for ($i = 0; $i < $count - 1; $i++)
		{
			$ngrams[] = $chars[$i] . $chars[$i + 1];
		}

		return $ngrams;
	}
}
