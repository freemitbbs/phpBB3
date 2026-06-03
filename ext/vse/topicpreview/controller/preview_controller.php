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
use phpbb\cache\driver\driver_interface as cache_driver;
use phpbb\content_visibility;
use phpbb\request\request_interface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use vse\topicpreview\core\data;
use vse\topicpreview\core\display;

class preview_controller
{
	protected const BATCH_LIMIT = 50;
	protected const CACHE_TTL = 86400;

	/** @var auth */
	protected $auth;

	/** @var cache_driver */
	protected $cache;

	/** @var content_visibility */
	protected $content_visibility;

	/** @var request_interface */
	protected $request;

	/** @var data */
	protected $preview_data;

	/** @var display */
	protected $preview_display;

	/**
	 * Constructor.
	 *
	 * @param auth               $auth
	 * @param cache_driver       $cache
	 * @param content_visibility $content_visibility
	 * @param request_interface  $request
	 * @param data               $preview_data
	 * @param display            $preview_display
	 */
	public function __construct(auth $auth, cache_driver $cache, content_visibility $content_visibility, request_interface $request, data $preview_data, display $preview_display)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->content_visibility = $content_visibility;
		$this->request = $request;
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

		$status = $this->get_inaccessible_status($row);
		if ($status)
		{
			return $this->empty_response($status);
		}

		$limit_multiplier = $this->get_limit_multiplier();
		$cache_key = $this->get_preview_cache_key($row, $limit_multiplier);
		$cached = $this->get_cached_preview($cache_key);
		if ($cached !== null)
		{
			return $this->html_response($cached['html'], $cached['status']);
		}

		$attachments = $this->preview_data->get_attachments_for_topics([$row]);
		$this->preview_display->set_attachments_cache($attachments);
		$result = $this->render_and_cache_preview($row, $limit_multiplier, $cache_key);

