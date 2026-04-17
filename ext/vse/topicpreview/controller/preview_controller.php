<?php
/**
 *
 * Topic Preview
 *
 * @copyright (c) 2013 Matt Friedman
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace vse\topicpreview\controller;

use phpbb\auth\auth;
use phpbb\content_visibility;
use Symfony\Component\HttpFoundation\Response;
use vse\topicpreview\core\data;
use vse\topicpreview\core\display;

class preview_controller
{
	/** @var auth */
	protected $auth;

	/** @var content_visibility */
	protected $content_visibility;

	/** @var data */
	protected $preview_data;

	/** @var display */
	protected $preview_display;

	/**
	 * Constructor.
	 *
	 * @param auth               $auth
	 * @param content_visibility $content_visibility
	 * @param data               $preview_data
	 * @param display            $preview_display
	 */
	public function __construct(auth $auth, content_visibility $content_visibility, data $preview_data, display $preview_display)
	{
		$this->auth = $auth;
		$this->content_visibility = $content_visibility;
		$this->preview_data = $preview_data;
		$this->preview_display = $preview_display;
	}

	/**
	 * Render a topic preview on demand.
	 *
	 * @param int $topic_id
	 *
	 * @return Response
	 */
	public function show($topic_id)
	{
		$row = $this->preview_data->get_topic_preview_row((int) $topic_id);
		if (!$row)
		{
			return $this->empty_response(404);
		}

		$forum_id = (int) $row['forum_id'];
		if (!$this->auth->acl_get('f_read', $forum_id) || !$this->content_visibility->is_visible('topic', $forum_id, $row))
		{
			return $this->empty_response(404);
		}

		$attachments = $this->preview_data->get_attachments_for_topics([$row]);
		$this->preview_display->set_attachments_cache($attachments);

		$html = $this->preview_display->render_topic_preview_html($row);
		if ($html === '')
		{
			return $this->empty_response(204);
		}

		return new Response($html, 200, [
			'Content-Type' => 'text/html; charset=UTF-8',
			'Cache-Control' => 'private, no-store, max-age=0',
		]);
	}

	/**
	 * Build an empty response for missing/inaccessible previews.
	 *
	 * @param int $status
	 *
	 * @return Response
	 */
	protected function empty_response($status)
	{
		return new Response('', (int) $status, [
			'Content-Type' => 'text/html; charset=UTF-8',
			'Cache-Control' => 'private, no-store, max-age=0',
		]);
	}
}
