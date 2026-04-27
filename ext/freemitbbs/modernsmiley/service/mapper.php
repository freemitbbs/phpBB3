<?php

namespace freemitbbs\modernsmiley\service;

use phpbb\config\config;
use phpbb\db\driver\driver_interface;

class mapper
{
	public const DEFAULT_ASSET_URL_PATTERN = 'https://cdn.jsdelivr.net/gh/s9e/emoji-assets/assets/noto/svgz/{seq}.svgz';
	public const DEFAULT_FALLBACK_ASSET_URL_PATTERN = '';
	public const DEFAULT_HOVER_ASSET_URL_PATTERN = '';
	public const DEFAULT_HOVER_FALLBACK_ASSET_URL_PATTERN = '';
	public const DEFAULT_PLACEHOLDER_URL = 'icon_e_smile.gif';
	private const CACHE_CODE_MAPPINGS = '_freemitbbs_modernsmiley_code_mappings';
	private const CACHE_INLINE_PICKER_ROWS = '_freemitbbs_modernsmiley_inline_picker_rows';
	private const CACHE_SECONDS = 86400;

	public const DEFAULT_URL_MAPPINGS = [
		'icon_e_biggrin.gif'    => '1f603',
		'icon_e_smile.gif'      => '1f642',
		'icon_e_wink.gif'       => '1f609',
		'icon_e_sad.gif'        => '1f641',
		'icon_e_surprised.gif'  => '1f62e',
		'icon_eek.gif'          => '1f632',
		'icon_e_confused.gif'   => '1f615',
		'icon_cool.gif'         => '1f60e',
		'icon_lol.gif'          => '1f602',
		'icon_mad.gif'          => '1f621',
		'icon_razz.gif'         => '1f61b',
		'icon_redface.gif'      => '1f633',
		'icon_cry.gif'          => '1f622',
		'icon_evil.gif'         => '1f608',
		'icon_twisted.gif'      => '1f47f',
		'icon_rolleyes.gif'     => '1f644',
		'icon_exclaim.gif'      => '2757',
		'icon_question.gif'     => '2753',
		'icon_idea.gif'         => '1f4a1',
		'icon_arrow.gif'        => '27a1',
		'icon_neutral.gif'      => '1f610',
		'icon_mrgreen.gif'      => '1f60f',
		'icon_e_geek.gif'       => '1f913',
		'icon_e_ugeek.gif'      => '1f9d0',
	];

	private driver_interface $db;
	private config $config;
	private \phpbb\cache\service $cache;
	private string $smilies_table;
	private string $modern_smiley_map_table;
	private ?bool $modern_smiley_map_available = null;

	public function __construct(driver_interface $db, config $config, \phpbb\cache\service $cache, string $smilies_table, string $modern_smiley_map_table)
	{
		$this->db = $db;
		$this->config = $config;
		$this->cache = $cache;
		$this->smilies_table = $smilies_table;
		$this->modern_smiley_map_table = $modern_smiley_map_table;
	}

	public function get_asset_url_pattern(): string
	{
		$pattern = trim((string) ($this->config['modernsmiley_asset_url'] ?? ''));

		return $this->is_valid_asset_url_pattern($pattern) ? $pattern : self::DEFAULT_ASSET_URL_PATTERN;
	}

	public function get_fallback_asset_url_pattern(): string
	{
		$pattern = trim((string) ($this->config['modernsmiley_asset_fallback_url'] ?? ''));

		return $this->is_valid_asset_url_pattern($pattern, true) ? $pattern : self::DEFAULT_FALLBACK_ASSET_URL_PATTERN;
	}

	public function get_hover_asset_url_pattern(): string
	{
		$pattern = trim((string) ($this->config['modernsmiley_hover_asset_url'] ?? ''));

		return $this->is_valid_asset_url_pattern($pattern, true) ? $pattern : self::DEFAULT_HOVER_ASSET_URL_PATTERN;
	}

	public function get_hover_fallback_asset_url_pattern(): string
	{
		$pattern = trim((string) ($this->config['modernsmiley_hover_asset_fallback_url'] ?? ''));

		return $this->is_valid_asset_url_pattern($pattern, true) ? $pattern : self::DEFAULT_HOVER_FALLBACK_ASSET_URL_PATTERN;
	}

