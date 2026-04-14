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
	'ACP_RISKWATCH_GRP' => '风险观察',
	'ACP_RISKWATCH' => '风险观察',
	'RISKWATCH_EXPLAIN' => '基于 phpBB 核心的版务与滥用信号，聚合计算用户风险分，用于版务分诊。',
	'RISKWATCH_FIELDSET_SETTINGS' => '评分与刷新设置',
	'RISKWATCH_FIELDSET_MANUAL' => '手动风险调整',
	'RISKWATCH_TOP_USERS' => '风险最高用户',
	'RISKWATCH_RECOMPUTE_NOW' => '立即重算',
	'RISKWATCH_RECOMPUTE_RESULT' => '已为 %d 位用户重算风险状态。',
	'RISKWATCH_NO_DATA' => '当前没有风险分非零的用户。',

	'RISKWATCH_MANUAL_USER_ID' => '目标用户 ID',
	'RISKWATCH_MANUAL_USER_ID_EXPLAIN' => '要调整的用户数字 ID。',
	'RISKWATCH_MANUAL_DELTA' => '分值增量',
	'RISKWATCH_MANUAL_DELTA_EXPLAIN' => '正数提高风险，负数降低风险，不能为 0。',
	'RISKWATCH_MANUAL_REASON' => '原因',
	'RISKWATCH_MANUAL_REASON_EXPLAIN' => '必填审计原因（最多 255 字符）。',
	'RISKWATCH_MANUAL_EXPIRES_DAYS' => '多少天后过期',
	'RISKWATCH_MANUAL_EXPIRES_DAYS_EXPLAIN' => '填 0 表示不过期。',
	'RISKWATCH_MANUAL_ADD' => '添加手动调整',
	'RISKWATCH_MANUAL_ADD_SUCCESS' => '已为 %1$s 保存手动调整（增量：%2$s）。',
	'RISKWATCH_MANUAL_DISABLE_SUCCESS' => '已停用 %1$s 的手动调整。',
	'RISKWATCH_MANUAL_ACTIVE' => '生效中的手动调整',
	'RISKWATCH_MANUAL_CREATED_BY' => '创建人',
	'RISKWATCH_MANUAL_CREATED_TIME' => '创建时间',
	'RISKWATCH_MANUAL_EXPIRES_AT' => '过期时间',
	'RISKWATCH_MANUAL_STATUS' => '状态',
	'RISKWATCH_MANUAL_STATUS_ACTIVE' => '生效中',
	'RISKWATCH_MANUAL_STATUS_EXPIRED' => '已过期',
	'RISKWATCH_MANUAL_DISABLE' => '停用',
	'RISKWATCH_MANUAL_NONE' => '当前没有生效中的手动调整。',

	'RISKWATCH_ERR_MANUAL_USER_REQUIRED' => '手动调整需要有效的目标用户 ID。',
	'RISKWATCH_ERR_MANUAL_USER_NOT_FOUND' => '用户 ID %d 不存在。',
	'RISKWATCH_ERR_MANUAL_DELTA_REQUIRED' => '手动调整增量不能为 0。',
	'RISKWATCH_ERR_MANUAL_REASON_REQUIRED' => '手动调整原因不能为空。',
	'RISKWATCH_ERR_MANUAL_NOT_FOUND' => '未找到该手动调整记录，或其已停用。',

	'RISKWATCH_RISK_SCORE' => '风险分',
	'RISKWATCH_RISK_LEVEL' => '等级',
	'RISKWATCH_WARNINGS' => '警告数',
	'RISKWATCH_REPORTERS_30D' => '未处理举报人（30 天）',
	'RISKWATCH_UNAPPROVED_30D' => '未审核帖子（30 天）',
	'RISKWATCH_LOGIN_ATTEMPTS' => '登录尝试',
	'RISKWATCH_ACTIVE_BAN' => '生效封禁',
	'RISKWATCH_MANUAL_ADJUSTMENT' => '手动调整',
	'RISKWATCH_LAST_COMPUTED' => '最近计算',

	'RISKWATCH_LEVEL_NORMAL' => '正常',
	'RISKWATCH_LEVEL_WATCH' => '关注',
	'RISKWATCH_LEVEL_HIGH' => '高',
	'RISKWATCH_LEVEL_CRITICAL' => '严重',

	'RISKWATCH_REFRESH_SECONDS' => '刷新间隔（秒）',
	'RISKWATCH_REFRESH_SECONDS_EXPLAIN' => 'CRON 重算间隔。建议：300 秒。',
	'RISKWATCH_REFRESH_BATCH_SIZE' => '刷新批量大小',
	'RISKWATCH_REFRESH_BATCH_SIZE_EXPLAIN' => '一次 CRON 运行最多重算多少候选用户。',
	'RISKWATCH_ALERT_COOLDOWN_SECONDS' => '告警冷却（秒）',
	'RISKWATCH_ALERT_COOLDOWN_SECONDS_EXPLAIN' => '同一用户同一等级在冷却期内不重复告警。',
	'RISKWATCH_REPORTS_DAYS' => '未处理举报窗口（天）',
	'RISKWATCH_REPORTS_DAYS_EXPLAIN' => '仅统计该时间窗内的未处理举报。',
	'RISKWATCH_UNAPPROVED_DAYS' => '未审核帖子窗口（天）',
	'RISKWATCH_UNAPPROVED_DAYS_EXPLAIN' => '仅统计该时间窗内未审核/待复审帖子。',
	'RISKWATCH_IGNORE_NEW_REPORTERS_DAYS' => '忽略新举报用户（天）',
	'RISKWATCH_IGNORE_NEW_REPORTERS_DAYS_EXPLAIN' => '大于 0 时，会排除注册时长小于该值的举报用户，不计入“举报人多样性”。',

	'RISKWATCH_THRESHOLD_WATCH' => '关注阈值',
	'RISKWATCH_THRESHOLD_WATCH_EXPLAIN' => '分数达到或超过该值，标记为“关注”。',
	'RISKWATCH_THRESHOLD_HIGH' => '高风险阈值',
	'RISKWATCH_THRESHOLD_HIGH_EXPLAIN' => '分数达到或超过该值，标记为“高”。',
	'RISKWATCH_THRESHOLD_CRITICAL' => '严重阈值',
	'RISKWATCH_THRESHOLD_CRITICAL_EXPLAIN' => '分数达到或超过该值，标记为“严重”。',

	'RISKWATCH_WEIGHT_WARNINGS' => '警告权重',
	'RISKWATCH_WEIGHT_WARNINGS_EXPLAIN' => '每单位警告数增加的分值。',
	'RISKWATCH_WEIGHT_REPORTS' => '举报人权重',
	'RISKWATCH_WEIGHT_REPORTS_EXPLAIN' => '举报窗口内每个不同举报人增加的分值。',
	'RISKWATCH_WEIGHT_UNAPPROVED' => '未审核帖子权重',
	'RISKWATCH_WEIGHT_UNAPPROVED_EXPLAIN' => '时间窗内每个未审核/待复审帖子增加的分值。',
	'RISKWATCH_WEIGHT_LOGIN' => '登录尝试权重',
	'RISKWATCH_WEIGHT_LOGIN_EXPLAIN' => '当前登录尝试计数每单位增加的分值。',
	'RISKWATCH_WEIGHT_BAN' => '封禁权重',
	'RISKWATCH_WEIGHT_BAN_EXPLAIN' => '用户存在生效封禁时增加的分值。',

	'RISKWATCH_CAP_REPORTERS' => '举报人数上限',
	'RISKWATCH_CAP_REPORTERS_EXPLAIN' => '计入评分时“举报人数”分量的上限。',
	'RISKWATCH_CAP_UNAPPROVED' => '未审核帖子数上限',
	'RISKWATCH_CAP_UNAPPROVED_EXPLAIN' => '计入评分时“未审核帖子数”分量的上限。',
	'RISKWATCH_CAP_LOGIN' => '登录尝试上限',
	'RISKWATCH_CAP_LOGIN_EXPLAIN' => '计入评分时“登录尝试”分量的上限。',
]);
