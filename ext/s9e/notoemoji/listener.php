<?php

/**
* @package   s9e\noto-emoji
* @copyright Copyright (c) 2017-2018 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\notoemoji;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	private const ASSET_BASE = 'https://cdn.jsdelivr.net/gh/s9e/emoji-assets/assets/noto/svgz/';

	public static function getSubscribedEvents()
	{
		return ['core.text_formatter_s9e_configure_after' => 'onConfigure'];
	}

	public function onConfigure($event)
	{
		$configurator = $event['configurator'];
		$this->replaceEmojiTag($configurator);
	}

	private function replaceEmojiTag($configurator)
	{
		if (!isset($configurator->tags['EMOJI']))
		{
			return;
		}

		$tag = $configurator->tags['EMOJI'];
		$dom = $tag->template->asDOM();
		foreach ($dom->getElementsByTagName('img') as $img)
		{
			$src = $img->getAttribute('src');
			$img->setAttribute('src', self::ASSET_BASE . ((strpos($src, '{@tseq}') !== false) ? '{@tseq}' : '{@seq}') . '.svgz');

			$firstChild = $img->firstChild;
			if ($firstChild && $firstChild->nodeName === 'xsl:attribute' && $firstChild->getAttribute('name') === 'src')
			{
				$img->removeChild($firstChild);
			}
		}
		$dom->saveChanges();
	}
}