	public function get_asset_url(string $sequence, bool $fallback = false, bool $hover = false): string
	{
		if ($hover)
		{
			$pattern = $fallback ? $this->get_hover_fallback_asset_url_pattern() : $this->get_hover_asset_url_pattern();
		}
		else
		{
			$pattern = $fallback ? $this->get_fallback_asset_url_pattern() : $this->get_asset_url_pattern();
		}

		return ($pattern === '') ? '' : str_replace('{seq}', $sequence, $pattern);
	}

	public function is_valid_asset_url_pattern(string $pattern, bool $allow_empty = false): bool
	{
		return ($pattern === '' && $allow_empty) || ($pattern !== '' && strpos($pattern, '{seq}') !== false);
	}

	public function normalize_sequence(string $sequence): string
	{
		return strtolower(trim($sequence));
	}

	public function normalize_smiley_code(string $code): string
	{
		$code = trim($code);

		for ($i = 0; $i < 3; $i++)
		{
			$decoded = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			if ($decoded === $code)
			{
				break;
			}

			$code = $decoded;
		}

		return trim($code);
	}

	public function is_valid_sequence(string $sequence): bool
	{
		return (bool) preg_match('/^[0-9a-f]{1,8}(?:-[0-9a-f]{1,8})*$/', $sequence);
	}

	public function get_rows_for_acp(): array
	{
		if ($this->has_modern_smiley_map_table())
		{
			$sql = 'SELECT s.smiley_id, s.code, s.emotion, s.smiley_url, s.smiley_width, s.smiley_height, s.smiley_order, s.display_on_posting, m.emoji_seq
				FROM ' . $this->smilies_table . ' s
				LEFT JOIN ' . $this->modern_smiley_map_table . ' m
					ON m.smiley_id = s.smiley_id
				ORDER BY s.display_on_posting DESC, s.smiley_order, s.code, s.smiley_id';
		}
		else
		{
			$sql = 'SELECT s.smiley_id, s.code, s.emotion, s.smiley_url, s.smiley_width, s.smiley_height, s.smiley_order, s.display_on_posting
				FROM ' . $this->smilies_table . ' s
				ORDER BY s.display_on_posting DESC, s.smiley_order, s.code, s.smiley_id';
		}
		$result = $this->db->sql_query($sql);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = [
				'smiley_id' => (int) $row['smiley_id'],
				'code' => (string) $row['code'],
				'emotion' => (string) $row['emotion'],
				'smiley_url' => (string) $row['smiley_url'],
				'smiley_width' => (int) $row['smiley_width'],
				'smiley_height' => (int) $row['smiley_height'],
				'smiley_order' => (int) $row['smiley_order'],
				'display_on_posting' => (bool) $row['display_on_posting'],
				'emoji_seq' => (string) ($row['emoji_seq'] ?? ''),
			];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	public function get_default_placeholder_url(): string
	{
		return self::DEFAULT_PLACEHOLDER_URL;
	}

	public function get_smiley_count(): int
	{
		$sql = 'SELECT COUNT(*) AS smiley_count
			FROM ' . $this->smilies_table;
		$result = $this->db->sql_query($sql);
		$count = (int) $this->db->sql_fetchfield('smiley_count');
		$this->db->sql_freeresult($result);

		return $count;
	}

	public function get_next_order(bool $display_on_posting): int
	{
		$sql = 'SELECT MAX(smiley_order) AS max_order
			FROM ' . $this->smilies_table . '
			WHERE display_on_posting = ' . (int) $display_on_posting;
		$result = $this->db->sql_query($sql);
		$max_order = (int) $this->db->sql_fetchfield('max_order');
		$this->db->sql_freeresult($result);

		return $max_order + 1;
	}

	public function get_code_mappings(): array
	{
		$cached = $this->cache->get(self::CACHE_CODE_MAPPINGS);
		if (is_array($cached))
		{
			return $cached;
		}

		if (!$this->has_modern_smiley_map_table())
		{
			return [];
		}

		$sql = 'SELECT s.code, m.emoji_seq
			FROM ' . $this->smilies_table . ' s
			INNER JOIN ' . $this->modern_smiley_map_table . ' m
				ON m.smiley_id = s.smiley_id
			ORDER BY s.smiley_order, s.code';
		$result = $this->db->sql_query($sql);

		$mappings = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$sequence = $this->normalize_sequence((string) $row['emoji_seq']);
			if ($sequence !== '' && $this->is_valid_sequence($sequence))
			{
				$mappings[(string) $row['code']] = $sequence;
			}
		}
		$this->db->sql_freeresult($result);

		$this->cache->put(self::CACHE_CODE_MAPPINGS, $mappings, self::CACHE_SECONDS);

		return $mappings;
	}

