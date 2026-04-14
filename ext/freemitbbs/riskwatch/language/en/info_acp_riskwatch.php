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
	'ACP_RISKWATCH_GRP' => 'Risk Watch',
	'ACP_RISKWATCH' => 'Risk Watch',
	'RISKWATCH_EXPLAIN' => 'Aggregated moderation risk score for users, built from core phpBB moderation and abuse signals.',
	'RISKWATCH_FIELDSET_SETTINGS' => 'Scoring and refresh settings',
	'RISKWATCH_FIELDSET_MANUAL' => 'Manual risk adjustments',
	'RISKWATCH_TOP_USERS' => 'Highest risk users',
	'RISKWATCH_RECOMPUTE_NOW' => 'Recompute now',
	'RISKWATCH_RECOMPUTE_RESULT' => 'Risk state recomputed for %d user(s).',
	'RISKWATCH_NO_DATA' => 'No users currently have non-zero risk.',

	'RISKWATCH_MANUAL_USER_ID' => 'Target user ID',
	'RISKWATCH_MANUAL_USER_ID_EXPLAIN' => 'Numeric user ID to adjust.',
	'RISKWATCH_MANUAL_DELTA' => 'Score delta',
	'RISKWATCH_MANUAL_DELTA_EXPLAIN' => 'Positive increases risk, negative reduces risk. Zero is not allowed.',
	'RISKWATCH_MANUAL_REASON' => 'Reason',
	'RISKWATCH_MANUAL_REASON_EXPLAIN' => 'Required audit reason (max 255 characters).',
	'RISKWATCH_MANUAL_EXPIRES_DAYS' => 'Expires in days',
	'RISKWATCH_MANUAL_EXPIRES_DAYS_EXPLAIN' => '0 means no expiry.',
	'RISKWATCH_MANUAL_ADD' => 'Add manual adjustment',
	'RISKWATCH_MANUAL_ADD_SUCCESS' => 'Manual adjustment saved for %1$s (delta: %2$s).',
	'RISKWATCH_MANUAL_DISABLE_SUCCESS' => 'Manual adjustment disabled for %1$s.',
	'RISKWATCH_MANUAL_ACTIVE' => 'Active manual adjustments',
	'RISKWATCH_MANUAL_CREATED_BY' => 'Created by',
	'RISKWATCH_MANUAL_CREATED_TIME' => 'Created',
	'RISKWATCH_MANUAL_EXPIRES_AT' => 'Expires',
	'RISKWATCH_MANUAL_STATUS' => 'Status',
	'RISKWATCH_MANUAL_STATUS_ACTIVE' => 'Active',
	'RISKWATCH_MANUAL_STATUS_EXPIRED' => 'Expired',
	'RISKWATCH_MANUAL_DISABLE' => 'Disable',
	'RISKWATCH_MANUAL_NONE' => 'No active manual adjustments.',

	'RISKWATCH_ERR_MANUAL_USER_REQUIRED' => 'Manual adjustment requires a valid target user ID.',
	'RISKWATCH_ERR_MANUAL_USER_NOT_FOUND' => 'User ID %d does not exist.',
	'RISKWATCH_ERR_MANUAL_DELTA_REQUIRED' => 'Manual adjustment delta must be non-zero.',
	'RISKWATCH_ERR_MANUAL_REASON_REQUIRED' => 'Manual adjustment reason is required.',
	'RISKWATCH_ERR_MANUAL_NOT_FOUND' => 'Manual adjustment record was not found or is already inactive.',

	'RISKWATCH_RISK_SCORE' => 'Risk score',
	'RISKWATCH_RISK_LEVEL' => 'Level',
	'RISKWATCH_WARNINGS' => 'Warnings',
	'RISKWATCH_REPORTERS_30D' => 'Open reporters (30d)',
	'RISKWATCH_UNAPPROVED_30D' => 'Unapproved posts (30d)',
	'RISKWATCH_LOGIN_ATTEMPTS' => 'Login attempts',
	'RISKWATCH_ACTIVE_BAN' => 'Active ban',
	'RISKWATCH_MANUAL_ADJUSTMENT' => 'Manual adj.',
	'RISKWATCH_LAST_COMPUTED' => 'Last computed',

	'RISKWATCH_LEVEL_NORMAL' => 'Normal',
	'RISKWATCH_LEVEL_WATCH' => 'Watch',
	'RISKWATCH_LEVEL_HIGH' => 'High',
	'RISKWATCH_LEVEL_CRITICAL' => 'Critical',

	'RISKWATCH_REFRESH_SECONDS' => 'Refresh interval (seconds)',
	'RISKWATCH_REFRESH_SECONDS_EXPLAIN' => 'Cron recompute interval. Recommended: 300 seconds.',
	'RISKWATCH_REFRESH_BATCH_SIZE' => 'Refresh batch size',
	'RISKWATCH_REFRESH_BATCH_SIZE_EXPLAIN' => 'Maximum number of candidate users to recompute in one cron run.',
	'RISKWATCH_ALERT_COOLDOWN_SECONDS' => 'Alert cooldown (seconds)',
	'RISKWATCH_ALERT_COOLDOWN_SECONDS_EXPLAIN' => 'Suppress repeat alerts for the same user and same level during this cooldown window.',
	'RISKWATCH_REPORTS_DAYS' => 'Open reports window (days)',
	'RISKWATCH_REPORTS_DAYS_EXPLAIN' => 'Only open reports newer than this are counted in the score.',
	'RISKWATCH_UNAPPROVED_DAYS' => 'Unapproved posts window (days)',
	'RISKWATCH_UNAPPROVED_DAYS_EXPLAIN' => 'Only unapproved/reapprove posts newer than this are counted.',
	'RISKWATCH_IGNORE_NEW_REPORTERS_DAYS' => 'Ignore new reporters (days)',
	'RISKWATCH_IGNORE_NEW_REPORTERS_DAYS_EXPLAIN' => 'If greater than 0, reports from accounts newer than this are excluded from reporter diversity counting.',

	'RISKWATCH_THRESHOLD_WATCH' => 'Watch threshold',
	'RISKWATCH_THRESHOLD_WATCH_EXPLAIN' => 'Score at or above this value is labeled Watch.',
	'RISKWATCH_THRESHOLD_HIGH' => 'High threshold',
	'RISKWATCH_THRESHOLD_HIGH_EXPLAIN' => 'Score at or above this value is labeled High.',
	'RISKWATCH_THRESHOLD_CRITICAL' => 'Critical threshold',
	'RISKWATCH_THRESHOLD_CRITICAL_EXPLAIN' => 'Score at or above this value is labeled Critical.',

	'RISKWATCH_WEIGHT_WARNINGS' => 'Warnings weight',
	'RISKWATCH_WEIGHT_WARNINGS_EXPLAIN' => 'Points added per warning count unit.',
	'RISKWATCH_WEIGHT_REPORTS' => 'Open reporters weight',
	'RISKWATCH_WEIGHT_REPORTS_EXPLAIN' => 'Points added per distinct open reporter in the report window.',
	'RISKWATCH_WEIGHT_UNAPPROVED' => 'Unapproved posts weight',
	'RISKWATCH_WEIGHT_UNAPPROVED_EXPLAIN' => 'Points added per unapproved/reapprove post in the time window.',
	'RISKWATCH_WEIGHT_LOGIN' => 'Login attempts weight',
	'RISKWATCH_WEIGHT_LOGIN_EXPLAIN' => 'Points added per current login-attempt counter unit.',
	'RISKWATCH_WEIGHT_BAN' => 'Active ban weight',
	'RISKWATCH_WEIGHT_BAN_EXPLAIN' => 'Points added when a user has an active ban.',

	'RISKWATCH_CAP_REPORTERS' => 'Open reporters cap',
	'RISKWATCH_CAP_REPORTERS_EXPLAIN' => 'Maximum reporter count considered in score contribution.',
	'RISKWATCH_CAP_UNAPPROVED' => 'Unapproved posts cap',
	'RISKWATCH_CAP_UNAPPROVED_EXPLAIN' => 'Maximum unapproved post count considered in score contribution.',
	'RISKWATCH_CAP_LOGIN' => 'Login attempts cap',
	'RISKWATCH_CAP_LOGIN_EXPLAIN' => 'Maximum login attempt count considered in score contribution.',
]);
