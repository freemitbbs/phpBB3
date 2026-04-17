<?php

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ACP_MODERNSMILEY' => 'Modern Smiley',
	'MODERNSMILEY_EXPLAIN' => 'Manage phpBB smiley rows and Unicode emoji rendering from one place. New smileys created here use a shared placeholder image in phpBB and render through the configured asset URL pattern when an emoji sequence is set.',
	'MODERNSMILEY_ASSET_FIELDSET' => 'Emoji asset pack',
	'MODERNSMILEY_ASSET_EXPLAIN' => 'These URL patterns are used for both Unicode emoji and modern smiley replacements. Use {seq} where the Unicode codepoint sequence should appear. The fallback pattern is optional and will be used if the primary asset fails to load.',
	'MODERNSMILEY_ASSET_URL_PATTERN' => 'Primary asset URL pattern',
	'MODERNSMILEY_ASSET_URL_PATTERN_EXPLAIN' => 'Example: https://fonts.gstatic.com/s/e/notoemoji/latest/{seq}/512.webp',
	'MODERNSMILEY_ASSET_FALLBACK_URL_PATTERN' => 'Fallback asset URL pattern',
	'MODERNSMILEY_ASSET_FALLBACK_URL_PATTERN_EXPLAIN' => 'Optional. Example: https://fonts.gstatic.com/s/e/notoemoji/latest/{seq}/512.gif',
	'MODERNSMILEY_ASSET_SAMPLE' => 'Primary sample URL',
	'MODERNSMILEY_ASSET_FALLBACK_SAMPLE' => 'Fallback sample URL',
	'MODERNSMILEY_HOVER_ASSET_URL_PATTERN' => 'Hover asset URL pattern',
	'MODERNSMILEY_HOVER_ASSET_URL_PATTERN_EXPLAIN' => 'Optional. If set, emoji stay on the primary asset until mouse hover, then switch to this asset.',
	'MODERNSMILEY_HOVER_ASSET_FALLBACK_URL_PATTERN' => 'Hover fallback asset URL pattern',
	'MODERNSMILEY_HOVER_ASSET_FALLBACK_URL_PATTERN_EXPLAIN' => 'Optional. Used if the hover asset fails to load.',
	'MODERNSMILEY_HOVER_ASSET_SAMPLE' => 'Hover sample URL',
	'MODERNSMILEY_HOVER_ASSET_FALLBACK_SAMPLE' => 'Hover fallback sample URL',
	'MODERNSMILEY_ASSET_URL_INVALID' => 'The asset URL pattern must contain {seq}.',
	'MODERNSMILEY_ASSET_FALLBACK_URL_INVALID' => 'The fallback asset URL pattern must be empty or contain {seq}.',
	'MODERNSMILEY_HOVER_ASSET_URL_INVALID' => 'The hover asset URL pattern must be empty or contain {seq}.',
	'MODERNSMILEY_HOVER_ASSET_FALLBACK_URL_INVALID' => 'The hover fallback asset URL pattern must be empty or contain {seq}.',
	'MODERNSMILEY_FIELDSET' => 'Existing smileys',
	'MODERNSMILEY_NEW_FIELDSET' => 'Add smiley',
	'MODERNSMILEY_NEW_EXPLAIN' => 'Create a new smiley row directly from this page. The core placeholder image is used automatically so the new entry appears on the posting form without using the legacy ACP.',
	'MODERNSMILEY_LEGACY' => 'Legacy smiley',
	'MODERNSMILEY_CODES' => 'Codes',
	'MODERNSMILEY_EMOTIONS' => 'Emotions',
	'MODERNSMILEY_POSTING' => 'Show on posting form',
	'MODERNSMILEY_ORDER' => 'Order',
	'MODERNSMILEY_EMOJI_SEQ' => 'Emoji sequence',
	'MODERNSMILEY_EMOJI_SEQ_EXPLAIN' => 'Examples: 1f603 or 2764-fe0f. Leave empty to keep the original phpBB smiley image.',
	'MODERNSMILEY_NEW_SEQ_EXPLAIN' => 'Enter a sequence like 1f44d or 1f468-200d-1f4bb. Leave it empty to create a classic placeholder-backed smiley.',
	'MODERNSMILEY_NEW_PREVIEW_HINT' => 'Preview appears after save',
	'MODERNSMILEY_PREVIEW' => 'Modern preview',
	'MODERNSMILEY_INVALID_INPUT' => 'Invalid smiley input: %s',
	'MODERNSMILEY_NEW_ROW_INVALID' => 'New smileys require both a code and an emotion.',
	'MODERNSMILEY_NEW_SEQ_INVALID' => 'Invalid new emoji sequence format: %s',
]);