	public function get_window_mode_sql_ary(): array
	{
		if (!$this->has_modern_smiley_map_table())
		{
			return [
				'SELECT' => 's.smiley_url, MIN(s.emotion) AS emotion, MIN(s.code) AS code, MIN(s.smiley_width) AS smiley_width, MIN(s.smiley_height) AS smiley_height, MIN(s.smiley_order) AS min_smiley_order',
				'FROM' => [
					$this->smilies_table => 's',
				],
				'GROUP_BY' => 's.smiley_url, s.smiley_width, s.smiley_height',
				'ORDER_BY' => $this->db->sql_quote('min_smiley_order'),
			];
		}

		$sequence_expr = "COALESCE(m.emoji_seq, '')";
		$query_marker = "'" . $this->db->sql_escape('?modernsmiley=') . "'";
		$modern_url = $this->db->sql_concatenate(
			$this->db->sql_concatenate('s.smiley_url', $query_marker),
			$sequence_expr
		);
		$window_smiley_url = $this->db->sql_case($sequence_expr . " <> ''", $modern_url, 's.smiley_url');

		return [
			'SELECT' => $window_smiley_url . ' AS smiley_url, MIN(s.emotion) AS emotion, MIN(s.code) AS code, MIN(s.smiley_width) AS smiley_width, MIN(s.smiley_height) AS smiley_height, MIN(s.smiley_order) AS min_smiley_order',
			'FROM' => [
				$this->smilies_table => 's',
			],
			'LEFT_JOIN' => [
				[
					'FROM' => [
						$this->modern_smiley_map_table => 'm',
					],
					'ON' => 'm.smiley_id = s.smiley_id',
				],
			],
			'GROUP_BY' => "s.smiley_url, s.smiley_width, s.smiley_height, $sequence_expr",
			'ORDER_BY' => $this->db->sql_quote('min_smiley_order'),
		];
	}

	public function get_inline_mode_sql_ary(): array
	{
		if (!$this->has_modern_smiley_map_table())
		{
			return [
				'SELECT' => 's.*',
				'FROM' => [
					$this->smilies_table => 's',
				],
				'WHERE' => 's.display_on_posting = 1',
				'ORDER_BY' => 's.smiley_order',
			];
		}

		$sequence_expr = "COALESCE(m.emoji_seq, '')";
		$query_marker = "'" . $this->db->sql_escape('?modernsmiley=') . "'";
		$modern_url = $this->db->sql_concatenate(
			$this->db->sql_concatenate('s.smiley_url', $query_marker),
			$sequence_expr
		);
		$inline_smiley_url = $this->db->sql_case($sequence_expr . " <> ''", $modern_url, 's.smiley_url');

		return [
			'SELECT' => 'MIN(s.smiley_id) AS smiley_id, MIN(s.code) AS code, MIN(s.emotion) AS emotion, ' . $inline_smiley_url . ' AS smiley_url, MIN(s.smiley_width) AS smiley_width, MIN(s.smiley_height) AS smiley_height, MIN(s.smiley_order) AS min_smiley_order, MIN(s.display_on_posting) AS display_on_posting',
			'FROM' => [
				$this->smilies_table => 's',
			],
			'LEFT_JOIN' => [
				[
					'FROM' => [
						$this->modern_smiley_map_table => 'm',
					],
					'ON' => 'm.smiley_id = s.smiley_id',
				],
			],
			'WHERE' => 's.display_on_posting = 1',
			'GROUP_BY' => $inline_smiley_url,
			'ORDER_BY' => $this->db->sql_quote('min_smiley_order'),
		];
	}

