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
	'ACP_ADULTACCESS_GRP' => '成人访问控制',
	'ACP_ADULTACCESS' => '成人版块访问控制',
	'ADULTACCESS_FORUM_IDS' => '成人版块 ID',
	'ADULTACCESS_FORUM_IDS_EXPLAIN' => '填写逗号分隔的帖子版块 ID。保存后会把当前可读取的普通会员权限复制给隐藏的 18+ 确认用户组，移除非管理人员的版块可见性授权，并保留其他版块权限。',
	'ADULTACCESS_GROUP_LABEL' => '隐藏确认用户组',
	'ADULTACCESS_SYNC_EXPLAIN' => '此扩展通过隐藏用户组控制这些版块的访问。版块受控期间，Adult Access 管理版块可见性权限；将版块移出此列表时会保留其他权限修改。',
	'ADULTACCESS_FORUM_STATUS' => '版块状态',
	'ADULTACCESS_ADULT_GROUP_ACCESS' => '18+ 用户组访问',
	'ADULTACCESS_BLOCKED_GROUPS' => '可见性绕过项',
	'ADULTACCESS_OTHER_GROUPS' => '其他 ACL 用户组',
	'ADULTACCESS_FORUM_MISSING' => '版块不存在或不是帖子版块',
	'ADULTACCESS_CONFIG_UPDATED_INVALID' => '设置已保存。以下无效版块 ID 已忽略：%s',
	'ADULTACCESS_CONFIG_UPDATED_SKIPPED' => '以下版块 ID 未能设置访问控制：%s',
	'ADULTACCESS_SKIP_FORUM_MISSING' => '版块不存在或不是帖子版块',
	'ADULTACCESS_SKIP_GROUP_MISSING' => '隐藏确认用户组不存在',
	'ADULTACCESS_SKIP_NO_SOURCE' => '没有可读取的普通会员或现有 18+ 权限作为来源',
]);
