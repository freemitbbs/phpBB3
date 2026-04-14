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
	'NOTIFICATION_TYPE_RISKWATCH_ALERT' => '风险观察告警',
	'NOTIFICATION_TYPE_RISKWATCH_ALERT_EXPLAIN' => '当某用户跨入更高风险等级时通知我。',
	'NOTIFICATION_RISKWATCH_ALERT_TITLE' => '风险观察：%1$s 已达到 %2$s（分数 %3$s）',
	'NOTIFICATION_RISKWATCH_ALERT_REFERENCE' => 'W:%1$s R:%2$s U:%3$s L:%4$s B:%5$s M:%6$s',

	'LOG_RISKWATCH_ALERT' => '风险告警：%1$s 达到分数 %2$s（%3$s），分量 %4$s',
	'LOG_RISKWATCH_MANUAL_ADD' => '已为 %1$s 添加风险手动调整：增量 %2$s，原因：%3$s，过期：%4$s',
	'LOG_RISKWATCH_MANUAL_DISABLE' => '已停用 %1$s 的风险手动调整：增量 %2$s，原因：%3$s',
]);
