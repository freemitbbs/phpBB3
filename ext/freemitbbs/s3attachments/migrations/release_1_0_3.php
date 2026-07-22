<?php

namespace freemitbbs\s3attachments\migrations;

class release_1_0_3 extends \phpbb\db\migration\container_aware_migration
{
	private const EPUB_GROUP_NAME = 'EPUB';

	public static function depends_on()
	{
		return [
			'\freemitbbs\s3attachments\migrations\release_1_0_2',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'ensure_epub_attachment_group']]],
			['config.update', ['s3attachments_version', '1.0.3']],
		];
	}

	public function ensure_epub_attachment_group(): void
	{
		$epub_group_id = $this->get_epub_group_id();
		if ($epub_group_id === 0)
		{
			$epub_group_id = $this->create_epub_group();
		}
		else
		{
			$this->enable_epub_group($epub_group_id);
		}

		$this->assign_epub_extension($epub_group_id);

		if ($this->container !== null && $this->container->has('cache'))
		{
			$this->container->get('cache')->destroy('_extensions');
		}
	}

	private function get_epub_group_id(): int
	{
		$sql = 'SELECT group_id
			FROM ' . EXTENSION_GROUPS_TABLE . "
			WHERE LOWER(group_name) = '" . $this->db->sql_escape(utf8_strtolower(self::EPUB_GROUP_NAME)) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$group_id = (int) $this->db->sql_fetchfield('group_id');
		$this->db->sql_freeresult($result);

		return $group_id;
	}

	private function create_epub_group(): int
	{
		$sql_ary = [
			'group_name' => self::EPUB_GROUP_NAME,
			'cat_id' => ATTACHMENT_CATEGORY_NONE,
			'allow_group' => 1,
			'download_mode' => INLINE_LINK,
			'upload_icon' => '',
			'max_filesize' => 0,
			'allowed_forums' => '',
			'allow_in_pm' => 1,
		];

		$this->db->sql_query('INSERT INTO ' . EXTENSION_GROUPS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));

		return (int) $this->db->sql_nextid();
	}

	private function enable_epub_group(int $group_id): void
	{
		$sql_ary = [
			'cat_id' => ATTACHMENT_CATEGORY_NONE,
			'allow_group' => 1,
			'allow_in_pm' => 1,
			'download_mode' => INLINE_LINK,
		];

		$sql = 'UPDATE ' . EXTENSION_GROUPS_TABLE . '
			SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
			WHERE group_id = ' . $group_id;
		$this->db->sql_query($sql);
	}

	private function assign_epub_extension(int $group_id): void
	{
		$sql = 'SELECT extension_id
			FROM ' . EXTENSIONS_TABLE . "
			WHERE extension = 'epub'";
		$result = $this->db->sql_query_limit($sql, 1);
		$extension_id = (int) $this->db->sql_fetchfield('extension_id');
		$this->db->sql_freeresult($result);

		if ($extension_id === 0)
		{
			$sql_ary = [
				'group_id' => $group_id,
				'extension' => 'epub',
			];
			$this->db->sql_query('INSERT INTO ' . EXTENSIONS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));

			return;
		}

		$sql = 'UPDATE ' . EXTENSIONS_TABLE . '
			SET group_id = ' . $group_id . "
			WHERE extension = 'epub'";
		$this->db->sql_query($sql);
	}
}
