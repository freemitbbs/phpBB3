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
	'ACP_CARDGAMESAUTH' => '卡牌游戏认证桥',
	'ACP_CARDGAMESAUTH_GRP' => '卡牌游戏',
	'ACP_CARDGAMESAUTH_SETTINGS' => '卡牌游戏设置',

	'CARDGAMESAUTH_EXPLAIN' => '本地卡牌游戏静态客户端与游戏服务器使用的 phpBB 会话桥设置。',
	'CARDGAMESAUTH_FIELDSET_GENERAL' => '通用设置',
	'CARDGAMESAUTH_FIELDSET_CLIENT' => '客户端与游戏服务器',
	'CARDGAMESAUTH_FIELDSET_TOKEN' => '令牌签发',

	'CARDGAMESAUTH_ENABLED' => '启用认证桥',
	'CARDGAMESAUTH_ENABLED_EXPLAIN' => '允许有权限的已登录用户请求卡牌游戏认证令牌。',
	'CARDGAMESAUTH_NAV_ENABLED' => '显示导航链接',
	'CARDGAMESAUTH_NAV_ENABLED_EXPLAIN' => '在论坛页头的博客链接旁边显示卡牌游戏链接。',
	'CARDGAMESAUTH_CLIENT_URL' => '客户端 URL 或路径',
	'CARDGAMESAUTH_CLIENT_URL_EXPLAIN' => '静态卡牌游戏客户端的路径或 URL，例如 /card-games/client/。客户端资源尚未存在时可留空。',
	'CARDGAMESAUTH_LAUNCH_REDIRECT' => '启动页直接跳转到客户端',
	'CARDGAMESAUTH_LAUNCH_REDIRECT_EXPLAIN' => '启用后，允许访问的用户打开 /card-games 时会直接跳转到配置的客户端 URL。',
	'CARDGAMESAUTH_WS_URL' => 'WebSocket URL',
	'CARDGAMESAUTH_WS_URL_EXPLAIN' => '公开的游戏服务器 WebSocket URL。留空时默认使用 ws(s)://当前论坛/card-games/ws。',
	'CARDGAMESAUTH_TOKEN_TTL' => '令牌有效期',
	'CARDGAMESAUTH_TOKEN_TTL_EXPLAIN' => '签发的认证令牌过期秒数。允许范围：30-600。',
	'CARDGAMESAUTH_TOKEN_RATE_LIMIT' => '令牌请求上限',
	'CARDGAMESAUTH_TOKEN_RATE_LIMIT_EXPLAIN' => '每个用户会话在限流窗口内最多可请求的令牌数量。允许范围：1-120。',
	'CARDGAMESAUTH_TOKEN_RATE_WINDOW' => '令牌限流窗口',
	'CARDGAMESAUTH_TOKEN_RATE_WINDOW_EXPLAIN' => '限流窗口秒数。允许范围：10-3600。',
]);
