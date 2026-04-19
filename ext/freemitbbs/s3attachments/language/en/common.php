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
	'S3ATTACHMENTS_SETTINGS' => 'S3 attachment storage',
	'S3ATTACHMENTS_ENABLED' => 'Store attachments in S3',
	'S3ATTACHMENTS_ENABLED_EXPLAIN' => 'Keep phpBB attachment metadata in the database, but upload attachment files to shared S3-compatible storage as soon as the attachment is added.',
	'S3ATTACHMENTS_PATH_PREFIX' => 'Object key prefix',
	'S3ATTACHMENTS_PATH_PREFIX_EXPLAIN' => 'Optional folder prefix for attachments, for example: attachments.',
	'S3ATTACHMENTS_SIGNED_URLS' => 'Use signed download URLs',
	'S3ATTACHMENTS_SIGNED_URLS_EXPLAIN' => 'Recommended. When enabled, phpBB redirects attachment downloads to short-lived signed S3 URLs after permission checks.',
	'S3ATTACHMENTS_SIGNED_URL_TTL' => 'Signed URL lifetime (seconds)',
	'S3ATTACHMENTS_SIGNED_URL_TTL_EXPLAIN' => 'How long each generated download URL remains valid.',
	'S3ATTACHMENTS_ACL' => 'Object ACL header',
	'S3ATTACHMENTS_ACL_EXPLAIN' => 'Sent as x-amz-acl during upload. Use <samp>private</samp> when relying on signed URLs; this omits the ACL header for providers that reject explicit private ACLs.',
	'S3ATTACHMENTS_UPLOAD_UPLOADING' => 'Uploading attachment...',
	'S3ATTACHMENTS_UPLOAD_SUCCESS' => 'Attachment uploaded.',
	'S3ATTACHMENTS_UPLOAD_ERROR' => 'Attachment upload failed. Please try again.',
	'S3ATTACHMENTS_UPLOAD_WAIT' => 'Please wait for the attachment upload to finish.',
]);
