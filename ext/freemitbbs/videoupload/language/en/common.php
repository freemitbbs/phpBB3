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
	'VIDEOUPLOAD_BUTTON' => 'Upload images and media',
	'VIDEOUPLOAD_MULTI_BUTTON' => 'Upload image set',
	'VIDEOUPLOAD_MULTI_HELP' => 'Upload multiple images and insert them into the editor.',
	'VIDEOUPLOAD_MULTI_EMPTY' => 'Choose one or more image files first.',
	'VIDEOUPLOAD_MULTI_UPLOADING' => 'Uploading image %1$s of %2$s...',
	'VIDEOUPLOAD_MULTI_SUCCESS' => '%1$s image(s) uploaded. BBCode inserted into editor.',
	'VIDEOUPLOAD_MULTI_PARTIAL' => '%1$s image(s) uploaded, %2$s failed. Successful BBCode inserted into editor.',
	'VIDEOUPLOAD_HELP_WITH_LIMIT' => 'Supported formats: %1$s. Max file size: %2$s.',
	'VIDEOUPLOAD_UPLOADING' => 'Uploading file...',
	'VIDEOUPLOAD_UPLOAD_SUCCESS' => 'File uploaded. Link inserted into editor.',
	'VIDEOUPLOAD_ERR_SERVER' => 'Upload failed. Please try again.',
	'VIDEOUPLOAD_ERR_DISABLED' => 'Image and media upload is disabled.',
	'VIDEOUPLOAD_ERR_LOGIN_REQUIRED' => 'Please sign in to upload files.',
	'VIDEOUPLOAD_ERR_METHOD' => 'Invalid request method.',
	'VIDEOUPLOAD_ERR_FORM' => 'Invalid form token.',
	'VIDEOUPLOAD_ERR_FORUM' => 'Invalid forum.',
	'VIDEOUPLOAD_ERR_PERMISSION' => 'You do not have permission to upload files in this forum.',
	'VIDEOUPLOAD_ERR_MISSING_CONFIG' => 'Image and media upload storage is not configured.',
	'VIDEOUPLOAD_ERR_NO_FILE' => 'No file selected.',
	'VIDEOUPLOAD_ERR_INVALID_UPLOAD' => 'Uploaded file is invalid.',
	'VIDEOUPLOAD_ERR_INVALID_IMAGE' => 'Uploaded file content is invalid or does not match its file extension.',
	'VIDEOUPLOAD_ERR_UNSUPPORTED_VIDEO_CODEC' => 'This video uses HEVC/H.265, which many browsers cannot play here. Please upload a compatible H.264 MP4 instead. On iPhone, use Settings > Camera > Formats > Most Compatible.',
	'VIDEOUPLOAD_ERR_FILE_TOO_LARGE_PHP' => 'The uploaded file exceeds server upload limits.',
	'VIDEOUPLOAD_ERR_PARTIAL' => 'The file was only partially uploaded.',
	'VIDEOUPLOAD_ERR_DISK' => 'The server could not save the uploaded file.',
	'VIDEOUPLOAD_ERR_EXTENSION' => 'A server extension rejected the upload.',
	'VIDEOUPLOAD_ERR_UPLOAD_FAILED' => 'Upload failed.',
	'VIDEOUPLOAD_ERR_TOO_LARGE' => 'File is too large. Maximum allowed size is %1$s.',
	'VIDEOUPLOAD_ERR_PHP_LIMIT' => 'Upload exceeds PHP server limit (%1$s). Increase upload_max_filesize and post_max_size.',
	'VIDEOUPLOAD_ERR_UNSUPPORTED_EXTENSION' => 'Only %1$s files are supported.',
	'VIDEOUPLOAD_ERR_BAD_URL' => 'Upload completed, but the resulting URL does not end with a supported file extension.',
]);
