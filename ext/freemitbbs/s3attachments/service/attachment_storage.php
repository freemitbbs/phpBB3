<?php

namespace freemitbbs\s3attachments\service;

use Symfony\Component\DependencyInjection\ContainerInterface;

class attachment_storage
{
	protected \phpbb\config\config $config;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\filesystem\filesystem $filesystem;
	protected string $phpbb_root_path;
	protected $object_store;
	protected $shared_config_provider;
	protected array $delete_object_keys = [];

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\filesystem\filesystem $filesystem,
		ContainerInterface $container,
		string $phpbb_root_path
	) {
		$this->config = $config;
		$this->db = $db;
		$this->filesystem = $filesystem;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->object_store = $container->has('freemitbbs.s3storage.object_store')
			? $container->get('freemitbbs.s3storage.object_store')
			: null;
		$this->shared_config_provider = $container->has('freemitbbs.s3storage.config_provider')
			? $container->get('freemitbbs.s3storage.config_provider')
			: null;
	}

	public function is_ready(): bool
	{
		return (bool) ((int) ($this->config['s3attachments_enabled'] ?? 0))
			&& $this->object_store !== null
			&& $this->shared_config_provider !== null
			&& $this->shared_config_provider->has_shared_storage_config();
	}

	public function upload_attachments(array $attachment_ids): void
	{
		if (!$this->is_ready())
		{
			return;
		}

		if (!$this->has_object_key_schema())
		{
			$this->write_log('upload_skipped_schema_not_ready');
			return;
		}

		$attachment_ids = $this->normalize_attachment_ids($attachment_ids);
		if (!$attachment_ids)
		{
			return;
		}

		$sql = 'SELECT attach_id, physical_filename, real_filename, mimetype, filesize, thumbnail, s3_object_key, s3_thumb_object_key
			FROM ' . ATTACHMENTS_TABLE . '
			WHERE ' . $this->db->sql_in_set('attach_id', $attachment_ids);
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$this->upload_attachment_row($row);
		}
		$this->db->sql_freeresult($result);
	}

	public function offload_attachments(array $attachment_ids): void
	{
		$this->upload_attachments($attachment_ids);
	}

	public function build_download_url(array $attachment, int $display_cat, string $mode, bool $thumbnail): ?string
	{
		if (!$this->is_ready())
		{
			return null;
		}

		$physical_filename = $this->sanitize_filename((string) ($attachment['physical_filename'] ?? ''));
		if ($physical_filename === '')
		{
			return null;
		}

		$object_key = $this->get_stored_object_key((int) ($attachment['attach_id'] ?? 0), $thumbnail);
		if ($object_key === '')
		{
			if ($this->filesystem->exists($this->get_local_path($physical_filename, $thumbnail)))
			{
				return null;
			}

			$object_key = $this->build_object_key($physical_filename, $thumbnail);
		}

		if (!(bool) ((int) ($this->config['s3attachments_signed_urls'] ?? 1)))
		{
			return $this->object_store->build_public_url($this->get_storage_config(), $object_key);
		}

		$response_headers = [];
		$real_filename = html_entity_decode((string) ($attachment['real_filename'] ?? ''), ENT_COMPAT);
		$mimetype = (string) ($attachment['mimetype'] ?? 'application/octet-stream');
		$is_image = $thumbnail || ($display_cat === ATTACHMENT_CATEGORY_IMAGE && strpos($mimetype, 'image') === 0 && $mode === 'view');

		$response_headers['response-content-type'] = $is_image ? $mimetype : 'application/octet-stream';
		if ($real_filename !== '')
		{
			if (!function_exists('header_filename'))
			{
				require_once $this->phpbb_root_path . 'includes/functions_download.php';
			}

			$response_headers['response-content-disposition'] = ($is_image ? 'inline; ' : 'attachment; ') . \header_filename($real_filename);
		}

		return $this->object_store->create_presigned_get_url(
			$this->get_storage_config(),
			$object_key,
			(int) ($this->config['s3attachments_signed_url_ttl'] ?? 300),
			$response_headers
		);
	}

	public function clone_attachment_storage(array $source_attachment, string $target_physical_filename): array
	{
		if (!$this->is_ready() || !$this->has_object_key_schema())
		{
			return [];
		}

		$source_physical_filename = $this->sanitize_filename((string) ($source_attachment['physical_filename'] ?? ''));
		$target_physical_filename = $this->sanitize_filename($target_physical_filename);
		if ($source_physical_filename === '' || $target_physical_filename === '')
		{
			return [];
		}

		$source_object_key = $this->attachment_object_key($source_attachment, false);
		if ($source_object_key === '')
		{
			if ($this->local_file_is_usable($source_physical_filename, false))
			{
				return [];
			}

			$source_object_key = $this->build_object_key($source_physical_filename, false);
		}

		$target_object_key = $this->build_object_key($target_physical_filename, false);
		$target_thumb_object_key = '';

		try
		{
			$this->clone_object_to_key(
				$source_object_key,
				$target_object_key,
				(string) ($source_attachment['mimetype'] ?: 'application/octet-stream')
			);
			$this->write_local_marker($target_physical_filename, false);

			if ((int) ($source_attachment['thumbnail'] ?? 0))
			{
				$target_thumb_object_key = $this->build_object_key($target_physical_filename, true);
				$source_thumb_object_key = $this->attachment_object_key($source_attachment, true);
				$source_thumb_path = $this->get_local_path($source_physical_filename, true);
				$thumb_mimetype = $this->detect_thumbnail_mimetype($source_thumb_path, (string) ($source_attachment['mimetype'] ?? ''));

				if ($source_thumb_object_key !== '')
				{
					$this->clone_object_to_key($source_thumb_object_key, $target_thumb_object_key, $thumb_mimetype);
				}
				else if ($this->local_file_is_usable($source_physical_filename, true))
				{
					$this->object_store->put_object(
						$this->get_storage_config(),
						$source_thumb_path,
						(int) @filesize($source_thumb_path),
						$thumb_mimetype,
						$target_thumb_object_key
					);
				}
				else
				{
					$this->clone_object_to_key(
						$this->build_object_key($source_physical_filename, true),
						$target_thumb_object_key,
						$thumb_mimetype
					);
				}

				$this->write_local_marker($target_physical_filename, true);
			}
		}
		catch (\Exception $e)
		{
			$this->write_log('clone_failed', [
				'physical_filename' => $source_physical_filename,
				'target_physical_filename' => $target_physical_filename,
				'message' => $e->getMessage(),
			]);
			throw $e;
		}

		return [
			's3_object_key' => $target_object_key,
			's3_thumb_object_key' => $target_thumb_object_key,
		];
	}

	public function delete_objects(array $physical): void
	{
		if (!$this->is_ready() || !$physical)
		{
			return;
		}

		$processed_files = [];
		foreach ($physical as $file_ary)
		{
			$physical_filename = $this->sanitize_filename((string) ($file_ary['filename'] ?? ''));
			if ($physical_filename === '' || isset($processed_files[$physical_filename]))
			{
				continue;
			}

			if ($this->has_remaining_attachment_entries($physical_filename))
			{
				continue;
			}

			try
			{
				$object_keys = $this->delete_object_keys[$physical_filename] ?? [];
				$this->object_store->delete_object($this->get_storage_config(), $object_keys['file'] ?? $this->build_object_key($physical_filename, false));
				if (!empty($file_ary['thumbnail']))
				{
					$this->object_store->delete_object($this->get_storage_config(), $object_keys['thumbnail'] ?? $this->build_object_key($physical_filename, true));
				}
			}
			catch (\Exception $e)
			{
				$this->write_log('delete_failed', [
					'physical_filename' => $physical_filename,
					'message' => $e->getMessage(),
				]);
				continue;
			}

			$processed_files[$physical_filename] = true;
			$this->write_log('delete_success', [
				'physical_filename' => $physical_filename,
			]);
			unset($this->delete_object_keys[$physical_filename]);
		}
	}

	public function remember_delete_object_keys(string $mode, $ids, string $sql_id): void
	{
		if (!$this->is_ready() || !$this->has_object_key_schema())
		{
			return;
		}

		$ids = $this->normalize_delete_ids($ids);
		if (!$ids || !in_array($sql_id, ['attach_id', 'post_msg_id', 'topic_id', 'poster_id'], true))
		{
			return;
		}

		$sql = 'SELECT physical_filename, thumbnail, s3_object_key, s3_thumb_object_key
			FROM ' . ATTACHMENTS_TABLE . '
			WHERE ' . $this->db->sql_in_set($sql_id, $ids);

		if ($mode === 'post')
		{
			$sql .= ' AND in_message = 0';
		}
		else if ($mode === 'message')
		{
			$sql .= ' AND in_message = 1';
		}

		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$physical_filename = $this->sanitize_filename((string) $row['physical_filename']);
			if ($physical_filename === '')
			{
				continue;
			}

			$object_key = trim(str_replace('\\', '/', (string) $row['s3_object_key']), '/');
			$thumb_object_key = trim(str_replace('\\', '/', (string) $row['s3_thumb_object_key']), '/');

			if ($object_key !== '')
			{
				$this->delete_object_keys[$physical_filename]['file'] = $object_key;
			}
			if ((int) $row['thumbnail'] && $thumb_object_key !== '')
			{
				$this->delete_object_keys[$physical_filename]['thumbnail'] = $thumb_object_key;
			}
		}
		$this->db->sql_freeresult($result);
	}

	protected function upload_attachment_row(array $row): void
	{
		$attach_id = (int) $row['attach_id'];
		$physical_filename = $this->sanitize_filename((string) $row['physical_filename']);
		$object_key = (string) ($row['s3_object_key'] ?? '');
		$thumb_object_key = (string) ($row['s3_thumb_object_key'] ?? '');

		if ($physical_filename === '')
		{
			return;
		}

		try
		{
			if ($object_key === '')
			{
				$local_file = $this->get_local_path($physical_filename, false);
				if (!$this->filesystem->exists($local_file))
				{
					$this->write_log('upload_missing_local', [
						'attach_id' => $attach_id,
						'physical_filename' => $physical_filename,
						'real_filename' => (string) $row['real_filename'],
					]);
					return;
				}

				$object_key = $this->build_object_key($physical_filename, false);
				$this->object_store->put_object(
					$this->get_storage_config(),
					$local_file,
					(int) $row['filesize'],
					(string) ($row['mimetype'] ?: 'application/octet-stream'),
					$object_key
				);

				if ((int) $row['thumbnail'])
				{
					$local_thumb = $this->get_local_path($physical_filename, true);
					if ($this->filesystem->exists($local_thumb))
					{
						$thumb_object_key = $this->build_object_key($physical_filename, true);
						$this->object_store->put_object(
							$this->get_storage_config(),
							$local_thumb,
							(int) @filesize($local_thumb),
							$this->detect_thumbnail_mimetype($local_thumb, (string) $row['mimetype']),
							$thumb_object_key
						);
					}
				}

				$this->store_object_keys($attach_id, $object_key, $thumb_object_key);
				$this->write_log('upload_success', [
					'attach_id' => $attach_id,
					'physical_filename' => $physical_filename,
					'real_filename' => (string) $row['real_filename'],
					'object_key' => $object_key,
				]);
			}

			$this->replace_local_file_with_marker($physical_filename, false);
			if ((int) $row['thumbnail'] && $thumb_object_key !== '')
			{
				$this->replace_local_file_with_marker($physical_filename, true);
			}
		}
		catch (\Exception $e)
		{
			$this->write_log('upload_failed', [
				'attach_id' => $attach_id,
				'physical_filename' => $physical_filename,
				'real_filename' => (string) $row['real_filename'],
				'message' => $e->getMessage(),
			]);
		}
	}

	protected function store_object_keys(int $attach_id, string $object_key, string $thumb_object_key): void
	{
		$sql_ary = [
			's3_object_key' => $object_key,
			's3_thumb_object_key' => $thumb_object_key,
		];

		$sql = 'UPDATE ' . ATTACHMENTS_TABLE . '
			SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
			WHERE attach_id = ' . $attach_id;
		$this->db->sql_query($sql);
	}

	protected function get_stored_object_key(int $attach_id, bool $thumbnail): string
	{
		if (!$this->has_object_key_schema() || $attach_id <= 0)
		{
			return '';
		}

		$column = $thumbnail ? 's3_thumb_object_key' : 's3_object_key';
		$sql = 'SELECT ' . $column . '
			FROM ' . ATTACHMENTS_TABLE . '
			WHERE attach_id = ' . $attach_id;
		$result = $this->db->sql_query($sql);
		$object_key = (string) $this->db->sql_fetchfield($column);
		$this->db->sql_freeresult($result);

		return trim(str_replace('\\', '/', $object_key), '/');
	}

	protected function attachment_object_key(array $attachment, bool $thumbnail): string
	{
		$column = $thumbnail ? 's3_thumb_object_key' : 's3_object_key';

		return trim(str_replace('\\', '/', (string) ($attachment[$column] ?? '')), '/');
	}

	protected function clone_object_to_key(string $source_object_key, string $target_object_key, string $mimetype): void
	{
		$tmp_path = $this->download_object_to_temp_file($source_object_key);
		try
		{
			$this->object_store->put_object(
				$this->get_storage_config(),
				$tmp_path,
				(int) @filesize($tmp_path),
				$mimetype !== '' ? $mimetype : 'application/octet-stream',
				$target_object_key
			);
		}
		finally
		{
			@unlink($tmp_path);
		}
	}

	protected function download_object_to_temp_file(string $object_key): string
	{
		if (!function_exists('curl_init'))
		{
			throw new \RuntimeException('Download failed: cURL extension is not enabled.');
		}

		$tmp_path = tempnam(sys_get_temp_dir(), 'phpbb-s3-copy-');
		if ($tmp_path === false)
		{
			throw new \RuntimeException('Download failed: unable to allocate temporary file.');
		}

		$file_handle = @fopen($tmp_path, 'wb');
		if ($file_handle === false)
		{
			@unlink($tmp_path);
			throw new \RuntimeException('Download failed: unable to write temporary file.');
		}

		$url = $this->object_store->create_presigned_get_url($this->get_storage_config(), $object_key, 300);
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_FILE, $file_handle);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($curl, CURLOPT_TIMEOUT, 120);

		$response = curl_exec($curl);
		$error = $response === false ? curl_error($curl) : '';
		$status_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		fclose($file_handle);

		if ($response === false)
		{
			@unlink($tmp_path);
			throw new \RuntimeException('Download failed: ' . $error);
		}

		if ($status_code !== 200)
		{
			@unlink($tmp_path);
			throw new \RuntimeException('Download failed with HTTP status ' . $status_code);
		}

		return $tmp_path;
	}

	protected function normalize_attachment_ids(array $attachment_ids): array
	{
		$attachment_ids = array_values(array_unique(array_map('intval', $attachment_ids)));

		return array_filter($attachment_ids);
	}

	protected function normalize_delete_ids($ids): array
	{
		if (is_array($ids))
		{
			return $this->normalize_attachment_ids($ids);
		}

		return $this->normalize_attachment_ids(explode(',', (string) $ids));
	}

	protected function has_object_key_schema(): bool
	{
		return (bool) ((int) ($this->config['s3attachments_object_keys_ready'] ?? 0));
	}

	protected function get_storage_config(): array
	{
		$config = $this->shared_config_provider->get_shared_storage_config();
		$config['acl'] = (string) ($this->config['s3attachments_acl'] ?? 'private');

		return $config;
	}

	protected function build_object_key(string $physical_filename, bool $thumbnail): string
	{
		$prefix = trim((string) ($this->config['s3attachments_path_prefix'] ?? 'attachments'), " \t\n\r\0\x0B/");
		$basename = $thumbnail ? ('thumb_' . $physical_filename) : $physical_filename;

		return ($prefix !== '') ? ($prefix . '/' . $basename) : $basename;
	}

	protected function get_local_path(string $physical_filename, bool $thumbnail): string
	{
		$filename = $thumbnail ? ('thumb_' . $physical_filename) : $physical_filename;

		return $this->phpbb_root_path . $this->config['upload_path'] . '/' . $filename;
	}

	protected function local_file_is_usable(string $physical_filename, bool $thumbnail): bool
	{
		$path = $this->get_local_path($physical_filename, $thumbnail);

		return $this->filesystem->exists($path) && is_readable($path) && @filesize($path) > 0;
	}

	protected function sanitize_filename(string $filename): string
	{
		$filename = str_replace('\\', '/', $filename);
		$filename = basename($filename);

		return trim($filename);
	}

	protected function replace_local_file_with_marker(string $physical_filename, bool $thumbnail): void
	{
		$path = $this->get_local_path($physical_filename, $thumbnail);
		if (!$this->filesystem->exists($path))
		{
			return;
		}

		if (@file_put_contents($path, '') === false)
		{
			throw new \RuntimeException('Unable to replace local attachment with marker: ' . $path);
		}
	}

	protected function write_local_marker(string $physical_filename, bool $thumbnail): void
	{
		$path = $this->get_local_path($physical_filename, $thumbnail);
		if (@file_put_contents($path, '') === false)
		{
			throw new \RuntimeException('Unable to write local attachment marker: ' . $path);
		}
	}

	protected function has_remaining_attachment_entries(string $physical_filename): bool
	{
		$sql = 'SELECT COUNT(attach_id) AS num_entries
			FROM ' . ATTACHMENTS_TABLE . "
			WHERE physical_filename = '" . $this->db->sql_escape($physical_filename) . "'";
		$result = $this->db->sql_query($sql);
		$num_entries = (int) $this->db->sql_fetchfield('num_entries');
		$this->db->sql_freeresult($result);

		return $num_entries > 0;
	}

	protected function detect_thumbnail_mimetype(string $path, string $fallback): string
	{
		if (function_exists('mime_content_type'))
		{
			$mime = @mime_content_type($path);
			if (is_string($mime) && $mime !== '')
			{
				return $mime;
			}
		}

		return $fallback !== '' ? $fallback : 'image/jpeg';
	}

	protected function write_log(string $event, array $context = []): void
	{
		$context = array_merge([
			'time_utc' => gmdate('Y-m-d H:i:s') . ' UTC',
			'event' => $event,
		], $context);

		$encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if ($encoded === false)
		{
			$encoded = json_encode([
				'time_utc' => $context['time_utc'],
				'event' => $event,
				'encode_failure' => true,
			]);
		}

		@file_put_contents($this->phpbb_root_path . 'store/s3attachments.log', $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
	}
}
