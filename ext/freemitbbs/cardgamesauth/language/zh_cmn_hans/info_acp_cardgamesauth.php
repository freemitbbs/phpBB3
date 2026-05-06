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
	'CARDGAMESAUTH_OPEN_CONTROL' => '打开实时控制页',
	'CARDGAMESAUTH_OPEN_CONTROL_EXPLAIN' => '用于重置房间、断开用户、取消游戏和导出回放。',
	'CARDGAMESAUTH_FIELDSET_GENERAL' => '通用设置',
	'CARDGAMESAUTH_FIELDSET_CLIENT' => '客户端与游戏服务器',
	'CARDGAMESAUTH_FIELDSET_TOKEN' => '令牌签发',
	'CARDGAMESAUTH_FIELDSET_TESTERS' => '测试访问',
	'CARDGAMESAUTH_FIELDSET_PROXY' => '服务器数据库代理',
	'CARDGAMESAUTH_FIELDSET_RUNTIME_HOOKS' => '运行时 Hook',

	'CARDGAMESAUTH_ENABLED' => '启用认证桥',
	'CARDGAMESAUTH_ENABLED_EXPLAIN' => '允许有权限的已登录用户请求卡牌游戏认证令牌。',
	'CARDGAMESAUTH_NAV_ENABLED' => '显示导航链接',
	'CARDGAMESAUTH_NAV_ENABLED_EXPLAIN' => '在论坛页头的博客链接旁边显示卡牌游戏链接。',
	'CARDGAMESAUTH_TESTING_MODE' => '测试模式',
	'CARDGAMESAUTH_TESTING_MODE_EXPLAIN' => '启用后，普通用户看不到卡牌游戏；只有测试组用户可以打开客户端或获取游戏令牌。',
	'CARDGAMESAUTH_CLIENT_URL' => '客户端 URL 或路径',
	'CARDGAMESAUTH_CLIENT_URL_EXPLAIN' => '卡牌游戏客户端的路径或 URL。留空时使用扩展内置客户端 /card-games/client。',
	'CARDGAMESAUTH_LAUNCH_REDIRECT' => '启动页直接跳转到客户端',
	'CARDGAMESAUTH_LAUNCH_REDIRECT_EXPLAIN' => '启用后，允许访问的用户打开 /card-games 时会直接跳转到配置的客户端 URL。',
	'CARDGAMESAUTH_WS_URL' => 'WebSocket URL',
	'CARDGAMESAUTH_WS_URL_EXPLAIN' => '公开的游戏服务器 WebSocket URL。留空时默认使用 ws(s)://当前论坛/card-games/ws。',
	'CARDGAMESAUTH_TOKEN_SECRET' => '游戏认证令牌密钥',
	'CARDGAMESAUTH_TOKEN_SECRET_EXPLAIN' => '用于签名 phpBB 签发的游戏认证令牌的共享密钥。游戏服务器的 GAME_AUTH_TOKEN_SECRET 必须使用同一个值。请保密；修改后现有令牌会失效，并且需要同步更新游戏服务器。',
	'CARDGAMESAUTH_TOKEN_TTL' => '令牌有效期',
	'CARDGAMESAUTH_TOKEN_TTL_EXPLAIN' => '签发的认证令牌过期秒数。允许范围：30-600。',
	'CARDGAMESAUTH_TOKEN_RATE_LIMIT' => '令牌请求上限',
	'CARDGAMESAUTH_TOKEN_RATE_LIMIT_EXPLAIN' => '每个用户会话在限流窗口内最多可请求的令牌数量。允许范围：1-120。',
	'CARDGAMESAUTH_TOKEN_RATE_WINDOW' => '令牌限流窗口',
	'CARDGAMESAUTH_TOKEN_RATE_WINDOW_EXPLAIN' => '限流窗口秒数。允许范围：10-3600。',
	'CARDGAMESAUTH_TOKEN_CLOCK_TOLERANCE' => '令牌时钟容差',
	'CARDGAMESAUTH_TOKEN_CLOCK_TOLERANCE_EXPLAIN' => '服务器代理校验游戏令牌时允许的时钟偏差秒数。允许范围：0-300。',
	'CARDGAMESAUTH_TESTER_GROUP' => '测试组',
	'CARDGAMESAUTH_TESTER_GROUP_EXPLAIN' => '测试模式使用的隐藏 phpBB 用户组。',
	'CARDGAMESAUTH_ADD_TESTERS' => '添加测试用户',
	'CARDGAMESAUTH_ADD_TESTERS_EXPLAIN' => '输入一个或多个准确用户名，用逗号或换行分隔。已经在测试组中的用户会被忽略。',
	'CARDGAMESAUTH_CURRENT_TESTERS' => '当前测试用户',
	'CARDGAMESAUTH_CURRENT_TESTERS_EXPLAIN' => '如需移除用户，请通过 phpBB 用户/用户组管理操作。',
	'CARDGAMESAUTH_NO_TESTERS' => '尚未添加测试用户。',
	'CARDGAMESAUTH_TESTER_USERS_INVALID' => '由于以下用户名不存在或未激活，未添加测试用户：%s',
	'CARDGAMESAUTH_TESTER_ADD_FAILED' => '无法添加测试用户：%s',
	'CARDGAMESAUTH_PROXY_ENABLED' => '启用服务器代理',
	'CARDGAMESAUTH_PROXY_ENABLED_EXPLAIN' => '允许外部游戏服务器调用经过认证的 /card-games/server/* JSON 接口。',
	'CARDGAMESAUTH_PROXY_SECRET' => '代理 HMAC 密钥',
	'CARDGAMESAUTH_PROXY_SECRET_EXPLAIN' => '外部游戏服务器用于签名代理请求的共享密钥。请保密；修改后需要同步更新游戏服务器。',
	'CARDGAMESAUTH_PROXY_CLOCK_SKEW' => '代理时间戳窗口',
	'CARDGAMESAUTH_PROXY_CLOCK_SKEW_EXPLAIN' => '允许的请求时间戳偏差秒数。允许范围：30-3600。',
	'CARDGAMESAUTH_PROXY_NONCE_TTL' => '代理 nonce 有效期',
	'CARDGAMESAUTH_PROXY_NONCE_TTL_EXPLAIN' => '为防重放而记住签名请求 nonce 的秒数。允许范围：30-3600。',
	'CARDGAMESAUTH_PROXY_MAX_BODY_BYTES' => '代理最大请求体字节数',
	'CARDGAMESAUTH_PROXY_MAX_BODY_BYTES_EXPLAIN' => '服务器代理接口接受的最大 JSON 请求体大小。允许范围：1024-1048576。',
	'CARDGAMESAUTH_NODE_RUNTIME_ENABLED' => '启用 Node 运行时 Hook',
	'CARDGAMESAUTH_NODE_RUNTIME_ENABLED_EXPLAIN' => '允许 phpBB 管理控制调用正在运行的 Node 游戏服务器执行实时状态操作。',
	'CARDGAMESAUTH_NODE_RUNTIME_BASE_URL' => 'Node 运行时基础 URL',
	'CARDGAMESAUTH_NODE_RUNTIME_BASE_URL_EXPLAIN' => 'Node 运行时 Hook API 的基础 URL，例如 https://freemitbbs-card-games.fly.dev。不要包含具体操作路径。',
	'CARDGAMESAUTH_NODE_RUNTIME_SERVICE_ID' => 'Node 运行时服务 ID',
	'CARDGAMESAUTH_NODE_RUNTIME_SERVICE_ID_EXPLAIN' => '发送到 x-cardgames-service 的服务标识。Node 服务器默认值为 phpbb-cardgamesauth。',
	'CARDGAMESAUTH_NODE_RUNTIME_SERVICE_SECRET' => 'Node 运行时服务密钥',
	'CARDGAMESAUTH_NODE_RUNTIME_SERVICE_SECRET_EXPLAIN' => 'phpBB 用于签名运行时 Hook 请求的共享 HMAC 密钥。需与 Node 服务器的 RUNTIME_HOOK_SERVICE_SECRET 保持一致。',
	'CARDGAMESAUTH_NODE_RUNTIME_TIMEOUT_MS' => 'Node 运行时超时',
	'CARDGAMESAUTH_NODE_RUNTIME_TIMEOUT_MS_EXPLAIN' => '等待运行时 Hook 响应的毫秒数。允许范围：1000-30000。',
]);
