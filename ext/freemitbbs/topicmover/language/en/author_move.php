<?php

/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang, [
	'LOG_TOPICMOVER_AUTHOR_MOVED' => '<strong>Topic author moved topic</strong><br />From %1$s to %2$s (forum ID %3$d to %4$d)',
	'TOPICMOVER_AUTHOR_MOVE_BUTTON' => 'Move topic',
	'TOPICMOVER_AUTHOR_MOVE_TITLE' => 'Move your topic',
	'TOPICMOVER_AUTHOR_MOVE_EXPLAIN' => 'As the topic author, you can move this topic to another forum where you have permission to post.',
	'TOPICMOVER_AUTHOR_MOVE_CURRENT_FORUM' => 'Current forum',
	'TOPICMOVER_AUTHOR_MOVE_DESTINATION' => 'Destination forum',
	'TOPICMOVER_AUTHOR_MOVE_CHOOSE_FORUM' => 'Choose a forum',
	'TOPICMOVER_AUTHOR_MOVE_NO_DESTINATIONS' => 'No available destination forums.',
	'TOPICMOVER_AUTHOR_MOVE_WARNING' => 'The entire topic, including all replies, will move immediately. No redirect will be left in the current forum.',
	'TOPICMOVER_AUTHOR_MOVE_SUBMIT' => 'Move topic',
	'TOPICMOVER_AUTHOR_MOVE_INVALID_DESTINATION' => 'Please choose an available destination forum.',
	'TOPICMOVER_AUTHOR_MOVE_FAILED' => 'The topic could not be moved. Please try again.',
]);
