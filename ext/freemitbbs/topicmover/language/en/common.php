<?php

/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang, [
	'LOG_TOPICMOVER_MOVED' => '<strong>Topic mover moved topic</strong><br />Topic ID: %1$s from forum ID %2$s to forum ID %3$s. Reason: %4$s',
	'LOG_TOPICMOVER_FAILED' => '<strong>Topic mover failed</strong><br />Topic ID: %1$s. Error: %2$s',
	'TOPICMOVER_UCP_NO_MOVE' => 'Do not automatically move my topics',
	'TOPICMOVER_UCP_NO_MOVE_EXPLAIN' => 'When enabled, Topic mover will leave topics you started in their original forum.',
]);
