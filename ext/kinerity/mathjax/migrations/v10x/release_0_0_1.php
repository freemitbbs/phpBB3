<?php
/**
 *
 * MathJax extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, kinerity, https://www.layer-3.org
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace kinerity\mathjax\migrations\v10x;

class release_0_0_1 extends \phpbb\db\migration\migration
{
	protected $bbcodes_installer;

	protected $bbcode_data = [
			'latex' => [
				//display_on_posting is automatically set to true, so don't modify it
				'bbcode_match'			=> '[latex]{TEXT}[/latex]',
				'bbcode_tpl'			=> '<span>\({TEXT}\)</span>',
			],
		];

	/**
	 * Assign migration file dependencies for this migration
	 *
	 * @return array
	 * @access public
	 */
	public static function depends_on(): array
	{
		return ['\phpbb\db\migration\data\v320\v320'];
	}

	/**
	 * Add or update data in the database
	 *
	 * @return array
	 * @access public
	 */
	public function update_data(): array
	{
		return [
			['custom', [[$this, 'install_bbcodes']]],
		];
	}

	/**
	 * Add the LaTeX BBCode to the database
	 *
	 * @return void
	 * @access public
	 */
	public function install_bbcodes(): void
	{
		if (!isset($this->bbcodes_installer))
		{
			$this->bbcodes_installer = new \kinerity\mathjax\core\bbcodes_installer($this->db, $this->phpbb_root_path, $this->php_ext);
		}

		$this->bbcodes_installer->install_bbcodes($this->bbcode_data);
	}
}
