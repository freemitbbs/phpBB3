<?php
/**
 *
 * Top Stats extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 stoker - https://phpbb3bbcodes.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

declare(strict_types=1);

namespace stoker\topstats\helper;

use phpbb\language\language;

/**
 * Number formatting helper with localization support.
 * Formats numeric values based on language-specific settings.
 */
final class number_helper
{
	/** @var language */
	protected $language;

	/** @var int|null Cached format settings per request */
	private $decimals = null;
	
	/** @var string|null */
	private $decimal_separator = null;
	
	/** @var string|null */
	private $thousands_separator = null;

	public function __construct(language $language)
	{
		$this->language = $language;
	}

	/**
	 * Format a number using localized settings from language keys.
	 * Settings are cached per request to avoid repeated language lookups.
	 * 
	 * Language keys used:
	 * - DECIMAL_TS: Number of decimal places (0-6)
	 * - DECIMAL_SEPARATOR_TS: Decimal separator character
	 * - THOUSANDS_SEPARATOR_TS: Thousands separator character
	 * 
	 * @param float $value Numeric value to format
	 * @return string Formatted number string
	 */
	public function format_number(float $value): string
	{
		if ($this->decimals === null)
		{
			$this->load_format_settings();
		}

		$is_integer = (floor($value) === $value);

		if ($this->decimals === 0 || $is_integer)
		{
			return number_format($value, 0, $this->decimal_separator, $this->thousands_separator);
		}

		return number_format($value, $this->decimals, $this->decimal_separator, $this->thousands_separator);
	}

	/**
	 * Load and cache format settings from language keys.
	 * Validates and clamps decimal precision to safe range (0-6).
	 */
	private function load_format_settings(): void
	{
		$decimals = (int) $this->language->lang('DECIMAL_TS');
		$this->decimals = max(0, min($decimals, 6));

		$this->decimal_separator = (string) $this->language->lang('DECIMAL_SEPARATOR_TS');
		$this->thousands_separator = (string) $this->language->lang('THOUSANDS_SEPARATOR_TS');
	}
}
