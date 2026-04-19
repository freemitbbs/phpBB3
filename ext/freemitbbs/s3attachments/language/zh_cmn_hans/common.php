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
	'S3ATTACHMENTS_SETTINGS' => 'S3 附件存储',
	'S3ATTACHMENTS_ENABLED' => '将附件存储到 S3',
	'S3ATTACHMENTS_ENABLED_EXPLAIN' => '保留 phpBB 附件数据库元数据，但在附件添加成功后立即将实际文件上传到共享的兼容 S3 存储。',
	'S3ATTACHMENTS_PATH_PREFIX' => '对象键前缀',
	'S3ATTACHMENTS_PATH_PREFIX_EXPLAIN' => '附件对象的可选目录前缀，例如：attachments。',
	'S3ATTACHMENTS_SIGNED_URLS' => '使用签名下载链接',
	'S3ATTACHMENTS_SIGNED_URLS_EXPLAIN' => '推荐开启。启用后，phpBB 在完成权限检查后会将附件下载重定向到短时有效的签名 S3 URL。',
	'S3ATTACHMENTS_SIGNED_URL_TTL' => '签名链接有效期（秒）',
	'S3ATTACHMENTS_SIGNED_URL_TTL_EXPLAIN' => '每个生成的下载链接可使用的时长。',
	'S3ATTACHMENTS_ACL' => '对象 ACL 头',
	'S3ATTACHMENTS_ACL_EXPLAIN' => '上传时作为 x-amz-acl 发送。依赖签名 URL 时建议使用 <samp>private</samp>；对于不接受显式 private ACL 的服务商，这会省略 ACL 头。',
	'S3ATTACHMENTS_UPLOAD_UPLOADING' => '正在上传附件……',
	'S3ATTACHMENTS_UPLOAD_SUCCESS' => '附件已上传。',
	'S3ATTACHMENTS_UPLOAD_ERROR' => '附件上传失败，请重试。',
	'S3ATTACHMENTS_UPLOAD_WAIT' => '请等待附件上传完成。',
]);
