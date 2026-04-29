<?php
/**
 *
 * phpBB Media Embed PlugIn extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2016 phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbb\mediaembed\migrations;

/**
 * Migration 8: Enable Douyin in the stored media site list.
 */
class m8_add_douyin extends \phpbb\db\migration\container_aware_migration
{
	private const SITE_ID = 'douyin';
	private const CONFIG_KEY = 'media_embed_sites';

	public static function depends_on()
	{
		return ['\phpbb\mediaembed\migrations\m7_add_missing_permissions'];
	}

	public function effectively_installed()
	{
		return in_array(self::SITE_ID, $this->get_enabled_sites(), true);
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'enable_douyin_site']]],
			['custom', [[$this, 'clear_media_embed_caches']]],
		];
	}

	public function enable_douyin_site()
	{
		$sites = $this->get_enabled_sites();
		if (!in_array(self::SITE_ID, $sites, true))
		{
			$sites[] = self::SITE_ID;
			sort($sites);

			$this->container->get('config_text')->set(self::CONFIG_KEY, json_encode(array_values($sites)));
		}
	}

	public function clear_media_embed_caches()
	{
		$this->container->get('cache.driver')->destroy('_bbvideo_sites');
		$this->container->get('text_formatter.s9e.factory')->invalidate();
	}

	protected function get_enabled_sites()
	{
		$sites = json_decode((string) $this->container->get('config_text')->get(self::CONFIG_KEY), true);

		return is_array($sites) ? $sites : [];
	}
}