	public function get_inline_picker_rows(): array
	{
		$cached = $this->cache->get(self::CACHE_INLINE_PICKER_ROWS);
		if (is_array($cached))
		{
			return $cached;
		}

		if ($this->has_modern_smiley_map_table())
		{
			$sql = 'SELECT s.smiley_id, s.code, s.emotion, s.smiley_url, s.smiley_width, s.smiley_height, s.smiley_order, m.emoji_seq
				FROM ' . $this->smilies_table . ' s
				LEFT JOIN ' . $this->modern_smiley_map_table . ' m
					ON m.smiley_id = s.smiley_id
				WHERE s.display_on_posting = 1
				ORDER BY s.smiley_order, s.smiley_id';
		}
		else
		{
			$sql = 'SELECT s.smiley_id, s.code, s.emotion, s.smiley_url, s.smiley_width, s.smiley_height, s.smiley_order
				FROM ' . $this->smilies_table . ' s
				WHERE s.display_on_posting = 1
				ORDER BY s.smiley_order, s.smiley_id';
		}

		$result = $this->db->sql_query($sql);

		$rows = [];
		$seen = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$sequence = $this->normalize_sequence((string) ($row['emoji_seq'] ?? ''));
			$is_mapped = ($sequence !== '' && $this->is_valid_sequence($sequence));
			$key = $is_mapped ? ('seq:' . $sequence) : ('url:' . (string) $row['smiley_url']);

			if (isset($seen[$key]))
			{
				continue;
			}
			$seen[$key] = true;

			$rows[] = [
				'code' => (string) $row['code'],
				'emotion' => (string) $row['emotion'],
				'smiley_url' => (string) $row['smiley_url'],
				'smiley_width' => (int) $row['smiley_width'],
				'smiley_height' => (int) $row['smiley_height'],
				'emoji_seq' => $is_mapped ? $sequence : '',
			];
		}
		$this->db->sql_freeresult($result);

		$this->cache->put(self::CACHE_INLINE_PICKER_ROWS, $rows, self::CACHE_SECONDS);

