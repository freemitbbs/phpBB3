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
	'NOTIFICATION_TYPE_RISKWATCH_ALERT' => 'Risk Watch alerts',
	'NOTIFICATION_TYPE_RISKWATCH_ALERT_EXPLAIN' => 'Notify me when a user crosses a higher Risk Watch threshold.',
	'NOTIFICATION_RISKWATCH_ALERT_TITLE' => 'Risk Watch: %1$s reached %2$s (score %3$s)',
	'NOTIFICATION_RISKWATCH_ALERT_REFERENCE' => 'W:%1$s R:%2$s U:%3$s L:%4$s B:%5$s M:%6$s',

	'LOG_RISKWATCH_ALERT' => 'Risk alert: %1$s reached score %2$s (%3$s), components %4$s',
	'LOG_RISKWATCH_MANUAL_ADD' => 'Risk manual adjustment added for %1$s: delta %2$s, reason: %3$s, expiry: %4$s',
	'LOG_RISKWATCH_MANUAL_DISABLE' => 'Risk manual adjustment disabled for %1$s: delta %2$s, reason: %3$s',
]);
