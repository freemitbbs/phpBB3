<?php
/**
 * MathJax3
 * An extension for the phpBB Forum Software package.
 *
 * Adapted original code from:
 * Advanced BBCode Box v3.3.8
 *
 * @copyright (c) 2025, Thorsten Ahlers
 * @copyright (c) 2016, Matt Friedman
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace kinerity\mathjax\core;

/**
 * Class bbcodes_installer
 */
class bbcodes_installer
{
	/** @var \acp_bbcodes */
	protected object $acp_bbcodes;

	/** @var driver_interface */
	protected object $db;

	/** @var string */
	protected string $phpbb_root_path;

	/** @var string */
	protected string $php_ext;

	/**
	 * Constructor
	 *
	 * @param driver_interface $db
	 * @param string           $phpbb_root_path
	 * @param string           $php_ext
	 * @access public
	 */
	public function __construct
	(
		\phpbb\db\driver\driver_interface $db,
		string $phpbb_root_path,
		string $php_ext
	)
	{
		$this->db			   = $db;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext         = $php_ext;

		$this->acp_bbcodes     = $this->get_acp_bbcodes();
	}

	/**
	 * Installs bbcodes, used by migrations to perform add/updates
	 *
	 * @param array $bbcodes Array of bbcodes to install
	 * @access public
	 */
	public function install_bbcodes(array $bbcodes): void
	{
		foreach ($bbcodes as $bbcode_name => $bbcode_data)
		{
			$bbcode_data = $this->build_bbcode($bbcode_data);

			if ($bbcode_id = $this->bbcode_exists($bbcode_name, $bbcode_data['bbcode_tag']))
			{
				$this->update_bbcode($bbcode_id, $bbcode_data);
				continue;
			}

			$this->add_bbcode($bbcode_data);
		}
	}

	/**
	 * Deletes bbcodes, used by migrations to perform add/updates.
	 *
	 * @param array $bbcodes Array of bbcodes to delete
	 * @access public
	 */
	public function delete_bbcodes(array $bbcodes): void
	{
		foreach ($bbcodes as $bbcode_name => $bbcode_data)
		{
			$bbcode_data = $this->build_bbcode($bbcode_data);

			if ($bbcode_id = $this->bbcode_exists($bbcode_name, $bbcode_data['bbcode_tag']))
			{
				$this->remove_bbcode($bbcode_id, $bbcode_data);
			}
		}
	}

	/**
	 * Get the acp_bbcodes class
	 *
	 * @return \acp_bbcodes
	 * @access protected
	 */
	protected function get_acp_bbcodes(): object
	{
		if (!class_exists('acp_bbcodes'))
		{
			include $this->phpbb_root_path . 'includes/acp/acp_bbcodes.' . $this->php_ext;
		}

		return new \acp_bbcodes();
	}

	/**
	 * Build the bbcode
	 *
	 * @param array $bbcode_data Initial bbcode data
	 * @return array Complete bbcode data array
	 * @access protected
	 */
	protected function build_bbcode(array $bbcode_data): array
	{
		$data = $this->acp_bbcodes->build_regexp($bbcode_data['bbcode_match'], $bbcode_data['bbcode_tpl']);

		return array_replace($bbcode_data, [
			'bbcode_tag'          => $data['bbcode_tag'],
			'first_pass_match'    => $data['first_pass_match'],
			'first_pass_replace'  => $data['first_pass_replace'],
			'second_pass_match'   => $data['second_pass_match'],
			'second_pass_replace' => $data['second_pass_replace'],
		]);
	}

	/**
	 * Get the max bbcode id value
	 *
	 * @return int bbcode identifier
	 * @access protected
	 */
	protected function get_max_bbcode_id(): int
	{
		return $this->get_max_column_value('bbcode_id');
	}

