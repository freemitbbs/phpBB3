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
	'VIDEOUPLOAD_BUTTON' => '上载图片和媒体',
	'VIDEOUPLOAD_MULTI_BUTTON' => '上载组图',
	'VIDEOUPLOAD_MULTI_HELP' => '一次选择多张图片并插入编辑器。',
	'VIDEOUPLOAD_MULTI_EMPTY' => '请先选择一张或多张图片。',
	'VIDEOUPLOAD_MULTI_UPLOADING' => '正在上载第 %1$s / %2$s 张图片...',
	'VIDEOUPLOAD_MULTI_SUCCESS' => '%1$s 张图片已上载，BBCode 已插入编辑器。',
	'VIDEOUPLOAD_MULTI_PARTIAL' => '%1$s 张图片已上载，%2$s 张失败。成功的 BBCode 已插入编辑器。',
	'VIDEOUPLOAD_HELP_WITH_LIMIT' => '支持格式：%1$s。单个文件最大：%2$s。',
	'VIDEOUPLOAD_UPLOADING' => '正在上载文件...',
	'VIDEOUPLOAD_UPLOAD_SUCCESS' => '文件上载成功，链接已插入编辑器。',
	'VIDEOUPLOAD_ERR_SERVER' => '上载失败，请稍后重试。',
	'VIDEOUPLOAD_ERR_DISABLED' => '图片和媒体上载功能已关闭。',
	'VIDEOUPLOAD_ERR_LOGIN_REQUIRED' => '请先登录再上载文件。',
	'VIDEOUPLOAD_ERR_METHOD' => '请求方法无效。',
	'VIDEOUPLOAD_ERR_FORM' => '表单令牌无效。',
	'VIDEOUPLOAD_ERR_FORUM' => '版面参数无效。',
	'VIDEOUPLOAD_ERR_PERMISSION' => '你没有在该版面上载文件的权限。',
	'VIDEOUPLOAD_ERR_MISSING_CONFIG' => '图片和媒体上载存储配置不完整。',
	'VIDEOUPLOAD_ERR_NO_FILE' => '未选择文件。',
	'VIDEOUPLOAD_ERR_INVALID_UPLOAD' => '上载文件无效。',
	'VIDEOUPLOAD_ERR_INVALID_IMAGE' => '上载文件内容无效，或与文件扩展名不匹配。',
	'VIDEOUPLOAD_ERR_UNSUPPORTED_VIDEO_CODEC' => '该视频使用 HEVC/H.265 编码，很多浏览器无法在这里播放。请上传兼容的 H.264 MP4。iPhone 可在“设置 > 相机 > 格式”中选择“兼容性最佳”。',
	'VIDEOUPLOAD_ERR_FILE_TOO_LARGE_PHP' => '上载文件超过服务器限制。',
	'VIDEOUPLOAD_ERR_PARTIAL' => '文件仅部分上载。',
	'VIDEOUPLOAD_ERR_DISK' => '服务器无法保存上传文件。',
	'VIDEOUPLOAD_ERR_EXTENSION' => '服务器扩展拒绝了上传。',
	'VIDEOUPLOAD_ERR_UPLOAD_FAILED' => '上载失败。',
	'VIDEOUPLOAD_ERR_TOO_LARGE' => '文件过大。允许的最大大小是 %1$s。',
	'VIDEOUPLOAD_ERR_PHP_LIMIT' => '上载超过 PHP 服务器限制（%1$s）。请增大 upload_max_filesize 与 post_max_size。',
	'VIDEOUPLOAD_ERR_UNSUPPORTED_EXTENSION' => '仅支持 %1$s 格式文件。',
	'VIDEOUPLOAD_ERR_BAD_URL' => '上载完成，但返回链接的扩展名不在受支持的格式内。',
]);
