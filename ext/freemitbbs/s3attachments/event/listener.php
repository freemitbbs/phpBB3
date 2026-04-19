<?php

namespace freemitbbs\s3attachments\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	protected \phpbb\language\language $language;
	protected \phpbb\db\driver\driver_interface $db;
	protected \freemitbbs\s3attachments\service\attachment_storage $attachment_storage;

	public function __construct(
		\phpbb\language\language $language,
		\phpbb\db\driver\driver_interface $db,
		\freemitbbs\s3attachments\service\attachment_storage $attachment_storage
	) {
		$this->language = $language;
		$this->db = $db;
		$this->attachment_storage = $attachment_storage;
	}

	public static function getSubscribedEvents(): array
	{
		return [
			'core.user_setup' => 'load_language',
			'core.acp_attachments_config_edit_add' => 'add_attachment_settings',
			'core.modify_attachment_data_on_upload' => 'upload_attachments',
			'core.download_file_send_to_browser_before' => 'redirect_remote_download',
			'core.delete_attachments_before' => 'remember_remote_objects',
			'core.delete_attachments_from_filesystem_after' => 'delete_remote_objects',
		];
	}

	public function load_language($event): void
	{
		$this->language->add_lang('common', 'freemitbbs/s3attachments');
	}

	public function add_attachment_settings($event): void
	{
		$display_vars = $event['display_vars'];
		$display_vars['vars']['legend3'] = 'S3ATTACHMENTS_SETTINGS';
		$display_vars['vars']['s3attachments_enabled'] = [
			'lang' => 'S3ATTACHMENTS_ENABLED',
			'validate' => 'bool',
			'type' => 'radio:yes_no',
			'explain' => true,
		];
		$display_vars['vars']['s3attachments_path_prefix'] = [
			'lang' => 'S3ATTACHMENTS_PATH_PREFIX',
			'validate' => 'string',
			'type' => 'text:25:255',
			'explain' => true,
		];
		$display_vars['vars']['s3attachments_signed_urls'] = [
			'lang' => 'S3ATTACHMENTS_SIGNED_URLS',
			'validate' => 'bool',
			'type' => 'radio:yes_no',
			'explain' => true,
		];
		$display_vars['vars']['s3attachments_signed_url_ttl'] = [
			'lang' => 'S3ATTACHMENTS_SIGNED_URL_TTL',
			'validate' => 'int:60:604800',
			'type' => 'number:60:604800',
			'explain' => true,
		];
		$display_vars['vars']['s3attachments_acl'] = [
			'lang' => 'S3ATTACHMENTS_ACL',
			'validate' => 'string',
			'type' => 'text:25:255',
			'explain' => true,
		];

		$event['display_vars'] = $display_vars;
	}

	public function upload_attachments($event): void
	{
		$this->attachment_storage->upload_attachments($this->extract_attachment_ids($event['attachment_data']));
	}

	public function redirect_remote_download($event): void
	{
		$attachment = $event['attachment'];
		$display_cat = (int) $event['display_cat'];
		$mode = (string) $event['mode'];
		$thumbnail = (bool) $event['thumbnail'];

		$url = $this->attachment_storage->build_download_url($attachment, $display_cat, $mode, $thumbnail);
		if ($url === null)
		{
			return;
		}

		if (!$thumbnail && $display_cat === ATTACHMENT_CATEGORY_NONE && empty($attachment['is_orphan']) && !phpbb_http_byte_range((int) $attachment['filesize']))
		{
			phpbb_increment_downloads($this->db, (int) $attachment['attach_id']);
		}

		file_gc(false);
		redirect($url, false, true);
		exit;
	}

	public function remember_remote_objects($event): void
	{
		$this->attachment_storage->remember_delete_object_keys(
			(string) $event['mode'],
			$event['ids'],
			(string) $event['sql_id']
		);
	}

	public function delete_remote_objects($event): void
	{
		$this->attachment_storage->delete_objects($event['physical']);
	}

	protected function extract_attachment_ids(array $attachment_data): array
	{
		$attachment_ids = [];
		foreach ($attachment_data as $attachment)
		{
			if (!isset($attachment['attach_id']))
			{
				continue;
			}

			$attachment_ids[] = (int) $attachment['attach_id'];
		}

		return $attachment_ids;
	}
}
