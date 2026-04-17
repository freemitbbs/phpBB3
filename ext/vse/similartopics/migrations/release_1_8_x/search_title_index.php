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

use vse\similartopics\core\search_title_builder;
use vse\similartopics\core\similar_topics;

class search_title_index extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		if (!$this->db_tools->sql_column_exists($this->table_prefix . 'topics', similar_topics::SEARCH_TITLE_COLUMN))
		{
			return false;
		}

		$driver = $this->get_driver();
		return $driver === null || $driver->is_fulltext(similar_topics::SEARCH_TITLE_COLUMN, TOPICS_TABLE);
	}

	public static function depends_on()
	{
		return [
			'\vse\similartopics\migrations\release_1_5_x\mysql_index',
			'\vse\similartopics\migrations\release_1_5_x\postgres_index',
			'\vse\similartopics\migrations\release_1_7_x\dynamic_similar_topics',
			'\vse\similartopics\migrations\release_1_7_x\mssql_index',
			'\vse\similartopics\migrations\release_1_7_x\oracle_index',
			'\vse\similartopics\migrations\release_1_7_x\sqlite3_index',
		];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'topics' => [
					similar_topics::SEARCH_TITLE_COLUMN => ['TEXT_UNI', null],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'topics' => [
					similar_topics::SEARCH_TITLE_COLUMN,
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'backfill_search_title_index']]],
			['custom', [[$this, 'create_search_title_index']]],
		];
	}

	public function backfill_search_title_index()
	{
		$sql = 'SELECT topic_id, topic_title
			FROM ' . TOPICS_TABLE;
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$search_title = search_title_builder::build_index_text($row['topic_title']);
			$update_sql = 'UPDATE ' . TOPICS_TABLE . "
				SET " . similar_topics::SEARCH_TITLE_COLUMN . " = '" . $this->db->sql_escape($search_title) . "'
				WHERE topic_id = " . (int) $row['topic_id'];
			$this->db->sql_query($update_sql);
		}

		$this->db->sql_freeresult($result);
	}

	public function create_search_title_index()
	{
		$driver = $this->get_driver();
		if ($driver === null || $driver->is_fulltext(similar_topics::SEARCH_TITLE_COLUMN, TOPICS_TABLE))
		{
			return;
		}

		if (strpos($this->db->get_sql_layer(), 'mysql') === 0 && !$this->config->offsetExists('similar_topics_fulltext'))
		{
			$this->config->set('similar_topics_fulltext', (string) $driver->get_engine());
		}

		$driver->create_fulltext_index(similar_topics::SEARCH_TITLE_COLUMN, TOPICS_TABLE);
	}

	protected function get_driver()
	{
		$sql_layer = $this->db->get_sql_layer();

		if (strpos($sql_layer, 'mysql') === 0)
		{
			return new \vse\similartopics\driver\mysqli($this->db);
		}

		if ($sql_layer === 'postgres')
		{
			return new \vse\similartopics\driver\postgres($this->db, $this->config);
		}

		if ($sql_layer === 'sqlite3')
		{
			return new \vse\similartopics\driver\sqlite3($this->db);
		}

		if (strpos($sql_layer, 'mssql') === 0)
		{
			return new \vse\similartopics\driver\mssql($this->db);
		}

		if (strpos($sql_layer, 'oracle') === 0)
		{
			return new \vse\similartopics\driver\oracle($this->db);
		}

		return null;
	}
}
