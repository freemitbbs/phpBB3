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
	'UCP_ADULTACCESS' => '成人内容访问',
	'ADULTACCESS_CONFIRM_TITLE' => '成人版块访问确认',
	'ADULTACCESS_CONFIRM_EXPLAIN' => '此页面用于控制你的账号是否显示成人内容版块。',
	'ADULTACCESS_ATTESTATION' => '我确认自己已年满 18 周岁，且浏览成人内容版块不违反我所在地区适用的法律法规。',
	'ADULTACCESS_CONFIRM_BUTTON' => '我确认',
	'ADULTACCESS_CANCEL_BUTTON' => '取消',
	'ADULTACCESS_ENABLED_TITLE' => '成人版块访问已开启',
	'ADULTACCESS_ENABLED_EXPLAIN' => '你的账号当前可以看到成人内容版块。关闭后将立即隐藏这些版块，并移除相关收藏与订阅。',
	'ADULTACCESS_DISABLE_BUTTON' => '关闭访问权限',
	'ADULTACCESS_KEEP_BUTTON' => '保持开启',
	'ADULTACCESS_LAST_CONFIRMED' => '上次确认时间',
	'ADULTACCESS_NOT_READY' => '成人内容访问当前不可用，请联系论坛管理员。',
	'ADULTACCESS_NO_FORUMS_CONFIGURED' => '管理员尚未配置成人内容版块。',
	'ADULTACCESS_RETURN_BUTTON' => '返回用户控制面板',
	'ADULTACCESS_OPT_IN_SAVED' => '你的账号已开启成人内容版块访问权限。',
	'ADULTACCESS_OPT_OUT_SAVED' => '你的账号已关闭成人内容版块访问权限。',
]);
