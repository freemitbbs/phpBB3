<?php

namespace freemitbbs\posttags\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	protected \phpbb\controller\helper $helper;
	protected \phpbb\language\language $language;
	protected \phpbb\request\request_interface $request;
	protected \freemitbbs\posttags\service\manager $manager;
	protected array $viewtopic_tags = [];

	public function __construct(
		\phpbb\controller\helper $helper,
		\phpbb\language\language $language,
		\phpbb\request\request_interface $request,
		\freemitbbs\posttags\service\manager $manager
	)
	{
		$this->helper = $helper;
		$this->language = $language;
		$this->request = $request;
		$this->manager = $manager;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language',
			'core.posting_modify_template_vars' => 'add_posting_template_vars',
			'core.viewtopic_modify_quick_reply_template_vars' => 'add_quick_reply_template_vars',
			'core.submit_post_end' => 'save_submitted_tags',
			'core.viewtopic_modify_post_data' => 'prefetch_viewtopic_tags',
			'core.viewtopic_modify_post_row' => 'add_post_row_tags',
			'core.delete_posts_after' => 'delete_post_tags',
		];
	}

	public function load_language($event): void
	{
		$this->language->add_lang('common', 'freemitbbs/posttags');
	}

	public function add_posting_template_vars($event): void
	{
		$mode = (string) $event['mode'];
		if (!in_array($mode, ['post', 'reply', 'quote', 'edit'], true))
		{
			return;
		}

		$post_id = (int) $event['post_id'];
		$raw_tags = $this->submitted_tags_raw();
		if ($raw_tags === null && $mode === 'edit' && $post_id > 0)
		{
			$raw_tags = $this->manager->tags_to_raw($this->manager->get_post_tags($post_id));
		}

		$page_data = $event['page_data'];
		$page_data = array_merge($page_data, $this->editor_template_vars((string) $raw_tags));
		$event['page_data'] = $page_data;
	}

	public function add_quick_reply_template_vars($event): void
	{
		$tpl_ary = $event['tpl_ary'];
		$tpl_ary = array_merge($tpl_ary, $this->editor_template_vars(''));
		$event['tpl_ary'] = $tpl_ary;
	}

	public function save_submitted_tags($event): void
	{
		$data = $event['data'];
		$post_id = (int) ($data['post_id'] ?? 0);
		if ($post_id <= 0)
		{
			return;
		}

		$raw_tags = (string) $this->request->variable('posttags_tags', '', true);
		$tags = $this->manager->clean_tags_from_string($raw_tags);
		$this->manager->set_post_tags($post_id, $tags);
	}

	public function prefetch_viewtopic_tags($event): void
	{
		$this->viewtopic_tags = $this->manager->get_tags_for_posts((array) $event['post_list']);
	}

	public function add_post_row_tags($event): void
	{
		$post_id = (int) $event['row']['post_id'];
		$tags = $this->viewtopic_tags[$post_id] ?? [];
		if (empty($tags))
		{
			return;
		}

		$posttags = [];
		foreach ($tags as $tag)
		{
			$name = (string) $tag['tag_name'];
			$posttags[] = [
				'NAME' => $name,
				'U_SEARCH' => $this->helper->route('freemitbbs_posttags_search', ['tag' => $name]),
			];
		}

		$post_row = $event['post_row'];
		$post_row['S_POSTTAGS_HAS_TAGS'] = true;
		$post_row['posttags'] = $posttags;
		$event['post_row'] = $post_row;
	}

	public function delete_post_tags($event): void
	{
		$this->manager->delete_post_tags((array) $event['post_ids']);
	}

	protected function submitted_tags_raw(): ?string
	{
		if (!$this->request->is_set_post('posttags_tags'))
		{
			return null;
		}

		return (string) $this->request->variable('posttags_tags', '', true);
	}

	protected function editor_template_vars(string $raw_tags): array
	{
		return [
			'S_POSTTAGS_EDITOR_AVAILABLE' => true,
			'POSTTAGS_TAGS_RAW' => $this->manager->tags_to_raw($this->manager->clean_tags_from_string($raw_tags)),
			'POSTTAGS_MAX_TAGS' => \freemitbbs\posttags\service\manager::MAX_TAGS,
			'POSTTAGS_MAX_LENGTH' => \freemitbbs\posttags\service\manager::MAX_TAG_LENGTH,
		];
	}
}
