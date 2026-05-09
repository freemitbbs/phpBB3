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
	'ACP_VIDEOUPLOAD' => '图片和媒体上载',
	'ACP_VIDEOUPLOAD_GRP' => '图片和媒体上载',

	'VIDEOUPLOAD_EXPLAIN' => '将图片、视频和音频上载到兼容 S3 的存储，并在编辑器中插入可直接用于发帖的链接。',
	'VIDEOUPLOAD_FIELDSET_GENERAL' => '基本设置',
	'VIDEOUPLOAD_FIELDSET_S3' => 'S3 存储设置',

	'VIDEOUPLOAD_ENABLED' => '启用图片和媒体上载',
	'VIDEOUPLOAD_ENABLED_EXPLAIN' => '开启或关闭发帖编辑器中的图片/媒体上载控件。',
	'VIDEOUPLOAD_MAX_SIZE_MB' => '最大文件大小（MB）',
	'VIDEOUPLOAD_MAX_SIZE_MB_EXPLAIN' => '单个图片或媒体文件允许的最大上载大小。',

	'VIDEOUPLOAD_S3_ENDPOINT' => 'S3 端点 URL',
	'VIDEOUPLOAD_S3_ENDPOINT_EXPLAIN' => '例如：https://s3.amazonaws.com 或你的 S3 兼容服务端点。',
	'VIDEOUPLOAD_S3_REGION' => 'S3 区域',
	'VIDEOUPLOAD_S3_REGION_EXPLAIN' => '例如：us-east-1。',
	'VIDEOUPLOAD_S3_BUCKET' => 'Bucket 名称',
	'VIDEOUPLOAD_S3_BUCKET_EXPLAIN' => '上载图片和媒体文件要存放的 Bucket。',
	'VIDEOUPLOAD_S3_ACCESS_KEY' => 'Access Key ID',
	'VIDEOUPLOAD_S3_ACCESS_KEY_EXPLAIN' => '用于 S3 API 鉴权的访问密钥。',
	'VIDEOUPLOAD_S3_SECRET_KEY' => 'Secret Access Key',
	'VIDEOUPLOAD_S3_SECRET_KEY_EXPLAIN' => '留空表示保留当前已保存的密钥。',
	'VIDEOUPLOAD_S3_SECRET_KEY_CLEAR' => '清除已保存的 Secret Key',
	'VIDEOUPLOAD_S3_PATH_PREFIX' => '对象键前缀',
	'VIDEOUPLOAD_S3_PATH_PREFIX_EXPLAIN' => '可选目录前缀，例如：media。',
	'VIDEOUPLOAD_S3_PUBLIC_BASE_URL' => '公开访问基础 URL（可选）',
	'VIDEOUPLOAD_S3_PUBLIC_BASE_URL_EXPLAIN' => '可选，用于拼接返回的公开 URL。留空则按端点配置自动推导。',
	'VIDEOUPLOAD_S3_USE_PATH_STYLE' => '使用 Path-Style Bucket URL',
	'VIDEOUPLOAD_S3_USE_PATH_STYLE_EXPLAIN' => '对于需要 endpoint/bucket/key 格式的服务请启用。',
	'VIDEOUPLOAD_S3_ACL' => '对象 ACL 头',
	'VIDEOUPLOAD_S3_ACL_EXPLAIN' => '作为 x-amz-acl 发送（如：public-read）。除非服务商要求，否则保持默认。',
]);
