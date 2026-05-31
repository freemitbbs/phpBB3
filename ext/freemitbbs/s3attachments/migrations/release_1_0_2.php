<?php

namespace freemitbbs\s3attachments\migrations;

class release_1_0_2 extends \phpbb\db\migration\container_aware_migration
{
	private const PDF_GROUP_NAME = 'PDF';

	public static function depends_on()
	{
		return [
			'\freemitbbs\s3attachments\migrations\release_1_0_1',
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'ensure_pdf_attachment_group']]],
			['config.update', ['s3attachments_version', '1.0.2']],
		];
	}

	public function ensure_pdf_attachment_group(): void
	{
		$pdf_group_id = $this->get_pdf_group_id();
		if ($pdf_group_id === 0)
		{
			$pdf_group_id = $this->create_pdf_group();
		}
		else
		{
			$this->enable_pdf_group($pdf_group_id);
		}

		$this->assign_pdf_extension($pdf_group_id);

		if ($this->container !== null && $this->container->has('cache'))
		{
			$this->container->get('cache')->destroy('_extensions');
		}
	}

	private function get_pdf_group_id(): int
	{
		$sql = 'SELECT group_id
			FROM ' . EXTENSION_GROUPS_TABLE . "
			WHERE LOWER(group_name) = '" . $this->db->sql_escape(utf8_strtolower(self::PDF_GROUP_NAME)) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$group_id = (int) $this->db->sql_fetchfield('group_id');
		$this->db->sql_freeresult($result);

		return $group_id;
	}

	private function create_pdf_group(): int
	{
		$sql_ary = [
			'group_name' => self::PDF_GROUP_NAME,
			'cat_id' => ATTACHMENT_CATEGORY_NONE,
			'allow_group' => 1,
			'download_mode' => INLINE_LINK,
			'upload_icon' => '',
			'max_filesize' => 0,
			'allowed_forums' => '',
			'allow_in_pm' => 0,
		];

		$this->db->sql_query('INSERT INTO ' . EXTENSION_GROUPS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));

		return (int) $this->db->sql_nextid();
	}

	private function enable_pdf_group(int $group_id): void
	{
		$sql_ary = [
			'cat_id' => ATTACHMENT_CATEGORY_NONE,
			'allow_group' => 1,
			'download_mode' => INLINE_LINK,
		];

		$sql = 'UPDATE ' . EXTENSION_GROUPS_TABLE . '
			SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
			WHERE group_id = ' . $group_id;
		$this->db->sql_query($sql);
	}

	private function assign_pdf_extension(int $group_id): void
	{
		$sql = 'SELECT extension_id
			FROM ' . EXTENSIONS_TABLE . "
			WHERE extension = 'pdf'";
		$result = $this->db->sql_query_limit($sql, 1);
		$extension_id = (int) $this->db->sql_fetchfield('extension_id');
		$this->db->sql_freeresult($result);

		if ($extension_id === 0)
		{
			$sql_ary = [
				'group_id' => $group_id,
				'extension' => 'pdf',
			];
			$this->db->sql_query('INSERT INTO ' . EXTENSIONS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));

			return;
		}

		$sql = 'UPDATE ' . EXTENSIONS_TABLE . '
			SET group_id = ' . $group_id . "
			WHERE extension = 'pdf'";
		$this->db->sql_query($sql);
	}
}