		return $rows;
	}

	public function get_unicode_emoji_url(string $sequence_token, bool $fallback = false, bool $hover = false): string
	{
		return $this->get_asset_url($sequence_token, $fallback, $hover);
	}

	public function replace_id_mappings(array $mappings): void
	{
		if (!$this->has_modern_smiley_map_table())
		{
			return;
		}

		$this->db->sql_query('DELETE FROM ' . $this->modern_smiley_map_table);
		$this->clear_cached_rows();

		if (empty($mappings))
		{
			return;
		}

		$sql_ary = [];
		foreach ($mappings as $smiley_id => $emoji_seq)
		{
			$sql_ary[] = [
				'smiley_id' => (int) $smiley_id,
				'emoji_seq' => (string) $emoji_seq,
			];
		}

		$this->db->sql_multi_insert($this->modern_smiley_map_table, $sql_ary);
		$this->clear_cached_rows();
	}

	public function save_smiley_rows(array $rows, array $new_smiley): void
	{
		$existing_rows = $this->get_existing_smilies_indexed();
		$mappings = [];
		$delete_ids = [];
		$order_groups = [
			1 => [],
			0 => [],
		];

		$this->db->sql_transaction('begin');

		try
		{
			foreach ($rows as $row)
			{
				$smiley_id = (int) ($row['smiley_id'] ?? 0);
				if ($smiley_id <= 0 || !isset($existing_rows[$smiley_id]))
				{
					continue;
				}

				if (!empty($row['delete']))
				{
					$delete_ids[] = $smiley_id;
					continue;
				}

				$display_on_posting = !empty($row['display_on_posting']);
				$smiley_order = max(1, (int) ($row['smiley_order'] ?? 1));
				$code = $this->normalize_smiley_code((string) ($row['code'] ?? ''));
				$emotion = trim((string) ($row['emotion'] ?? ''));
				$emoji_seq = $this->normalize_sequence((string) ($row['emoji_seq'] ?? ''));

				$this->db->sql_query('UPDATE ' . $this->smilies_table . '
					SET ' . $this->db->sql_build_array('UPDATE', [
						'code' => $code,
						'emotion' => $emotion,
						'display_on_posting' => (int) $display_on_posting,
					]) . '
					WHERE smiley_id = ' . $smiley_id);

				$order_groups[(int) $display_on_posting][] = [
					'smiley_id' => $smiley_id,
					'smiley_order' => $smiley_order,
				];

				if ($emoji_seq !== '')
				{
					$mappings[$smiley_id] = $emoji_seq;
				}
			}

			if (!empty($delete_ids))
			{
				$sql = 'DELETE FROM ' . $this->smilies_table . '
					WHERE ' . $this->db->sql_in_set('smiley_id', array_map('intval', $delete_ids));
				$this->db->sql_query($sql);
			}

			if (!empty($new_smiley))
			{
				$placeholder = $this->get_placeholder_metadata();
				$display_on_posting = !empty($new_smiley['display_on_posting']);
				$smiley_order = max(1, (int) ($new_smiley['smiley_order'] ?? 1));
				$emoji_seq = $this->normalize_sequence((string) ($new_smiley['emoji_seq'] ?? ''));

				$this->db->sql_query('INSERT INTO ' . $this->smilies_table . ' ' . $this->db->sql_build_array('INSERT', [
					'code' => $this->normalize_smiley_code((string) $new_smiley['code']),
					'emotion' => trim((string) $new_smiley['emotion']),
					'smiley_url' => $placeholder['smiley_url'],
					'smiley_width' => $placeholder['smiley_width'],
					'smiley_height' => $placeholder['smiley_height'],
					'smiley_order' => $smiley_order,
					'display_on_posting' => (int) $display_on_posting,
				]));

				$smiley_id = (int) $this->db->sql_nextid();
				$order_groups[(int) $display_on_posting][] = [
					'smiley_id' => $smiley_id,
					'smiley_order' => $smiley_order,
				];

				if ($emoji_seq !== '')
				{
					$mappings[$smiley_id] = $emoji_seq;
				}
			}

			$this->resequence_orders($order_groups);
			$this->replace_id_mappings($mappings);

			$this->db->sql_transaction('commit');
		}
		catch (\Throwable $e)
		{
			$this->db->sql_transaction('rollback');
			throw $e;
		}

		$this->clear_cached_rows();
	}

	private function clear_cached_rows(): void
	{
		$this->cache->destroy(self::CACHE_CODE_MAPPINGS);
		$this->cache->destroy(self::CACHE_INLINE_PICKER_ROWS);
	}

	private function get_existing_smilies_indexed(): array
	{
		$sql = 'SELECT smiley_id
			FROM ' . $this->smilies_table;
		$result = $this->db->sql_query($sql);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[(int) $row['smiley_id']] = true;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	private function get_placeholder_metadata(): array
	{
		$sql = 'SELECT smiley_url, smiley_width, smiley_height
			FROM ' . $this->smilies_table . "
			WHERE smiley_url = '" . $this->db->sql_escape(self::DEFAULT_PLACEHOLDER_URL) . "'
			ORDER BY smiley_id";
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($row)
		{
			return [
				'smiley_url' => (string) $row['smiley_url'],
				'smiley_width' => (int) $row['smiley_width'],
				'smiley_height' => (int) $row['smiley_height'],
			];
		}

		$sql = 'SELECT smiley_url, smiley_width, smiley_height
			FROM ' . $this->smilies_table . '
			ORDER BY smiley_id';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($row)
		{
			return [
				'smiley_url' => (string) $row['smiley_url'],
				'smiley_width' => (int) $row['smiley_width'],
				'smiley_height' => (int) $row['smiley_height'],
			];
		}

		return [
			'smiley_url' => self::DEFAULT_PLACEHOLDER_URL,
			'smiley_width' => 15,
			'smiley_height' => 17,
		];
	}

	private function resequence_orders(array $order_groups): void
	{
		foreach ([1, 0] as $display_on_posting)
		{
			usort($order_groups[$display_on_posting], static function (array $left, array $right): int
			{
				return [$left['smiley_order'], $left['smiley_id']] <=> [$right['smiley_order'], $right['smiley_id']];
			});

			$order = 1;
			foreach ($order_groups[$display_on_posting] as $row)
			{
				$this->db->sql_query('UPDATE ' . $this->smilies_table . '
					SET ' . $this->db->sql_build_array('UPDATE', [
						'smiley_order' => $order,
						'display_on_posting' => $display_on_posting,
					]) . '
					WHERE smiley_id = ' . (int) $row['smiley_id']);
				++$order;
			}
		}
	}

	private function has_modern_smiley_map_table(): bool
	{
		if ($this->modern_smiley_map_available !== null)
		{
			return $this->modern_smiley_map_available;
		}

		$this->db->sql_return_on_error(true);
		$result = $this->db->sql_query_limit('SELECT smiley_id
			FROM ' . $this->modern_smiley_map_table . '
			ORDER BY smiley_id', 1);
		$error_triggered = $this->db->get_sql_error_triggered();
		if ($result)
		{
			$this->db->sql_freeresult($result);
		}
		$this->db->sql_return_on_error(false);

		$this->modern_smiley_map_available = !$error_triggered;

		return $this->modern_smiley_map_available;
	}
}
