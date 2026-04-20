<?php

namespace freemitbbs\toptopics\service;

class quality_length
{
	public static function calculate(string $text): int
	{
		$text = self::strip_quote_blocks($text);

		$text = preg_replace('#\[(?:img|attachment)(?:=[^\]]*)?(?::[a-z0-9]+)?\].*?\[/(?:img|attachment)(?::[a-z0-9]+)?\]#isu', ' ', $text);
		$text = preg_replace('#\[url(?:=[^\]]*)?(?::[a-z0-9]+)?\].*?\[/url(?::[a-z0-9]+)?\]#isu', ' ', $text);
		$text = preg_replace('#https?://\S+|www\.\S+#iu', ' ', $text);
		$text = preg_replace('#\[/?[a-z][a-z0-9_=-]*(?:[^\]]*)?(?::[a-z0-9]+)?\]#iu', ' ', $text);
		$text = strip_tags((string) $text);
		$text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		if (preg_match_all('/[\p{L}\p{N}]/u', $text, $matches) === false)
		{
			return 0;
		}

		return count($matches[0]);
	}

	protected static function strip_quote_blocks(string $text): string
	{
		$pattern = '#\[quote(?:=[^\]]*)?(?::[a-z0-9]+)?\].*?\[/quote(?::[a-z0-9]+)?\]#isu';
		for ($i = 0; $i < 10; $i++)
		{
			$stripped = preg_replace($pattern, ' ', $text);
			if ($stripped === null || $stripped === $text)
			{
				break;
			}

			$text = $stripped;
		}

		return $text;
	}
}
