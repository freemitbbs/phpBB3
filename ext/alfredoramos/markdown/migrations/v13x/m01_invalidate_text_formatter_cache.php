<?php

/**
 * Markdown extension for phpBB.
 * @author Alfredo Ramos <alfredo.ramos@proton.me>
 * @copyright 2019 Alfredo Ramos
 * @license GPL-2.0-only
 */

namespace alfredoramos\markdown\migrations\v13x;

use phpbb\db\migration\container_aware_migration;

class m01_invalidate_text_formatter_cache extends container_aware_migration
{
	public static function depends_on()
	{
		return ['\alfredoramos\markdown\migrations\v13x\m00_post_configuration'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'invalidate_text_formatter_cache']]],
		];
	}

	public function invalidate_text_formatter_cache()
	{
		$this->container->get('text_formatter.s9e.factory')->invalidate();
	}
}