		return $this->html_response($result['html'], $result['status']);
	}

	/**
	 * Render multiple topic previews on demand.
	 *
	 * @return JsonResponse
	 */
	public function batch()
	{
		$topic_ids = $this->get_batch_topic_ids();
		if (empty($topic_ids))
		{
			return $this->json_response([]);
		}

		$limit_multiplier = $this->get_limit_multiplier();
		$rows = $this->preview_data->get_topic_preview_rows($topic_ids);
		$results = [];
		$render_rows = [];

		foreach ($topic_ids as $topic_id)
		{
			if (empty($rows[$topic_id]))
			{
				$results[$topic_id] = [
					'html' => '',
					'status' => 404,
				];
				continue;
			}

			$row = $rows[$topic_id];
			$status = $this->get_inaccessible_status($row);
			if ($status)
			{
				$results[$topic_id] = [
					'html' => '',
					'status' => $status,
				];
				continue;
			}

			$cache_key = $this->get_preview_cache_key($row, $limit_multiplier);
			$cached = $this->get_cached_preview($cache_key);
			if ($cached !== null)
			{
				$results[$topic_id] = $cached;
				continue;
			}

			$render_rows[$topic_id] = [
				'row' => $row,
				'cache_key' => $cache_key,
			];
		}

		if (!empty($render_rows))
		{
			$attachments = $this->preview_data->get_attachments_for_topics(array_column($render_rows, 'row'));
			$this->preview_display->set_attachments_cache($attachments);

			foreach ($render_rows as $topic_id => $render_row)
			{
				$results[$topic_id] = $this->render_and_cache_preview($render_row['row'], $limit_multiplier, $render_row['cache_key']);
			}
		}

		$ordered_results = [];
		foreach ($topic_ids as $topic_id)
		{
			$ordered_results[(string) $topic_id] = $results[$topic_id] ?? [
				'html' => '',
				'status' => 404,
			];
		}

		return $this->json_response($ordered_results);
	}

	/**
	 * Get topic IDs requested by the batch endpoint.
	 *
	 * @return array
	 */
	protected function get_batch_topic_ids()
	{
		$topic_ids = [];
		$topic_ids_text = $this->request->variable('topic_ids', '');

		foreach (preg_split('/[,\s]+/', $topic_ids_text, -1, PREG_SPLIT_NO_EMPTY) as $topic_id)
		{
			$topic_id = (int) $topic_id;
			if ($topic_id > 0)
			{
				$topic_ids[$topic_id] = $topic_id;
			}

			if (count($topic_ids) >= self::BATCH_LIMIT)
			{
				break;
			}
		}

		return array_values($topic_ids);
	}

	/**
	 * Get the preview text length multiplier for this request.
	 *
	 * @return int
	 */
	protected function get_limit_multiplier()
	{
		return $this->request->variable('toptopics_inline', 0) ? 2 : 1;
	}

	/**
	 * Get the status for an inaccessible row, or 0 when visible.
	 *
	 * @param array $row Topic row data
	 *
	 * @return int
	 */
	protected function get_inaccessible_status($row)
	{
		$forum_id = (int) $row['forum_id'];

		return (!$this->auth->acl_get('f_read', $forum_id) || !$this->content_visibility->is_visible('topic', $forum_id, $row)) ? 404 : 0;
	}

	/**
	 * Get the rendered preview cache key for a topic row.
	 *
	 * @param array $row
	 * @param int   $limit_multiplier
	 *
	 * @return string
	 */
	protected function get_preview_cache_key($row, $limit_multiplier)
	{
		$forum_id = (int) $row['forum_id'];

		return $this->preview_display->get_topic_preview_cache_key($row, $limit_multiplier, $this->get_permission_vary($forum_id));
	}

	/**
	 * Get a cached preview result.
	 *
	 * @param string $cache_key
	 *
	 * @return array|null
	 */
	protected function get_cached_preview($cache_key)
	{
		$cached = $this->cache->get($cache_key);

		return is_array($cached) && isset($cached['html'], $cached['status'])
			? [
				'html' => (string) $cached['html'],
				'status' => (int) $cached['status'],
			]
			: null;
	}

	/**
	 * Render and cache one preview result.
	 *
	 * @param array  $row
	 * @param int    $limit_multiplier
	 * @param string $cache_key
	 *
	 * @return array
	 */
	protected function render_and_cache_preview($row, $limit_multiplier, $cache_key)
	{
		$html = $this->preview_display->render_topic_preview_html($row, $limit_multiplier);
		$status = $html === '' ? 204 : 200;
		$this->cache_preview($cache_key, $html, $status);

		return [
			'html' => $html,
			'status' => $status,
		];
	}

	/**
	 * Cache a rendered preview response.
	 *
	 * @param string $cache_key
	 * @param string $html
	 * @param int    $status
	 *
	 * @return void
	 */
	protected function cache_preview($cache_key, $html, $status)
	{
		$this->cache->put($cache_key, [
			'html' => (string) $html,
			'status' => (int) $status,
		], self::CACHE_TTL);
	}

	/**
	 * Build a small permission-sensitive cache vary segment.
	 *
	 * @param int $forum_id
	 *
	 * @return string
	 */
	protected function get_permission_vary($forum_id)
	{
		return implode(':', [
			'f_download=' . (int) $this->auth->acl_get('f_download', $forum_id),
			'u_download=' . (int) $this->auth->acl_get('u_download'),
		]);
	}

	/**
	 * Build a preview HTML response.
	 *
	 * @param string $html
	 * @param int    $status
	 *
	 * @return Response
	 */
	protected function html_response($html, $status)
	{
		return new Response($html, (int) $status, [
			'Content-Type' => 'text/html; charset=UTF-8',
			'Cache-Control' => 'private, no-store, max-age=0',
		]);
	}

	/**
	 * Build a preview JSON response.
	 *
	 * @param array $data
	 *
	 * @return JsonResponse
	 */
	protected function json_response($data)
	{
		return new JsonResponse($data, 200, [
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
		return $this->html_response('', $status);
	}
}
