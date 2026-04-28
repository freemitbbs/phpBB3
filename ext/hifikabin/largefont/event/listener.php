<?php
/**
 *
 * @package Large Font
 * @copyright (c) 2014 HiFiKabin
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace hifikabin\largefont\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event listener
 */
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/**
	 * @var array $size_map
	 *
	 * maps the internally-used legacy values back to
	 * the original values specified by the user
	 */
	protected $size_map;

	public function __construct(\phpbb\template\template $template, \phpbb\user $user)
	{
		$this->template = $template;
		$this->user = $user;

		$this->size_map = array(
			101 => 105,
			102 => 110,
			103 => 120,
			104 => 130,
			106 => 150,
			110 => 200,
			114 => 300,
			120 => 400,
		);
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.page_header' => 'main',
		);
	}

	public function main($event)
	{
		$this->template->assign_vars(array(
			'S_USER_FONT'				=> $this->user->data['user_font'],
			'S_USER_FONT_BOLD'			=> $this->user->data['user_font_bold'],
			'S_USER_FONT_SIZE'			=> $this->user->data['user_font_size'],
			'S_USER_MOBILE_FONT'		=> $this->user->data['user_mobile_font'],
			'S_USER_MOBILE_FONT_SIZE'	=> $this->user->data['user_mobile_font_size'],

			'S_REAL_FONT_SIZE'          => $this->size_map[$this->user->data['user_font_size']],
			'S_REAL_MOBILE_FONT_SIZE'   => $this->size_map[$this->user->data['user_mobile_font_size']],
		));
	}
}
