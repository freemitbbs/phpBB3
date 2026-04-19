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
	'ACP_S3STORAGE' => 'S3 存储',
	'ACP_S3STORAGE_GRP' => 'S3 存储',

	'S3STORAGE_EXPLAIN' => '供 FreeMitBBS 扩展共享使用的兼容 S3 存储设置。',
	'S3STORAGE_FIELDSET_CONNECTION' => '连接设置',

	'S3STORAGE_ENDPOINT' => 'S3 端点 URL',
	'S3STORAGE_ENDPOINT_EXPLAIN' => '例如：https://s3.amazonaws.com 或你的 S3 兼容服务端点。',
	'S3STORAGE_REGION' => 'S3 区域',
	'S3STORAGE_REGION_EXPLAIN' => '例如：us-east-1。',
	'S3STORAGE_BUCKET' => 'Bucket 名称',
	'S3STORAGE_BUCKET_EXPLAIN' => '供依赖共享对象存储的扩展使用的 Bucket。',
	'S3STORAGE_ACCESS_KEY' => 'Access Key ID',
	'S3STORAGE_ACCESS_KEY_EXPLAIN' => '用于 S3 API 鉴权的访问密钥。',
	'S3STORAGE_SECRET_KEY' => 'Secret Access Key',
	'S3STORAGE_SECRET_KEY_EXPLAIN' => '留空表示保留当前已保存的密钥。',
	'S3STORAGE_SECRET_KEY_CONFIGURED' => '当前已保存 Secret Key。',
	'S3STORAGE_SECRET_KEY_CLEAR' => '清除已保存的 Secret Key',
	'S3STORAGE_PUBLIC_BASE_URL' => '公开访问基础 URL（可选）',
	'S3STORAGE_PUBLIC_BASE_URL_EXPLAIN' => '可选，用于拼接公开对象 URL。留空则按端点配置自动推导。',
	'S3STORAGE_USE_PATH_STYLE' => '使用 Path-Style Bucket URL',
	'S3STORAGE_USE_PATH_STYLE_EXPLAIN' => '对于需要 endpoint/bucket/key 格式的服务请启用。',
]);
