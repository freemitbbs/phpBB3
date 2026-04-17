<?php
/**
 * MathJax3
 * An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2025, Thorsten Ahlers
 * @copyright (c) 2018, Kailey
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace kinerity\mathjax\migrations\v12x;

class v_1_2_0_update_bbcode extends \phpbb\db\migration\migration
{
	protected $bbcodes_installer;

	protected $bbcode_data = [
			'LaTeX' => [
				'bbcode_helpline'	 => 'Shows the LaTeX formula inline.',
				'bbcode_match'		 => '[latex #ignoreTags=true]{TEXT}[/latex]',
				'bbcode_tpl'		 => '<span>\({TEXT}\)</span>',
				'display_on_posting' => 1,
			],
			'LaTeXinline' => [
				'bbcode_helpline'	 => 'Shows the LaTeX formula inline.',
				'bbcode_match'		 => '[latex_inline #ignoreTags=true]{TEXT}[/latex_inline]',
				'bbcode_tpl'		 => '<span>\({TEXT}\)</span>',
				'display_on_posting' => 0,
			],
			'LaTeXparagraph' => [
				'bbcode_helpline'	 => 'Inserting the LaTeX formula centered in a separate paragraph.',
				'bbcode_match'		 => '[latex_para #ignoreTags=true]{TEXT}[/latex_para]',
				'bbcode_tpl'		 => '<span>\[{TEXT}\]</span>',
				'display_on_posting' => 1,
			],
		];

	public static function depends_on(): array
	{
		return ['\kinerity\mathjax\migrations\v10x\release_0_0_1',];
	}

	public function update_data(): array
	{
		return [
			['custom', [[$this, 'update_bbcodes_table']]],
		];
	}

	public function revert_data(): array
	{
		return [
			['custom', [[$this, 'delete_bbcodes']]],
		];
	}

	public function update_bbcodes_table(): void
	{
		if (!isset($this->bbcodes_installer))
		{
			$this->bbcodes_installer = new \kinerity\mathjax\core\bbcodes_installer($this->db, $this->phpbb_root_path, $this->php_ext);
		}

		$this->bbcodes_installer->install_bbcodes($this->bbcode_data);
	}

	public function delete_bbcodes(): void
	{
		if (!isset($this->bbcodes_installer))
		{
			$this->bbcodes_installer = new \kinerity\mathjax\core\bbcodes_installer($this->db, $this->phpbb_root_path, $this->php_ext);
		}

		$this->bbcodes_installer->delete_bbcodes($this->bbcode_data);
	}
}
