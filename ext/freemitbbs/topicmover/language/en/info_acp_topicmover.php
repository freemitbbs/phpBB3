<?php

/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang, [
	'ACP_TOPICMOVER' => 'Topic mover',
	'ACP_TOPICMOVER_GRP' => 'Topic mover',
	'TOPICMOVER_EXPLAIN' => 'Automatically classify busy topics from forum ID 2 and move them to a better public forum. Topics are moved, not copied, and no shadow topic is left behind.',
	'TOPICMOVER_FIELDSET_GENERAL' => 'General settings',
	'TOPICMOVER_FIELDSET_API' => 'Classifier API',
	'TOPICMOVER_THRESHOLD' => 'Other-user reply threshold',
	'TOPICMOVER_THRESHOLD_EXPLAIN' => 'Only topics in forum ID 2 with more replies from other users than this value are considered. The topic author’s own replies are not counted. Default is 5.',
	'TOPICMOVER_MIN_LATEST_REPLY_AGE_HOURS' => 'Latest reply age',
	'TOPICMOVER_MIN_LATEST_REPLY_AGE_HOURS_EXPLAIN' => 'Only topics with no replies for at least this many hours are considered. Default is 12. Use 0 to disable this wait.',
	'TOPICMOVER_INTERVAL_SECONDS' => 'Run interval',
	'TOPICMOVER_INTERVAL_SECONDS_EXPLAIN' => 'Minimum seconds between cron runs. Default is 3600.',
	'TOPICMOVER_EXCLUDED_FORUM_IDS' => 'Excluded destination forum IDs',
	'TOPICMOVER_EXCLUDED_FORUM_IDS_EXPLAIN' => 'Comma-separated public forum IDs that should never be selected as destinations. Forum ID 2 is always excluded.',
	'TOPICMOVER_EXCLUDED_USER_IDS' => 'Excluded topic author user IDs',
	'TOPICMOVER_EXCLUDED_USER_IDS_EXPLAIN' => 'Comma-separated user IDs. Topics started by these users will never be moved.',
	'TOPICMOVER_API_ENDPOINT' => 'API endpoint',
	'TOPICMOVER_API_ENDPOINT_EXPLAIN' => 'OpenAI-compatible chat completions endpoint. Default is https://api.deepseek.com/chat/completions.',
	'TOPICMOVER_MODEL' => 'Model name',
	'TOPICMOVER_MODEL_EXPLAIN' => 'Model identifier sent in the chat completions request. Default is deepseek-v4-flash; deepseek-v4-pro is also supported.',
	'TOPICMOVER_API_KEY' => 'API key',
	'TOPICMOVER_API_KEY_EXPLAIN' => 'Stored in phpBB config. Leave blank to keep the current key.',
	'TOPICMOVER_API_KEY_CLEAR' => 'Clear stored API key',
]);
