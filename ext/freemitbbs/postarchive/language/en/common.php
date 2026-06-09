<?php

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'UCP_POSTARCHIVE' => 'Post Archive',
	'POSTARCHIVE_READY_TITLE' => 'Archive ready',
	'POSTARCHIVE_READY_EXPLAIN' => 'Your current archive is available for a limited time.',
	'POSTARCHIVE_CREATE_TITLE' => 'Create your post archive',
	'POSTARCHIVE_CREATE_EXPLAIN' => 'Request a ZIP archive of your approved posts that are currently visible to your account. A cron job will build it in the background, split posts into monthly CSV and text files, and send you a private message when it is ready.',
	'POSTARCHIVE_VISIBLE_POSTS' => 'Posts available for export',
	'POSTARCHIVE_ARCHIVE_CREATED' => 'Created',
	'POSTARCHIVE_ARCHIVE_EXPIRES' => 'Expires',
	'POSTARCHIVE_ARCHIVE_POSTS' => 'Posts in archive',
	'POSTARCHIVE_ARCHIVE_SIZE' => 'Archive size',
	'POSTARCHIVE_PENDING_TITLE' => 'Archive request queued',
	'POSTARCHIVE_PROCESSING_TITLE' => 'Archive is being generated',
	'POSTARCHIVE_PENDING_EXPLAIN' => 'Your archive request is in the queue. You will receive a private message with the download link when the archive is ready.',
	'POSTARCHIVE_JOB_STATUS' => 'Status',
	'POSTARCHIVE_JOB_REQUESTED' => 'Requested',
	'POSTARCHIVE_JOB_STARTED' => 'Started',
	'POSTARCHIVE_STATUS_QUEUED' => 'Queued',
	'POSTARCHIVE_STATUS_PROCESSING' => 'Processing',
	'POSTARCHIVE_FAILED_TITLE' => 'Archive request failed',
	'POSTARCHIVE_FAILED_EXPLAIN' => 'The last archive request could not be completed. You can submit another request.',
	'POSTARCHIVE_FAILED_TIME' => 'Failed',
	'POSTARCHIVE_CREATE_BUTTON' => 'Request Archive',
	'POSTARCHIVE_RECREATE_BUTTON' => 'Request New Archive',
	'POSTARCHIVE_QUEUED_BUTTON' => 'Archive Requested',
	'POSTARCHIVE_DOWNLOAD_BUTTON' => 'Download Archive',
	'POSTARCHIVE_LOGIN_REQUIRED' => 'You need to log in before downloading your post archive.',
	'POSTARCHIVE_QUEUE_FAILED' => 'The post archive request could not be queued. Please try again later.',
	'POSTARCHIVE_NOT_AVAILABLE' => 'That post archive is no longer available. Please create a new archive.',
	'POSTARCHIVE_WINDOW_HOURS' => '%d hours',
	'POSTARCHIVE_WINDOW_SECONDS' => '%d seconds',
	'POSTARCHIVE_PM_SUBJECT' => 'Your post archive is ready',
	'POSTARCHIVE_PM_BODY' => "Your post archive is ready.\n\nDownload it here:\n%1\$s\n\nThe download is available until %2\$s (%3\$s from generation). After that time window, the archive file will be deleted automatically.\n\nPosts included: %4\$d\nArchive size: %5\$s",
]);
