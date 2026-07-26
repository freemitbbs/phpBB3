<?php

/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang, [
	'ACP_TOPICMOVER' => '主题自动移动',
	'ACP_TOPICMOVER_GRP' => '主题自动移动',
	'TOPICMOVER_EXPLAIN' => '自动分析论坛 ID 2 中回复较多的主题，并移动到更合适的公开版面。主题会被移动，不会复制，也不会保留影子主题。',
	'TOPICMOVER_FIELDSET_GENERAL' => '常规设置',
	'TOPICMOVER_FIELDSET_API' => '分类 API',
	'TOPICMOVER_THRESHOLD' => '其他用户回复数阈值',
	'TOPICMOVER_THRESHOLD_EXPLAIN' => '只有论坛 ID 2 中其他用户回复数大于此值的主题会被处理。主题作者自己的回复不会计入。默认值为 5。',
	'TOPICMOVER_MIN_LATEST_REPLY_AGE_HOURS' => '最近回复间隔',
	'TOPICMOVER_MIN_LATEST_REPLY_AGE_HOURS_EXPLAIN' => '只有至少这么多小时没有新回复的主题会被处理。默认值为 12。设为 0 可关闭等待。',
	'TOPICMOVER_INTERVAL_SECONDS' => '运行间隔',
	'TOPICMOVER_INTERVAL_SECONDS_EXPLAIN' => '两次 cron 运行之间的最短秒数。默认 3600。',
	'TOPICMOVER_EXCLUDED_FORUM_IDS' => '排除的目标版面 ID',
	'TOPICMOVER_EXCLUDED_FORUM_IDS_EXPLAIN' => '逗号分隔的公开版面 ID，这些版面不会被选为目标。论坛 ID 2 始终会被排除。',
	'TOPICMOVER_EXCLUDED_USER_IDS' => '排除的主题作者用户 ID',
	'TOPICMOVER_EXCLUDED_USER_IDS_EXPLAIN' => '逗号分隔的用户 ID。这些用户发起的主题不会被自动移动。',
	'TOPICMOVER_API_ENDPOINT' => 'API 地址',
	'TOPICMOVER_API_ENDPOINT_EXPLAIN' => '兼容 OpenAI 的 chat completions API 地址。默认 https://api.deepseek.com/chat/completions。',
	'TOPICMOVER_MODEL' => '模型名称',
	'TOPICMOVER_MODEL_EXPLAIN' => '发送到 chat completions 请求中的模型 ID。默认 deepseek-v4-flash，也支持 deepseek-v4-pro。',
	'TOPICMOVER_API_KEY' => 'API 密钥',
	'TOPICMOVER_API_KEY_EXPLAIN' => '保存在 phpBB 配置中。留空表示保留当前密钥。',
	'TOPICMOVER_API_KEY_CLEAR' => '清除已保存的 API 密钥',
]);
