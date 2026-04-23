<?php
/**
 *
 * Recent Topics NG. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, IMC, https://github.com/IMC-GER / LukeWCS, https://github.com/LukeWCS
 * @copyright (c) 2017, Sajaki, https://www.avathar.be
 * @copyright (c) 2015, PayBas
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 * Based on the original NV Recent Topics by Joas Schilling (nickvergessen)
 */

namespace imcger\recenttopicsng\migrations;

class v_1_1_1 extends \phpbb\db\migration\migration
{
	public function effectively_installed(): bool
	{
		return isset($this->config['rtng_excluded_forum_ids']);
	}

	public static function depends_on(): array
	{
		return ['\imcger\recenttopicsng\migrations\v_1_1_0'];
	}

	public function update_data(): array
	{
		return [
			['config.add', ['rtng_excluded_forum_ids', '']],
		];
	}
}
