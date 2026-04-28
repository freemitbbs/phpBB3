<?php
/**
*
* @package Announcements on index
* @copyright (c) 2015 david63
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace david63\announceonindex\migrations;

class version_2_0_0 extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\david63\announceonindex\migrations\version_1_0_0');
	}

	public function update_data()
	{
		return array(
			array('config.remove', array('announce_on_index_enable')),
			array('config.add', array('version_globalonindex', '1.0.0')),

		);
	}
}
