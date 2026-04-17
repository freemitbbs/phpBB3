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

class search_title_ready extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return !empty($this->config['similar_topics_search_title_ready']);
	}

	public static function depends_on()
	{
		return [
			'\vse\similartopics\migrations\release_1_8_x\search_title_nullable',
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['similar_topics_search_title_ready', 1]],
		];
	}
}
