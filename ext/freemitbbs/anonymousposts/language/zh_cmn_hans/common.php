<?php

if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang, [
	'ANONPOST_OPTION' => '匿名发布',
	'ANONPOST_EXPLAIN' => '对其他读者隐藏账号、头像和签名；版主及管理员仍可核查作者。',
	'ANONPOST_LOCKED' => '此帖以匿名方式发布，编辑后仍保持匿名。',
	'ANONPOST_UNAVAILABLE' => '此处不能使用匿名发布。仅支持在允许匿名的版块发表新主题或回复。',
	'ANONPOST_AUTHOR' => '匿名帖实际作者（仅版主可见）',
	'ANONPOST_REUPLOAD' => '此附件的旧存储地址包含账号信息。请删除附件并重新上传，再匿名发布。',
]);
