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
	'ACP_S3STORAGE' => 'S3 Storage',
	'ACP_S3STORAGE_GRP' => 'S3 Storage',

	'S3STORAGE_EXPLAIN' => 'Shared S3-compatible storage settings used by FreeMitBBS extensions.',
	'S3STORAGE_FIELDSET_CONNECTION' => 'Connection settings',

	'S3STORAGE_ENDPOINT' => 'S3 endpoint URL',
	'S3STORAGE_ENDPOINT_EXPLAIN' => 'For example: https://s3.amazonaws.com or your S3-compatible provider endpoint.',
	'S3STORAGE_REGION' => 'S3 region',
	'S3STORAGE_REGION_EXPLAIN' => 'For example: us-east-1.',
	'S3STORAGE_BUCKET' => 'Bucket name',
	'S3STORAGE_BUCKET_EXPLAIN' => 'Bucket used by extensions that rely on shared object storage.',
	'S3STORAGE_ACCESS_KEY' => 'Access key ID',
	'S3STORAGE_ACCESS_KEY_EXPLAIN' => 'Credential used for S3 API authentication.',
	'S3STORAGE_SECRET_KEY' => 'Secret access key',
	'S3STORAGE_SECRET_KEY_EXPLAIN' => 'Leave blank to keep the current stored secret.',
	'S3STORAGE_SECRET_KEY_CONFIGURED' => 'A secret key is currently stored.',
	'S3STORAGE_SECRET_KEY_CLEAR' => 'Clear stored secret key',
	'S3STORAGE_PUBLIC_BASE_URL' => 'Public base URL (optional)',
	'S3STORAGE_PUBLIC_BASE_URL_EXPLAIN' => 'Optional override used to build public object URLs. Leave empty to derive from endpoint settings.',
	'S3STORAGE_USE_PATH_STYLE' => 'Use path-style bucket URLs',
	'S3STORAGE_USE_PATH_STYLE_EXPLAIN' => 'Enable for providers that require endpoint/bucket/key URL format.',
]);
