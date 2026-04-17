<?php
/**
 *
 * Topic Preview
 *
 * @copyright (c) 2013 Matt Friedman
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace vse\topicpreview\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use vse\topicpreview\core\data;
use vse\topicpreview\core\display;

/**
 * Event listener
 */
class listener implements EventSubscriberInterface
{
	/** @var data */
	protected $preview_data;

	/** @var display */
	protected $preview_display;

	/**
	 * Constructor
	 *
	 * @param data    $preview_data    Topic Preview data object
	 * @param display $preview_display Topic Preview display object
	 */
	public function __construct(data $preview_data, display $preview_display)
	{
		$this->preview_data = $preview_data;
		$this->preview_display = $preview_display;
	}

	/**
	 * Assign functions defined in this class to event listeners in the core
	 *
	 * @return array
	 */
	public static function getSubscribedEvents()
	{
		return array(
			'core.viewforum_modify_topicrow'			=> 'display_topic_previews',
			'core.search_modify_tpl_ary'				=> 'display_topic_previews',
			'vse.similartopics.modify_topicrow'			=> 'display_topic_previews',
			'paybas.recenttopics.modify_tpl_ary'		=> 'display_topic_previews',
			'imcger.recenttopicsng.modify_tpl_ary'		=> 'display_topic_previews',
			'rmcgirr83.topfive.modify_tpl_ary'			=> 'display_topic_previews',
		);
	}

	/**
	 * Modify template vars to display topic previews
	 *
	 * @param \phpbb\event\data $event The event object
	 */
	public function display_topic_previews($event)
	{
		$block = $event['topic_row'] ? 'topic_row' : 'tpl_ary';
		$event[$block] = $this->preview_display->display_topic_preview($event['row'], $event[$block]);
	}
}
