<?php

namespace freemitbbs\modernsmiley\event;

use freemitbbs\modernsmiley\service\mapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	private mapper $mapper;
	private \phpbb\language\language $language;
	private \phpbb\template\template $template;

	public function __construct(mapper $mapper, \phpbb\language\language $language, \phpbb\template\template $template)
	{
		$this->mapper = $mapper;
		$this->language = $language;
		$this->template = $template;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language',
			'core.page_header' => 'add_assets_to_page',
			'core.generate_smilies_modify_sql' => 'adjust_smiley_query',
			'core.generate_smilies_after' => 'rebuild_inline_smiley_block',
			'core.text_formatter_s9e_configure_after' => 'configure_emoji_rendering',
		];
	}

	public function load_language($event): void
	{
		$this->language->add_lang('common', 'freemitbbs/modernsmiley');
	}

	public function add_assets_to_page($event): void
	{
		$code_mappings = $this->mapper->get_code_mappings();

		$this->template->assign_vars([
			'S_MODERNSMILEY_ENABLED' => !empty($code_mappings),
			'MODERNSMILEY_ASSET_URL_PATTERN_JSON' => json_encode(
				$this->mapper->get_asset_url_pattern(),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
			),
			'MODERNSMILEY_FALLBACK_ASSET_URL_PATTERN_JSON' => json_encode(
				$this->mapper->get_fallback_asset_url_pattern(),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
			),
			'MODERNSMILEY_HOVER_ASSET_URL_PATTERN_JSON' => json_encode(
				$this->mapper->get_hover_asset_url_pattern(),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
			),
			'MODERNSMILEY_HOVER_FALLBACK_ASSET_URL_PATTERN_JSON' => json_encode(
				$this->mapper->get_hover_fallback_asset_url_pattern(),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
			),
			'MODERNSMILEY_CODE_MAP_JSON' => json_encode(
				$code_mappings,
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
			),
		]);
	}

	public function adjust_smiley_query($event): void
	{
		if ($event['mode'] === 'window')
		{
			$event['sql_ary'] = $this->mapper->get_window_mode_sql_ary();
			return;
		}

		if ($event['mode'] === 'inline')
		{
			$event['sql_ary'] = $this->mapper->get_inline_mode_sql_ary();
		}
	}

	public function rebuild_inline_smiley_block($event): void
	{
		global $config, $phpbb_path_helper;

		if ($event['mode'] !== 'inline')
		{
			return;
		}

		$this->template->destroy_block_vars('smiley');

		$root_path = $phpbb_path_helper->get_web_root_path();
		$smilies_path = trim((string) $config['smilies_path'], '/');

		foreach ($this->mapper->get_inline_picker_rows() as $row)
		{
			$image_url = ($row['emoji_seq'] !== '')
				? $this->mapper->get_asset_url($row['emoji_seq'])
				: $root_path . $smilies_path . '/' . $row['smiley_url'];

			$this->template->assign_block_vars('smiley', [
				'SMILEY_CODE' => $row['code'],
				'A_SMILEY_CODE' => addslashes($row['code']),
				'SMILEY_IMG' => $image_url,
				'SMILEY_WIDTH' => $row['smiley_width'],
				'SMILEY_HEIGHT' => $row['smiley_height'],
				'SMILEY_DESC' => $row['emotion'],
			]);
		}
	}

	public function configure_emoji_rendering($event): void
	{
		$configurator = $event['configurator'];
		$this->replace_unicode_emoji($configurator);

		if (!isset($configurator->Emoticons))
		{
			return;
		}

		foreach ($this->mapper->get_code_mappings() as $code => $sequence)
		{
			if ($configurator->Emoticons->exists($code))
			{
				$configurator->Emoticons->set($code, $this->build_template($sequence));
			}
		}
	}

	private function replace_unicode_emoji($configurator): void
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
			$token = (strpos($src, '{@tseq}') !== false) ? '{@tseq}' : '{@seq}';
			$img->setAttribute('src', $this->mapper->get_unicode_emoji_url($token));
			$this->add_class($img, 'modernsmiley-emoji');
			$img->removeAttribute('width');
			$img->removeAttribute('height');
			$fallback_url = $this->mapper->get_unicode_emoji_url($token, true);
			if ($fallback_url !== '')
			{
				$img->setAttribute('onerror', $this->build_onerror($fallback_url));
			}
			else
			{
				$img->removeAttribute('onerror');
			}
			$this->apply_hover_attributes(
				$img,
				$this->mapper->get_unicode_emoji_url($token, false, true),
				$this->mapper->get_unicode_emoji_url($token, true, true)
			);

			$first_child = $img->firstChild;
			if ($first_child && $first_child->nodeName === 'xsl:attribute' && $first_child->getAttribute('name') === 'src')
			{
				$img->removeChild($first_child);
			}
		}
		$dom->saveChanges();
	}

	private function build_template(string $sequence): string
	{
		$attributes = [
			'alt="{.}"',
			'class="smilies emoji modernsmiley-emoji"',
			'draggable="false"',
			'src="' . htmlspecialchars($this->mapper->get_asset_url($sequence), ENT_COMPAT) . '"',
			'title="{.}"',
		];

		$fallback_url = $this->mapper->get_asset_url($sequence, true);
		if ($fallback_url !== '')
		{
			$attributes[] = 'onerror="' . htmlspecialchars($this->build_onerror($fallback_url), ENT_COMPAT) . '"';
		}

		$hover_url = $this->mapper->get_asset_url($sequence, false, true);
		if ($hover_url !== '')
		{
			$attributes[] = 'data-modernsmiley-hover-src="' . htmlspecialchars($hover_url, ENT_COMPAT) . '"';
		}

		$hover_fallback_url = $this->mapper->get_asset_url($sequence, true, true);
		if ($hover_fallback_url !== '')
		{
			$attributes[] = 'data-modernsmiley-hover-fallback-src="' . htmlspecialchars($hover_fallback_url, ENT_COMPAT) . '"';
		}

		return '<img ' . implode(' ', $attributes) . '/>';
	}

	private function add_class(\DOMElement $img, string $class): void
	{
		$classes = preg_split('/\s+/', trim($img->getAttribute('class'))) ?: [];
		if (!in_array($class, $classes, true))
		{
			$classes[] = $class;
		}

		$img->setAttribute('class', trim(implode(' ', array_filter($classes))));
	}

	private function apply_hover_attributes(\DOMElement $img, string $hover_url, string $hover_fallback_url): void
	{
		if ($hover_url !== '')
		{
			$img->setAttribute('data-modernsmiley-hover-src', $hover_url);
		}
		else
		{
			$img->removeAttribute('data-modernsmiley-hover-src');
		}

		if ($hover_fallback_url !== '')
		{
			$img->setAttribute('data-modernsmiley-hover-fallback-src', $hover_fallback_url);
		}
		else
		{
			$img->removeAttribute('data-modernsmiley-hover-fallback-src');
		}
	}

	private function build_onerror(string $fallback_url): string
	{
		return "this.onerror=null;this.src='" . str_replace("'", "\\'", $fallback_url) . "';";
	}
}
