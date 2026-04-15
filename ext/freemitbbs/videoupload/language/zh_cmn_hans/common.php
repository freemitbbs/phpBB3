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
	'VIDEOUPLOAD_BUTTON' => '上传视频',
	'VIDEOUPLOAD_HELP_WITH_LIMIT' => '支持格式：%1$s。单个文件最大：%2$s。',
	'VIDEOUPLOAD_UPLOADING' => '正在上传视频...',
	'VIDEOUPLOAD_UPLOAD_SUCCESS' => '视频上传成功，链接已插入编辑器。',
	'VIDEOUPLOAD_ERR_SERVER' => '上传失败，请稍后重试。',
	'VIDEOUPLOAD_ERR_DISABLED' => '视频上传功能已关闭。',
	'VIDEOUPLOAD_ERR_LOGIN_REQUIRED' => '请先登录再上传视频。',
	'VIDEOUPLOAD_ERR_METHOD' => '请求方法无效。',
	'VIDEOUPLOAD_ERR_FORM' => '表单令牌无效。',
	'VIDEOUPLOAD_ERR_FORUM' => '版面参数无效。',
	'VIDEOUPLOAD_ERR_PERMISSION' => '你没有在该版面上传视频的权限。',
	'VIDEOUPLOAD_ERR_MISSING_CONFIG' => '视频上传存储配置不完整。',
	'VIDEOUPLOAD_ERR_NO_FILE' => '未选择文件。',
	'VIDEOUPLOAD_ERR_INVALID_UPLOAD' => '上传文件无效。',
	'VIDEOUPLOAD_ERR_FILE_TOO_LARGE_PHP' => '上传文件超过服务器限制。',
	'VIDEOUPLOAD_ERR_PARTIAL' => '文件仅部分上传。',
	'VIDEOUPLOAD_ERR_DISK' => '服务器无法保存上传文件。',
	'VIDEOUPLOAD_ERR_EXTENSION' => '服务器扩展拒绝了上传。',
	'VIDEOUPLOAD_ERR_UPLOAD_FAILED' => '上传失败。',
	'VIDEOUPLOAD_ERR_TOO_LARGE' => '文件过大。允许的最大大小是 %1$s。',
	'VIDEOUPLOAD_ERR_PHP_LIMIT' => '上传超过 PHP 服务器限制（%1$s）。请增大 upload_max_filesize 与 post_max_size。',
	'VIDEOUPLOAD_ERR_UNSUPPORTED_EXTENSION' => '仅支持 %1$s 格式文件。',
	'VIDEOUPLOAD_ERR_BAD_URL' => '上传完成，但返回链接不是以 .mp4、.ogg 或 .webm 结尾。',
]);
