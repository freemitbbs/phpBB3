<?php

namespace freemitbbs\postarchive\service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class manager
{
	public const STATUS_QUEUED = 'queued';
	public const STATUS_PROCESSING = 'processing';
	public const STATUS_DONE = 'done';
	public const STATUS_FAILED = 'failed';

	public const ARCHIVE_TTL_SECONDS = 86400;

	private const BATCH_SIZE = 500;
	private const PROCESSING_STALE_SECONDS = 7200;
	private const JOB_RETENTION_SECONDS = 604800;
	private const MAX_ERROR_LENGTH = 255;

	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\db\driver\driver_interface $db;
	protected string $root_path;
	protected string $php_ext;
	protected string $users_table;
	protected string $posts_table;
	protected string $topics_table;
	protected string $forums_table;
	protected string $archives_table;
	protected string $jobs_table;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\controller\helper $helper,
		\phpbb\db\driver\driver_interface $db,
		string $root_path,
		string $php_ext,
		string $users_table,
		string $posts_table,
		string $topics_table,
		string $forums_table,
		string $archives_table,
		string $jobs_table
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->helper = $helper;
		$this->db = $db;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->users_table = $users_table;
		$this->posts_table = $posts_table;
		$this->topics_table = $topics_table;
		$this->forums_table = $forums_table;
		$this->archives_table = $archives_table;
		$this->jobs_table = $jobs_table;
	}

	public function enqueue_archive(int $user_id): int
	{
		$user_row = $this->user_row($user_id);
		if ($user_row === null)
		{
			throw new \RuntimeException('Unable to queue post archive for unknown user.');
		}

		$pending_job = $this->latest_user_pending_job($user_id);
		if ($pending_job !== null)
		{
			return (int) $pending_job['job_id'];
		}

		$this->delete_user_jobs($user_id, [self::STATUS_FAILED]);

		$sql = 'INSERT INTO ' . $this->jobs_table . ' ' . $this->db->sql_build_array('INSERT', [
			'user_id' => $user_id,
			'status' => self::STATUS_QUEUED,
			'requested_time' => time(),
			'started_time' => 0,
			'completed_time' => 0,
			'archive_id' => 0,
			'physical_filename' => '',
			'real_filename' => '',
			'attempt_count' => 0,
			'last_error' => '',
		]);
		$this->db->sql_query($sql);

		return (int) $this->db->sql_nextid();
	}

	public function process_next_job(): array
	{
		$expired_archives = $this->cleanup_expired_archives();
		$expired_jobs = $this->cleanup_old_jobs();
		$job = $this->claim_next_job();

		if ($job === null)
		{
			return [
				'processed' => 0,
				'expired_archives' => $expired_archives,
				'expired_jobs' => $expired_jobs,
				'status' => 'idle',
			];
		}

		$user_row = $this->user_row((int) $job['user_id']);
		if ($user_row === null)
		{
			$this->mark_job_failed((int) $job['job_id'], 'User no longer exists.');

			return [
				'processed' => 1,
				'expired_archives' => $expired_archives,
				'expired_jobs' => $expired_jobs,
				'status' => self::STATUS_FAILED,
			];
		}

		$created_at = time();
		$expires_at = $created_at + self::ARCHIVE_TTL_SECONDS;
		$real_filename = $this->download_filename((int) $user_row['user_id'], $created_at);
		$physical_filename = $this->physical_filename((int) $user_row['user_id'], $created_at);
		$archive_path = $this->archive_path($physical_filename);
		$post_count = 0;
		$filesize = 0;
		$restore_user_context = $this->apply_cron_user_context_defaults();

		try
		{
			$this->delete_job_physical_file($job);
			$this->assign_job_filenames((int) $job['job_id'], $physical_filename, $real_filename);

			$post_count = $this->build_archive_file($archive_path, $user_row, $created_at);
			$filesize = (int) filesize($archive_path);
			$archive_id = $this->insert_archive_record((int) $user_row['user_id'], $physical_filename, $real_filename, $created_at, $expires_at, $post_count, $filesize);

			if ($archive_id > 0)
			{
				$this->delete_user_archives((int) $user_row['user_id'], $archive_id);
			}

			$this->mark_job_done((int) $job['job_id'], $archive_id, $post_count);
			try
			{
				$this->send_archive_ready_pm($user_row, [
					'archive_id' => $archive_id,
					'expires_time' => $expires_at,
					'post_count' => $post_count,
					'filesize' => $filesize,
				]);
			}
			catch (\Throwable $pm_error)
			{
				$this->record_job_error((int) $job['job_id'], 'Private message delivery failed: ' . $pm_error->getMessage());
			}
		}
		catch (\Throwable $e)
		{
			if (is_file($archive_path))
			{
				@unlink($archive_path);
			}
			$this->mark_job_failed((int) $job['job_id'], $e->getMessage());

			return [
				'processed' => 1,
				'expired_archives' => $expired_archives,
				'expired_jobs' => $expired_jobs,
				'status' => self::STATUS_FAILED,
			];
		}
		finally
		{
			$restore_user_context();
		}

		return [
			'processed' => 1,
			'expired_archives' => $expired_archives,
			'expired_jobs' => $expired_jobs,
			'status' => self::STATUS_DONE,
			'post_count' => $post_count,
			'filesize' => $filesize,
		];
	}

	public function has_runnable_jobs(): bool
	{
		$cutoff = time() - self::PROCESSING_STALE_SECONDS;

		$sql = 'SELECT job_id
			FROM ' . $this->jobs_table . "
			WHERE status = '" . $this->db->sql_escape(self::STATUS_QUEUED) . "'
				OR (status = '" . $this->db->sql_escape(self::STATUS_PROCESSING) . "'
					AND started_time <= " . $cutoff . ')
			ORDER BY requested_time ASC, job_id ASC';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return (bool) $row;
	}

	public function has_expired_archives(): bool
	{
		$sql = 'SELECT archive_id
			FROM ' . $this->archives_table . '
			WHERE expires_time <= ' . time();
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return (bool) $row;
	}

	public function count_visible_posts(int $user_id): int
	{
		$user_row = $this->user_row($user_id);
		if ($user_row === null)
		{
			return 0;
		}

		$forum_ids = $this->readable_forum_ids($user_row);
		if (empty($forum_ids))
		{
			return 0;
		}

		$sql = 'SELECT COUNT(p.post_id) AS total_posts
			FROM ' . $this->posts_table . ' p
			INNER JOIN ' . $this->topics_table . ' t
				ON t.topic_id = p.topic_id
			WHERE p.poster_id = ' . $user_id . '
				AND p.post_visibility = ' . ITEM_APPROVED . '
				AND t.topic_visibility = ' . ITEM_APPROVED . '
				AND ' . $this->db->sql_in_set('p.forum_id', $forum_ids);
		$result = $this->db->sql_query($sql);
		$total_posts = (int) $this->db->sql_fetchfield('total_posts');
		$this->db->sql_freeresult($result);

		return $total_posts;
	}

	public function latest_user_archive(int $user_id): ?array
	{
		$sql = 'SELECT archive_id, physical_filename, real_filename, created_time, expires_time, post_count, filesize
			FROM ' . $this->archives_table . '
			WHERE user_id = ' . $user_id . '
				AND expires_time > ' . time() . '
			ORDER BY created_time DESC, archive_id DESC';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return null;
		}

		$archive_path = $this->archive_path((string) $row['physical_filename']);
		if (!is_file($archive_path))
		{
			$this->delete_archive_rows([$row]);
			return null;
		}

		return [
			'archive_id' => (int) $row['archive_id'],
			'real_filename' => (string) $row['real_filename'],
			'created_time' => (int) $row['created_time'],
			'expires_time' => (int) $row['expires_time'],
			'post_count' => (int) $row['post_count'],
			'filesize' => (int) $row['filesize'],
			'download_url' => $this->helper->route('freemitbbs_postarchive_download', ['archive' => (int) $row['archive_id']]),
		];
	}

	public function latest_user_pending_job(int $user_id): ?array
	{
		$sql = 'SELECT job_id, status, requested_time, started_time, attempt_count
			FROM ' . $this->jobs_table . '
			WHERE user_id = ' . $user_id . '
				AND ' . $this->db->sql_in_set('status', [self::STATUS_QUEUED, self::STATUS_PROCESSING]) . '
			ORDER BY requested_time DESC, job_id DESC';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return null;
		}

		return [
			'job_id' => (int) $row['job_id'],
			'status' => (string) $row['status'],
			'requested_time' => (int) $row['requested_time'],
			'started_time' => (int) $row['started_time'],
			'attempt_count' => (int) $row['attempt_count'],
		];
	}

	public function latest_user_failed_job(int $user_id, int $after_time = 0): ?array
	{
		$sql = 'SELECT job_id, requested_time, completed_time, last_error
			FROM ' . $this->jobs_table . "
			WHERE user_id = " . $user_id . "
				AND status = '" . $this->db->sql_escape(self::STATUS_FAILED) . "'" . ($after_time > 0 ? '
				AND requested_time > ' . $after_time : '') . '
			ORDER BY completed_time DESC, job_id DESC';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return null;
		}

		return [
			'job_id' => (int) $row['job_id'],
			'requested_time' => (int) $row['requested_time'],
			'completed_time' => (int) $row['completed_time'],
			'last_error' => (string) $row['last_error'],
		];
	}

	public function archive_for_user(int $archive_id, int $user_id): ?array
	{
		$sql = 'SELECT archive_id, physical_filename, real_filename, expires_time
			FROM ' . $this->archives_table . '
			WHERE archive_id = ' . $archive_id . '
				AND user_id = ' . $user_id . '
				AND expires_time > ' . time();
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return null;
		}

		if (!is_file($this->archive_path((string) $row['physical_filename'])))
		{
			$this->delete_archive_rows([$row]);
			return null;
		}

		return $row;
	}

	public function archive_file_path(string $physical_filename): string
	{
		return $this->archive_path($physical_filename);
	}

	public function cleanup_expired_archives(): int
	{
		$sql = 'SELECT archive_id, physical_filename
			FROM ' . $this->archives_table . '
			WHERE expires_time <= ' . time();
		$result = $this->db->sql_query($sql);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $this->delete_archive_rows($rows);
	}

	protected function claim_next_job(): ?array
	{
		$cutoff = time() - self::PROCESSING_STALE_SECONDS;

		$sql = 'SELECT job_id
			FROM ' . $this->jobs_table . "
			WHERE status = '" . $this->db->sql_escape(self::STATUS_QUEUED) . "'
				OR (status = '" . $this->db->sql_escape(self::STATUS_PROCESSING) . "'
					AND started_time <= " . $cutoff . ')
			ORDER BY requested_time ASC, job_id ASC';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return null;
		}

		$job_id = (int) $row['job_id'];
		$now = time();
		$sql = 'UPDATE ' . $this->jobs_table . "
			SET status = '" . $this->db->sql_escape(self::STATUS_PROCESSING) . "',
				started_time = " . $now . ',
				attempt_count = attempt_count + 1
			WHERE job_id = ' . $job_id . "
				AND (status = '" . $this->db->sql_escape(self::STATUS_QUEUED) . "'
					OR (status = '" . $this->db->sql_escape(self::STATUS_PROCESSING) . "'
						AND started_time <= " . $cutoff . '))';
		$this->db->sql_query($sql);

		if ((int) $this->db->sql_affectedrows() !== 1)
		{
			return null;
		}

		return $this->job_row($job_id);
	}

	protected function job_row(int $job_id): ?array
	{
		$sql = 'SELECT job_id, user_id, status, requested_time, started_time, completed_time,
				archive_id, physical_filename, real_filename, attempt_count, last_error
			FROM ' . $this->jobs_table . '
			WHERE job_id = ' . $job_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	protected function user_row(int $user_id): ?array
	{
		if ($user_id <= ANONYMOUS)
		{
			return null;
		}

		$sql = 'SELECT user_id, username, user_permissions, user_type, user_lang
			FROM ' . $this->users_table . '
			WHERE user_id = ' . $user_id . '
				AND user_type IN (' . USER_NORMAL . ', ' . USER_FOUNDER . ')';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	protected function readable_forum_ids(array $user_row): array
	{
		$acl_user = $user_row;
		$acl_user['user_permissions'] = (string) ($acl_user['user_permissions'] ?? '');
		$this->auth->acl($acl_user);

		$forum_acl = $this->auth->acl_getf('f_read', true);
		$forum_ids = array_map('intval', array_keys($forum_acl));

		return array_values(array_filter($forum_ids, static function (int $forum_id): bool {
			return $forum_id > 0;
		}));
	}

	protected function build_archive_file(string $archive_path, array $user_row, int $created_at): int
	{
		if (!class_exists('ZipArchive'))
		{
			throw new \RuntimeException('PHP ZipArchive extension is not available.');
		}
		if (!function_exists('generate_text_for_edit'))
		{
			require_once $this->root_path . 'includes/functions_content.' . $this->php_ext;
		}

		$forum_ids = $this->readable_forum_ids($user_row);
		$staging_dir = $this->temporary_directory();
		$zip = new \ZipArchive();
		$zip_open = false;
		$total_posts = 0;

		try
		{
			$open_result = $zip->open($archive_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
			if ($open_result !== true)
			{
				throw new \RuntimeException('Unable to open post archive ZIP for writing.');
			}
			$zip_open = true;

			if (!empty($forum_ids))
			{
				$range = $this->post_time_range((int) $user_row['user_id'], $forum_ids);
				if ($range !== null)
				{
					$month = $this->first_month_start((int) $range['min_time']);
					$last_month = $this->first_month_start((int) $range['max_time']);
					$board_url = generate_board_url();

					while ($month <= $last_month)
					{
						$next_month = $this->next_month_start($month);
						$total_posts += $this->dump_month($zip, $staging_dir, (int) $user_row['user_id'], $forum_ids, $month, $next_month, $board_url);
						$month = $next_month;
					}
				}
			}

			if (!$zip->addFromString('README.txt', $this->readme($total_posts, $created_at, $user_row)))
			{
				throw new \RuntimeException('Unable to add README to post archive ZIP.');
			}
			if (!$zip->close())
			{
				throw new \RuntimeException('Unable to finish post archive ZIP.');
			}
			$zip_open = false;
		}
		catch (\Throwable $e)
		{
			if ($zip_open)
			{
				$zip->close();
			}
			@unlink($archive_path);
			throw $e;
		}
		finally
		{
			$this->delete_directory($staging_dir);
		}

		return $total_posts;
	}

	protected function post_time_range(int $user_id, array $forum_ids): ?array
	{
		if (empty($forum_ids))
		{
			return null;
		}

		$sql = 'SELECT MIN(p.post_time) AS min_time, MAX(p.post_time) AS max_time
			FROM ' . $this->posts_table . ' p
			INNER JOIN ' . $this->topics_table . ' t
				ON t.topic_id = p.topic_id
			WHERE p.poster_id = ' . $user_id . '
				AND p.post_visibility = ' . ITEM_APPROVED . '
				AND t.topic_visibility = ' . ITEM_APPROVED . '
				AND ' . $this->db->sql_in_set('p.forum_id', $forum_ids);
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row || (int) $row['min_time'] <= 0 || (int) $row['max_time'] <= 0)
		{
			return null;
		}

		return [
			'min_time' => (int) $row['min_time'],
			'max_time' => (int) $row['max_time'],
		];
	}

	protected function dump_month(\ZipArchive $zip, string $staging_dir, int $user_id, array $forum_ids, int $month_start, int $month_end, string $board_url): int
	{
		$month_name = gmdate('Y-m', $month_start);
		$csv_path = $staging_dir . '/' . $month_name . '.csv';
		$text_path = $staging_dir . '/' . $month_name . '.txt';
		$csv_handle = null;
		$text_handle = null;
		$month_count = 0;
		$last_time = $month_start - 1;
		$last_post_id = 0;

		try
		{
			do
			{
				$sql = 'SELECT p.post_id, p.topic_id, p.forum_id, p.post_time, p.post_edit_time,
						p.post_subject, p.post_text, p.bbcode_uid, p.enable_bbcode, p.enable_smilies, p.enable_magic_url,
						t.topic_title, f.forum_name
					FROM ' . $this->posts_table . ' p
					INNER JOIN ' . $this->topics_table . ' t
						ON t.topic_id = p.topic_id
					INNER JOIN ' . $this->forums_table . ' f
						ON f.forum_id = p.forum_id
					WHERE p.poster_id = ' . $user_id . '
						AND p.post_visibility = ' . ITEM_APPROVED . '
						AND t.topic_visibility = ' . ITEM_APPROVED . '
						AND p.post_time >= ' . $month_start . '
						AND p.post_time < ' . $month_end . '
						AND (p.post_time > ' . $last_time . '
							OR (p.post_time = ' . $last_time . '
								AND p.post_id > ' . $last_post_id . '))
						AND ' . $this->db->sql_in_set('p.forum_id', $forum_ids) . '
					ORDER BY p.post_time ASC, p.post_id ASC';
				$result = $this->db->sql_query_limit($sql, self::BATCH_SIZE);

				$batch_count = 0;
				while ($row = $this->db->sql_fetchrow($result))
				{
					if ($csv_handle === null)
					{
						$csv_handle = $this->open_csv_file($csv_path);
						$text_handle = $this->open_text_file($text_path, $month_name);
					}

					$post = $this->archive_post_row($row, $board_url);
					fputcsv($csv_handle, $this->csv_fields($post));
					fwrite($text_handle, $this->text_post($post));

					$last_time = (int) $row['post_time'];
					$last_post_id = (int) $row['post_id'];
					$batch_count++;
					$month_count++;
				}
				$this->db->sql_freeresult($result);
			}
			while ($batch_count === self::BATCH_SIZE);
		}
		finally
		{
			if (is_resource($csv_handle))
			{
				fclose($csv_handle);
			}
			if (is_resource($text_handle))
			{
				fclose($text_handle);
			}
		}

		if ($month_count > 0)
		{
			if (!$zip->addFile($csv_path, 'posts/' . $month_name . '.csv'))
			{
				throw new \RuntimeException('Unable to add monthly CSV to post archive ZIP.');
			}
			if (!$zip->addFile($text_path, 'posts/' . $month_name . '.txt'))
			{
				throw new \RuntimeException('Unable to add monthly text file to post archive ZIP.');
			}
		}

		return $month_count;
	}

	protected function open_csv_file(string $csv_path)
	{
		$handle = fopen($csv_path, 'wb');
		if ($handle === false)
		{
			throw new \RuntimeException('Unable to open monthly post archive CSV.');
		}

		fputcsv($handle, [
			'post_id',
			'topic_id',
			'forum_id',
			'posted_at',
			'edited_at',
			'forum_name',
			'topic_title',
			'post_subject',
			'url',
			'body_bbcode',
		]);

		return $handle;
	}

	protected function open_text_file(string $text_path, string $month_name)
	{
		$handle = fopen($text_path, 'wb');
		if ($handle === false)
		{
			throw new \RuntimeException('Unable to open monthly post archive text file.');
		}

		fwrite($handle, 'FreeMITBBS post archive - ' . $month_name . "\n\n");

		return $handle;
	}

	protected function archive_post_row(array $row, string $board_url): array
	{
		$body = $this->decode_post_body($row);
		$post_id = (int) $row['post_id'];
		$post_time = (int) $row['post_time'];
		$post_edit_time = (int) $row['post_edit_time'];

		return [
			'post_id' => $post_id,
			'topic_id' => (int) $row['topic_id'],
			'forum_id' => (int) $row['forum_id'],
			'posted_at' => gmdate('c', $post_time),
			'edited_at' => $post_edit_time > 0 ? gmdate('c', $post_edit_time) : null,
			'forum_name' => html_entity_decode((string) $row['forum_name'], ENT_COMPAT, 'UTF-8'),
			'topic_title' => html_entity_decode(censor_text((string) $row['topic_title']), ENT_COMPAT, 'UTF-8'),
			'post_subject' => html_entity_decode(censor_text((string) $row['post_subject']), ENT_COMPAT, 'UTF-8'),
			'url' => $board_url . '/viewtopic.' . $this->php_ext . '?p=' . $post_id . '#p' . $post_id,
			'body_bbcode' => $body,
		];
	}

	protected function decode_post_body(array $row): string
	{
		$flags = 0;
		$flags |= !empty($row['enable_bbcode']) ? OPTION_FLAG_BBCODE : 0;
		$flags |= !empty($row['enable_smilies']) ? OPTION_FLAG_SMILIES : 0;
		$flags |= !empty($row['enable_magic_url']) ? OPTION_FLAG_LINKS : 0;

		$decoded = generate_text_for_edit((string) $row['post_text'], (string) $row['bbcode_uid'], $flags);

		return html_entity_decode((string) $decoded['text'], ENT_COMPAT, 'UTF-8');
	}

	protected function csv_fields(array $post): array
	{
		return [
			$post['post_id'],
			$post['topic_id'],
			$post['forum_id'],
			$post['posted_at'],
			$post['edited_at'] ?? '',
			$post['forum_name'],
			$post['topic_title'],
			$post['post_subject'],
			$post['url'],
			$post['body_bbcode'],
		];
	}

	protected function text_post(array $post): string
	{
		return implode("\n", [
			str_repeat('=', 72),
			'Post #' . $post['post_id'],
			'Date: ' . $post['posted_at'],
			'Forum: ' . $post['forum_name'],
			'Topic: ' . $post['topic_title'],
			'Subject: ' . $post['post_subject'],
			'URL: ' . $post['url'],
			'',
			$post['body_bbcode'],
			'',
		]);
	}

	protected function readme(int $post_count, int $created_at, array $user_row): string
	{
		return implode("\n", [
			'FreeMITBBS post archive',
			'Generated: ' . gmdate('c', $created_at),
			'Expires: ' . gmdate('c', $created_at + self::ARCHIVE_TTL_SECONDS),
			'User: ' . (string) $user_row['username'] . ' (ID ' . (int) $user_row['user_id'] . ')',
			'Posts included: ' . $post_count,
			'',
			'This archive includes approved posts that were visible to your account at export time.',
			'',
			'Files:',
			'- posts/YYYY-MM.csv: post metadata and BBCode bodies for that month',
			'- posts/YYYY-MM.txt: readable text copy of that month',
			'',
		]);
	}

	protected function send_archive_ready_pm(array $recipient_row, array $archive): void
	{
		if (!function_exists('submit_pm'))
		{
			require_once $this->root_path . 'includes/functions_privmsgs.' . $this->php_ext;
		}
		if (!class_exists('parse_message'))
		{
			require_once $this->root_path . 'includes/message_parser.' . $this->php_ext;
		}

		$restore_user_context = $this->apply_cron_user_context_defaults();
		try
		{
			$lang = $this->extension_language((string) ($recipient_row['user_lang'] ?? ''));
			$subject = $lang['POSTARCHIVE_PM_SUBJECT'] ?? 'Your post archive is ready';
			$download_url = $this->helper->route(
				'freemitbbs_postarchive_download',
				['archive' => (int) $archive['archive_id']],
				true,
				false,
				UrlGeneratorInterface::ABSOLUTE_URL
			);
			$expires = gmdate('Y-m-d H:i:s', (int) $archive['expires_time']) . ' UTC';
			$window = $this->download_window_label($lang);
			$filesize = function_exists('get_formatted_filesize') ? get_formatted_filesize((int) $archive['filesize']) : (string) (int) $archive['filesize'];
			$body_template = $lang['POSTARCHIVE_PM_BODY'] ?? "Your post archive is ready.\n\nDownload it here:\n%1\$s\n\nThe download is available until %2\$s (%3\$s from generation). After that time window, the archive file will be deleted automatically.\n\nPosts included: %4\$d\nArchive size: %5\$s";
			$message = sprintf($body_template, $download_url, $expires, $window, (int) $archive['post_count'], $filesize);

			$message_parser = new \parse_message();
			$message_parser->message = $message;
			$message_parser->parse(false, true, false, false, false, true, true);

			$sender_row = $this->pm_sender_user();
			$sender_acl = $sender_row;
			$this->auth->acl($sender_acl);

			$pm_data = [
				'from_user_id' => (int) $sender_row['user_id'],
				'from_user_ip' => '',
				'from_username' => (string) $sender_row['username'],
				'enable_sig' => false,
				'enable_bbcode' => false,
				'enable_smilies' => false,
				'enable_urls' => true,
				'icon_id' => 0,
				'bbcode_bitfield' => $message_parser->bbcode_bitfield,
				'bbcode_uid' => $message_parser->bbcode_uid,
				'message' => $message_parser->message,
				'address_list' => ['u' => [(int) $recipient_row['user_id'] => 'to']],
			];

			submit_pm('post', $subject, $pm_data, false);
		}
		finally
		{
			$restore_user_context();
		}
	}

	protected function apply_cron_user_context_defaults(): callable
	{
		global $user;

		if (!$user instanceof \phpbb\user)
		{
			return static function (): void {
			};
		}

		$missing_data_keys = [];
		foreach (['user_options' => 230271] as $key => $default)
		{
			if (!array_key_exists($key, $user->data))
			{
				$user->data[$key] = $default;
				$missing_data_keys[] = $key;
			}
		}

		$missing_page_keys = [];
		foreach (['page_name' => '', 'page_dir' => '', 'query_string' => ''] as $key => $default)
		{
			if (!array_key_exists($key, $user->page))
			{
				$user->page[$key] = $default;
				$missing_page_keys[] = $key;
			}
		}

		return static function () use ($user, $missing_data_keys, $missing_page_keys): void {
			foreach ($missing_data_keys as $key)
			{
				unset($user->data[$key]);
			}
			foreach ($missing_page_keys as $key)
			{
				unset($user->page[$key]);
			}
		};
	}

	protected function extension_language(string $lang_name): array
	{
		$lang = [];
		$candidates = array_values(array_unique([
			basename($lang_name),
			basename((string) ($this->config['default_lang'] ?? '')),
			'en',
		]));

		foreach ($candidates as $candidate)
		{
			if ($candidate === '')
			{
				continue;
			}

			$file = rtrim($this->root_path, '/\\') . '/ext/freemitbbs/postarchive/language/' . $candidate . '/common.' . $this->php_ext;
			if (is_file($file))
			{
				include $file;
				break;
			}
		}

		return $lang;
	}

	protected function pm_sender_user(): array
	{
		$sql = 'SELECT user_id, username, user_permissions, user_type
			FROM ' . $this->users_table . '
			WHERE user_type = ' . USER_FOUNDER . '
			ORDER BY user_id ASC';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($row)
		{
			return $row;
		}

		return [
			'user_id' => ANONYMOUS,
			'username' => (string) ($this->config['sitename'] ?? 'FreeMITBBS'),
			'user_permissions' => '',
			'user_type' => USER_IGNORE,
		];
	}

	protected function download_window_label(array $lang): string
	{
		if (self::ARCHIVE_TTL_SECONDS % 3600 === 0)
		{
			return sprintf($lang['POSTARCHIVE_WINDOW_HOURS'] ?? '%d hours', (int) (self::ARCHIVE_TTL_SECONDS / 3600));
		}

		return sprintf($lang['POSTARCHIVE_WINDOW_SECONDS'] ?? '%d seconds', self::ARCHIVE_TTL_SECONDS);
	}

	protected function insert_archive_record(int $user_id, string $physical_filename, string $real_filename, int $created_at, int $expires_at, int $post_count, int $filesize): int
	{
		$sql = 'INSERT INTO ' . $this->archives_table . ' ' . $this->db->sql_build_array('INSERT', [
			'user_id' => $user_id,
			'physical_filename' => $physical_filename,
			'real_filename' => $real_filename,
			'created_time' => $created_at,
			'expires_time' => $expires_at,
			'post_count' => $post_count,
			'filesize' => $filesize,
		]);
		$this->db->sql_query($sql);

		return (int) $this->db->sql_nextid();
	}

	protected function assign_job_filenames(int $job_id, string $physical_filename, string $real_filename): void
	{
		$sql = 'UPDATE ' . $this->jobs_table . '
			SET ' . $this->db->sql_build_array('UPDATE', [
				'physical_filename' => $physical_filename,
				'real_filename' => $real_filename,
			]) . '
			WHERE job_id = ' . $job_id;
		$this->db->sql_query($sql);
	}

	protected function mark_job_done(int $job_id, int $archive_id, int $post_count): void
	{
		$sql = 'UPDATE ' . $this->jobs_table . '
			SET ' . $this->db->sql_build_array('UPDATE', [
				'status' => self::STATUS_DONE,
				'completed_time' => time(),
				'archive_id' => $archive_id,
				'last_error' => '',
			]) . '
			WHERE job_id = ' . $job_id;
		$this->db->sql_query($sql);
	}

	protected function mark_job_failed(int $job_id, string $error): void
	{
		$error = substr($error, 0, self::MAX_ERROR_LENGTH);
		$sql = 'UPDATE ' . $this->jobs_table . '
			SET ' . $this->db->sql_build_array('UPDATE', [
				'status' => self::STATUS_FAILED,
				'completed_time' => time(),
				'last_error' => $error,
			]) . '
			WHERE job_id = ' . $job_id;
		$this->db->sql_query($sql);
	}

	protected function record_job_error(int $job_id, string $error): void
	{
		$error = substr($error, 0, self::MAX_ERROR_LENGTH);
		$sql = 'UPDATE ' . $this->jobs_table . '
			SET ' . $this->db->sql_build_array('UPDATE', [
				'last_error' => $error,
			]) . '
			WHERE job_id = ' . $job_id;
		$this->db->sql_query($sql);
	}

	protected function cleanup_old_jobs(): int
	{
		$cutoff = time() - self::JOB_RETENTION_SECONDS;
		$sql = 'DELETE FROM ' . $this->jobs_table . '
			WHERE completed_time > 0
				AND completed_time <= ' . $cutoff . '
				AND ' . $this->db->sql_in_set('status', [self::STATUS_DONE, self::STATUS_FAILED]);
		$this->db->sql_query($sql);

		return (int) $this->db->sql_affectedrows();
	}

	protected function delete_user_jobs(int $user_id, array $statuses): int
	{
		if (empty($statuses))
		{
			return 0;
		}

		$sql = 'DELETE FROM ' . $this->jobs_table . '
			WHERE user_id = ' . $user_id . '
				AND ' . $this->db->sql_in_set('status', $statuses);
		$this->db->sql_query($sql);

		return (int) $this->db->sql_affectedrows();
	}

	protected function delete_user_archives(int $user_id, int $except_archive_id = 0): int
	{
		$sql = 'SELECT archive_id, physical_filename
			FROM ' . $this->archives_table . '
			WHERE user_id = ' . $user_id . ($except_archive_id > 0 ? '
				AND archive_id <> ' . $except_archive_id : '');
		$result = $this->db->sql_query($sql);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $this->delete_archive_rows($rows);
	}

	protected function delete_archive_rows(array $rows): int
	{
		if (empty($rows))
		{
			return 0;
		}

		$archive_ids = [];
		foreach ($rows as $row)
		{
			$archive_ids[] = (int) $row['archive_id'];
			$path = $this->archive_path((string) $row['physical_filename']);
			if (is_file($path))
			{
				@unlink($path);
			}
		}

		$this->db->sql_query('DELETE FROM ' . $this->archives_table . '
			WHERE ' . $this->db->sql_in_set('archive_id', $archive_ids));

		return count($archive_ids);
	}

	protected function delete_job_physical_file(array $job): void
	{
		if (empty($job['physical_filename']))
		{
			return;
		}

		$path = $this->archive_path((string) $job['physical_filename']);
		if (is_file($path))
		{
			@unlink($path);
		}
	}

	protected function archive_storage_dir(): string
	{
		$storage_dir = rtrim($this->root_path, '/\\') . '/store/postarchive';
		if (!is_dir($storage_dir) && !@mkdir($storage_dir, 0775, true) && !is_dir($storage_dir))
		{
			throw new \RuntimeException('Unable to create post archive storage directory.');
		}
		if (!is_writable($storage_dir))
		{
			throw new \RuntimeException('Post archive storage directory is not writable.');
		}

		$index_path = $storage_dir . '/index.htm';
		if (!is_file($index_path))
		{
			@file_put_contents($index_path, '');
		}

		return $storage_dir;
	}

	protected function archive_path(string $physical_filename): string
	{
		$physical_filename = basename($physical_filename);
		if ($physical_filename === '' || $physical_filename === '.' || $physical_filename === '..')
		{
			throw new \RuntimeException('Invalid post archive filename.');
		}

		return $this->archive_storage_dir() . '/' . $physical_filename;
	}

	protected function physical_filename(int $user_id, int $created_at): string
	{
		return 'postarchive_' . $user_id . '_' . $created_at . '_' . bin2hex(random_bytes(8)) . '.zip';
	}

	protected function temporary_directory(): string
	{
		$base = $this->archive_storage_dir();
		$temp_path = tempnam($base, 'build_');
		if ($temp_path === false)
		{
			throw new \RuntimeException('Unable to create post archive staging path.');
		}

		@unlink($temp_path);
		if (!@mkdir($temp_path, 0775, true))
		{
			throw new \RuntimeException('Unable to create post archive staging directory.');
		}

		return $temp_path;
	}

	protected function delete_directory(string $directory): void
	{
		if (!is_dir($directory))
		{
			return;
		}

		$files = scandir($directory);
		if ($files !== false)
		{
			foreach ($files as $file)
			{
				if ($file === '.' || $file === '..')
				{
					continue;
				}
				$path = $directory . '/' . $file;
				if (is_dir($path))
				{
					$this->delete_directory($path);
				}
				else
				{
					@unlink($path);
				}
			}
		}

		@rmdir($directory);
	}

	protected function first_month_start(int $timestamp): int
	{
		return (int) gmmktime(0, 0, 0, (int) gmdate('n', $timestamp), 1, (int) gmdate('Y', $timestamp));
	}

	protected function next_month_start(int $timestamp): int
	{
		return (int) gmmktime(0, 0, 0, (int) gmdate('n', $timestamp) + 1, 1, (int) gmdate('Y', $timestamp));
	}

	protected function download_filename(int $user_id, int $created_at): string
	{
		return 'freemitbbs-posts-user-' . $user_id . '-' . gmdate('Ymd-His', $created_at) . '.zip';
	}
}
