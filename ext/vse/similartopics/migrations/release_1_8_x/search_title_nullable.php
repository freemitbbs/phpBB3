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

use vse\similartopics\core\similar_topics;

class search_title_nullable extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return !empty($this->config['similar_topics_search_title_nullable']);
	}

	public static function depends_on()
	{
		return [
			'\vse\similartopics\migrations\release_1_8_x\search_title_stopwords',
		];
	}

	public function update_schema()
	{
		return [
			'change_columns' => [
				$this->table_prefix . 'topics' => [
					similar_topics::SEARCH_TITLE_COLUMN => ['TEXT_UNI', null],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'change_columns' => [
				$this->table_prefix . 'topics' => [
					similar_topics::SEARCH_TITLE_COLUMN => ['TEXT_UNI', ''],
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['similar_topics_search_title_nullable', 1]],
		];
	}
}