	/**
	 * Check if bbcode exists
	 *
	 * @param string $bbcode_name Name of bbcode
	 * @param string $bbcode_tag  Tag name of bbcode
	 * @return string|false Existing bbcode identifier or false if not found
	 * @access protected
	 */
	public function bbcode_exists(string $bbcode_name, string $bbcode_tag)
	{
		$sql = 'SELECT bbcode_id
				FROM ' . BBCODES_TABLE . "
				WHERE LOWER(bbcode_tag) = '" . $this->db->sql_escape(strtolower($bbcode_name)) . "'
				   OR LOWER(bbcode_tag) = '" . $this->db->sql_escape(strtolower($bbcode_tag)) . "'";

		$result = $this->db->sql_query($sql);
		$bbcode_id = $this->db->sql_fetchfield('bbcode_id');
		$this->db->sql_freeresult($result);

		return $bbcode_id;
	}

	/**
	 * Update existing bbcode
	 *
	 * @param int $bbcode_id bbcode identifier
	 * @param array $bbcode_data bbcode data
	 * @access protected
	 */
	protected function update_bbcode(int $bbcode_id, array $bbcode_data): void
	{
		$sql = 'UPDATE ' . BBCODES_TABLE . '
				SET ' . $this->db->sql_build_array('UPDATE', $bbcode_data) . '
				WHERE bbcode_id = ' . (int) $bbcode_id;

		$this->db->sql_query($sql);
	}

	/**
	 * Add new bbcode
	 *
	 * @param array $bbcode_data New bbcode data
	 * @access protected
	 */
	protected function add_bbcode(array $bbcode_data): void
	{
		$bbcode_id = max($this->get_max_bbcode_id(), NUM_CORE_BBCODES) + 1;

		if ($bbcode_id <= BBCODE_LIMIT)
		{
			$bbcode_data['bbcode_id'] = (int) $bbcode_id;

			// set default when not in data array
			$bbcode_data['display_on_posting'] ??= 1;
			$bbcode_data['bbcode_helpline']	   ??= '';

			$this->db->sql_query('INSERT INTO ' . BBCODES_TABLE . ' ' . $this->db->sql_build_array('INSERT', $bbcode_data));
		}
	}

	/**
	 * Remove a bbcode
	 * Don't remove just by ID. Also match replace fields. This is to ensure we only delete BBCodes created by ABBC3,
	 * and not if, for example ABBC3 BBCodes that have been altered by the user.
	 *
	 * Note special handling for MSSQL Error: 402 The data types ntext and varchar are incompatible in the equal to operator
	 *
	 * @param string|int $bbcode_id bbcode identifier
	 * @param array $bbcode_data bbcode data
	 * @access protected
	 */
	protected function remove_bbcode($bbcode_id, array $bbcode_data): void
	{
		static $is_mssql;

		if (!isset($is_mssql))
		{
			$is_mssql = strpos($this->db->get_sql_layer(), 'mssql') === 0;
		}

		$sql = 'DELETE FROM ' . BBCODES_TABLE . '
				WHERE ' . ($is_mssql ? 'CONVERT(NVARCHAR(MAX), first_pass_match) = N' : 'first_pass_match = ') . "'" . $this->db->sql_escape($bbcode_data['first_pass_match']) . "'
					AND " . ($is_mssql ? 'CONVERT(NVARCHAR(MAX), first_pass_replace) = N' : 'first_pass_replace = ') . "'" . $this->db->sql_escape($bbcode_data['first_pass_replace']) . "'
					AND bbcode_id = " . (int) $bbcode_id;

		$this->db->sql_query($sql);
	}

	/**
	 * Retrieve the maximum value in a column from the bbcodes table
	 *
	 * @param string $column Name of the column (bbcode_id|bbcode_order)
	 * @return int The maximum value in the column
	 * @access protected
	 */
	protected function get_max_column_value(string $column): int
	{
		$sql = 'SELECT MAX(' . $this->db->sql_escape($column) . ') AS maximum
				FROM ' . BBCODES_TABLE;

		$result = $this->db->sql_query($sql);
		$maximum = $this->db->sql_fetchfield('maximum');
		$this->db->sql_freeresult($result);

		return (int) $maximum;
	}
}
