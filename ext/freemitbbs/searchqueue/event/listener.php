<?php

namespace freemitbbs\searchqueue\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	private const DEFER_MARKER = 'freemitbbs_searchqueue_defer';

	protected \freemitbbs\searchqueue\service\queue $queue;

	public function __construct(\freemitbbs\searchqueue\service\queue $queue)
	{
		$this->queue = $queue;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.modify_submit_post_data' => 'defer_native_search_index',
			'core.submit_post_end' => 'queue_submitted_post',
			'core.delete_posts_after' => 'delete_queued_posts',
		];
	}

	public function defer_native_search_index($event): void
	{
		$data = (array) $event['data'];
		if (!$this->queue->should_defer_submit_index((bool) $event['update_search_index'], $data))
		{
			return;
		}

		$data[self::DEFER_MARKER] = true;
		$event['data'] = $data;
		$event['update_search_index'] = false;
	}

	public function queue_submitted_post($event): void
	{
		$data = (array) $event['data'];
		if (empty($data[self::DEFER_MARKER]))
		{
			return;
		}

		$post_id = (int) ($data['post_id'] ?? 0);
		if ($post_id > 0)
		{
			$this->queue->queue_post($post_id);
		}
	}

	public function delete_queued_posts($event): void
	{
		$this->queue->delete_queued_posts((array) $event['post_ids']);
	}
}
