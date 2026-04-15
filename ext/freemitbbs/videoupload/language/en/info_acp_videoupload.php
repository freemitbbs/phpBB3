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
	'ACP_VIDEOUPLOAD' => 'Video Upload',
	'ACP_VIDEOUPLOAD_GRP' => 'Video Upload',

	'VIDEOUPLOAD_EXPLAIN' => 'Upload videos to S3-compatible storage and insert direct .mp4/.ogg/.webm URLs into posts.',
	'VIDEOUPLOAD_FIELDSET_GENERAL' => 'General settings',
	'VIDEOUPLOAD_FIELDSET_S3' => 'S3 storage settings',

	'VIDEOUPLOAD_ENABLED' => 'Enable video upload',
	'VIDEOUPLOAD_ENABLED_EXPLAIN' => 'Enable or disable the posting editor video upload control.',
	'VIDEOUPLOAD_MAX_SIZE_MB' => 'Max file size (MB)',
	'VIDEOUPLOAD_MAX_SIZE_MB_EXPLAIN' => 'Maximum upload size for each video file.',

	'VIDEOUPLOAD_S3_ENDPOINT' => 'S3 endpoint URL',
	'VIDEOUPLOAD_S3_ENDPOINT_EXPLAIN' => 'For example: https://s3.amazonaws.com or your S3-compatible provider endpoint.',
	'VIDEOUPLOAD_S3_REGION' => 'S3 region',
	'VIDEOUPLOAD_S3_REGION_EXPLAIN' => 'For example: us-east-1.',
	'VIDEOUPLOAD_S3_BUCKET' => 'Bucket name',
	'VIDEOUPLOAD_S3_BUCKET_EXPLAIN' => 'Bucket where uploaded videos are stored.',
	'VIDEOUPLOAD_S3_ACCESS_KEY' => 'Access key ID',
	'VIDEOUPLOAD_S3_ACCESS_KEY_EXPLAIN' => 'Credential used for S3 API authentication.',
	'VIDEOUPLOAD_S3_SECRET_KEY' => 'Secret access key',
	'VIDEOUPLOAD_S3_SECRET_KEY_EXPLAIN' => 'Leave blank to keep the current stored secret.',
	'VIDEOUPLOAD_S3_SECRET_KEY_CLEAR' => 'Clear stored secret key',
	'VIDEOUPLOAD_S3_PATH_PREFIX' => 'Object key prefix',
	'VIDEOUPLOAD_S3_PATH_PREFIX_EXPLAIN' => 'Optional folder prefix, for example: videos.',
	'VIDEOUPLOAD_S3_PUBLIC_BASE_URL' => 'Public base URL (optional)',
	'VIDEOUPLOAD_S3_PUBLIC_BASE_URL_EXPLAIN' => 'Optional override used to build returned public URLs. Leave empty to derive from endpoint settings.',
	'VIDEOUPLOAD_S3_USE_PATH_STYLE' => 'Use path-style bucket URLs',
	'VIDEOUPLOAD_S3_USE_PATH_STYLE_EXPLAIN' => 'Enable for providers that require endpoint/bucket/key URL format.',
	'VIDEOUPLOAD_S3_ACL' => 'Object ACL header',
	'VIDEOUPLOAD_S3_ACL_EXPLAIN' => 'Sent as x-amz-acl (for example: public-read). Leave default unless your provider requires a different value.',
]);
