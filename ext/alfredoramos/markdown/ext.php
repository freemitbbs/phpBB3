<?php

/**
 * Markdown extension for phpBB.
 * @author Alfredo Ramos <alfredo.ramos@proton.me>
 * @copyright 2019 Alfredo Ramos
 * @license GPL-2.0-only
 */

namespace alfredoramos\markdown;

use phpbb\extension\base;

class ext extends base
{
	/**
	 * Check whether or not the extension can be enabled.
	 *
	 * @return bool
	 */
	public function is_enableable()
	{
		return phpbb_version_compare(PHPBB_VERSION, '3.3.2', '>=');
	}

	/**
	 * {@inheritdoc}
	 */
	public function enable_step($old_state)
	{
		$state = parent::enable_step($old_state);

		if (!$state)
		{
			$this->invalidate_text_formatter_cache();
		}

		return $state;
	}

	/**
	 * {@inheritdoc}
	 */
	public function disable_step($old_state)
	{
		$state = parent::disable_step($old_state);

		if (!$state)
		{
			$this->invalidate_text_formatter_cache();
		}

		return $state;
	}

	/**
	 * {@inheritdoc}
	 */
	public function purge_step($old_state)
	{
		$state = parent::purge_step($old_state);

		if (!$state)
		{
			$this->invalidate_text_formatter_cache();
		}

		return $state;
	}

	protected function invalidate_text_formatter_cache()
	{
		if ($this->container->has('text_formatter.s9e.factory'))
		{
			$this->container->get('text_formatter.s9e.factory')->invalidate();
		}
	}
}
