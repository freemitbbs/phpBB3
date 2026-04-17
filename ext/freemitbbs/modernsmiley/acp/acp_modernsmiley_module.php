<?php

namespace freemitbbs\modernsmiley\acp;

use freemitbbs\modernsmiley\service\mapper;

class acp_modernsmiley_module
{
	private const FORM_KEY = 'freemitbbs/modernsmiley';

	public string $tpl_name;
	public string $page_title;
	public string $u_action;

	public function main($id, $mode)
	{
		global $phpbb_container, $phpbb_root_path, $config;

		/** @var mapper $mapper */
		$mapper = $phpbb_container->get('freemitbbs.modernsmiley.mapper');
		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');
		/** @var \phpbb\template\template $template */
		$template = $phpbb_container->get('template');
		/** @var \phpbb\request\request $request */
		$request = $phpbb_container->get('request');
		$config_text = $phpbb_container->get('config');
		$cache = $phpbb_container->get('cache.driver');
		$text_formatter_cache = $phpbb_container->get('text_formatter.cache');

		$language->add_lang('info_acp_modernsmiley', 'freemitbbs/modernsmiley');

		$this->tpl_name = 'acp_modernsmiley';
		$this->page_title = 'ACP_MODERNSMILEY';

		add_form_key(self::FORM_KEY);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$submitted_ids = $request->variable('smiley_id', [0 => 0]);
			$submitted_codes = $request->variable('code', ['' => ''], true);
			$submitted_emotions = $request->variable('emotion', ['' => ''], true);
			$submitted_display = $request->variable('display_on_posting', [0 => 0]);
			$submitted_orders = $request->variable('smiley_order', [0 => 0]);
			$submitted_sequences = $request->variable('emoji_seq', ['' => ''], true);
			$submitted_delete = $request->variable('delete_smiley', [0 => 0]);
			$asset_url_pattern = trim($request->variable('asset_url_pattern', '', true));
			$asset_fallback_url_pattern = trim($request->variable('asset_fallback_url_pattern', '', true));
			$hover_asset_url_pattern = trim($request->variable('hover_asset_url_pattern', '', true));
			$hover_asset_fallback_url_pattern = trim($request->variable('hover_asset_fallback_url_pattern', '', true));

			$invalid = [];
			$existing_rows = [];

			if (!$mapper->is_valid_asset_url_pattern($asset_url_pattern))
			{
				$invalid[] = $language->lang('MODERNSMILEY_ASSET_URL_INVALID');
			}

			if (!$mapper->is_valid_asset_url_pattern($asset_fallback_url_pattern, true))
			{
				$invalid[] = $language->lang('MODERNSMILEY_ASSET_FALLBACK_URL_INVALID');
			}

			if (!$mapper->is_valid_asset_url_pattern($hover_asset_url_pattern, true))
			{
				$invalid[] = $language->lang('MODERNSMILEY_HOVER_ASSET_URL_INVALID');
			}

			if (!$mapper->is_valid_asset_url_pattern($hover_asset_fallback_url_pattern, true))
			{
				$invalid[] = $language->lang('MODERNSMILEY_HOVER_ASSET_FALLBACK_URL_INVALID');
			}

			foreach ($submitted_ids as $index => $smiley_id)
			{
				$smiley_id = (int) $smiley_id;
				$code = $mapper->normalize_smiley_code((string) ($submitted_codes[$index] ?? ''));
				$emotion = trim((string) ($submitted_emotions[$index] ?? ''));
				$sequence = $mapper->normalize_sequence((string) ($submitted_sequences[$index] ?? ''));
				$delete = !empty($submitted_delete[$index]);

				if ($smiley_id <= 0)
				{
					continue;
				}

				if (!$delete && ($code === '' || $emotion === ''))
				{
					$invalid[] = '#' . $smiley_id;
				}

				if ($sequence !== '' && !$mapper->is_valid_sequence($sequence))
				{
					$invalid[] = '#' . $smiley_id . ' => ' . $sequence;
				}

				$existing_rows[] = [
					'smiley_id' => $smiley_id,
					'code' => $code,
					'emotion' => $emotion,
					'display_on_posting' => !empty($submitted_display[$index]),
					'smiley_order' => max(1, (int) ($submitted_orders[$index] ?? 1)),
					'emoji_seq' => $sequence,
					'delete' => $delete,
				];
			}

			$new_code = $mapper->normalize_smiley_code($request->variable('new_code', '', true));
			$new_emotion = trim($request->variable('new_emotion', '', true));
			$new_sequence = $mapper->normalize_sequence($request->variable('new_emoji_seq', '', true));
			$new_has_input = ($new_code !== '' || $new_emotion !== '' || $new_sequence !== '');
			$new_row = [];

			if ($new_has_input)
			{
				if ($new_code === '' || $new_emotion === '')
				{
					$invalid[] = $language->lang('MODERNSMILEY_NEW_ROW_INVALID');
				}

				if ($new_sequence !== '' && !$mapper->is_valid_sequence($new_sequence))
				{
					$invalid[] = $language->lang('MODERNSMILEY_NEW_SEQ_INVALID', $new_sequence);
				}

				if (defined('SMILEY_LIMIT') && ($mapper->get_smiley_count() + 1 > SMILEY_LIMIT))
				{
					$invalid[] = $language->lang('TOO_MANY_SMILIES', SMILEY_LIMIT);
				}

				$new_row = [
					'code' => $new_code,
					'emotion' => $new_emotion,
					'display_on_posting' => $request->variable('new_display_on_posting', 0),
					'smiley_order' => max(1, $request->variable('new_smiley_order', $mapper->get_next_order(true))),
					'emoji_seq' => $new_sequence,
				];
			}

			if (!empty($invalid))
			{
				trigger_error(
					$language->lang('MODERNSMILEY_INVALID_INPUT', implode(', ', $invalid)) . adm_back_link($this->u_action),
					E_USER_WARNING
				);
			}

			$config_text->set('modernsmiley_asset_url', $asset_url_pattern);
			$config_text->set('modernsmiley_asset_fallback_url', $asset_fallback_url_pattern);
			$config_text->set('modernsmiley_hover_asset_url', $hover_asset_url_pattern);
			$config_text->set('modernsmiley_hover_asset_fallback_url', $hover_asset_fallback_url_pattern);
			$mapper->save_smiley_rows($existing_rows, $new_row);
			$cache->destroy('_icons');
			$cache->destroy('sql', SMILIES_TABLE);
			$text_formatter_cache->invalidate();

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'U_ACTION' => $this->u_action,
			'ASSET_URL_PATTERN' => $mapper->get_asset_url_pattern(),
			'ASSET_URL_SAMPLE' => $mapper->get_asset_url('1f603'),
			'ASSET_FALLBACK_URL_PATTERN' => $mapper->get_fallback_asset_url_pattern(),
			'ASSET_FALLBACK_URL_SAMPLE' => $mapper->get_asset_url('1f603', true),
			'HOVER_ASSET_URL_PATTERN' => $mapper->get_hover_asset_url_pattern(),
			'HOVER_ASSET_URL_SAMPLE' => $mapper->get_asset_url('1f603', false, true),
			'HOVER_ASSET_FALLBACK_URL_PATTERN' => $mapper->get_hover_fallback_asset_url_pattern(),
			'HOVER_ASSET_FALLBACK_URL_SAMPLE' => $mapper->get_asset_url('1f603', true, true),
			'SMILIES_PATH' => $phpbb_root_path . $config['smilies_path'] . '/',
			'DEFAULT_PLACEHOLDER_URL' => $mapper->get_default_placeholder_url(),
			'DEFAULT_PLACEHOLDER_SRC' => $phpbb_root_path . $config['smilies_path'] . '/' . $mapper->get_default_placeholder_url(),
			'NEXT_DISPLAY_ORDER' => $mapper->get_next_order(true),
			'NEXT_HIDDEN_ORDER' => $mapper->get_next_order(false),
		]);

		foreach ($mapper->get_rows_for_acp() as $index => $row)
		{
			$template->assign_block_vars('smilies', [
				'ROW_ID' => $index,
				'SMILEY_ID' => $row['smiley_id'],
				'SMILEY_URL' => $row['smiley_url'],
				'IMG_SRC' => $phpbb_root_path . $config['smilies_path'] . '/' . $row['smiley_url'],
				'CODE' => $row['code'],
				'EMOTION' => $row['emotion'],
				'DISPLAY_ON_POSTING' => $row['display_on_posting'],
				'SMILEY_ORDER' => $row['smiley_order'],
				'EMOJI_SEQ' => $row['emoji_seq'],
				'EMOJI_PREVIEW' => ($row['emoji_seq'] !== '') ? $mapper->get_asset_url($row['emoji_seq']) : '',
			]);
		}
	}
}
