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
	'ACL_U_TOPTOPICS_DISLIKE' => 'Top Topics: Can dislike posts',
	'TOPTOPICS_INDEX_TITLE' => 'Top Topics',
	'TOPTOPICS_FORUM_TITLE' => 'Top Topics In This Forum',
	'TOPTOPICS_SCORE' => 'Score',
	'TOPTOPICS_SIGNALS' => 'Signals',
	'TOPTOPICS_LIKES' => 'Likes',
	'TOPTOPICS_DISLIKES' => 'Dislikes',
	'TOPTOPICS_VIEWS' => 'Views',
	'TOPTOPICS_FLAGS' => 'Flags',
	'TOPTOPICS_REACTIONS' => 'Reactions',
	'TOPTOPICS_POINTS' => 'Points',
	'TOPTOPICS_REPUTATION' => 'Reputation',
	'TOPTOPICS_CATEGORY_EMPTY' => 'No top topics in this category yet',
	'TOPTOPICS_CATEGORY_MORE' => 'More',
	'TOPTOPICS_RANK_TITLE' => 'Ranked by decayed score using likes, dislikes, replies, views, report penalties, and discussion balance',
	'TOPTOPICS_ADMIN_OVERRIDE' => 'Top Topics admin override',
	'TOPTOPICS_OVERRIDE_NORMAL' => 'Normal',
	'TOPTOPICS_OVERRIDE_BOOST' => 'Boost',
	'TOPTOPICS_OVERRIDE_DEMOTE' => 'Demote',
	'TOPTOPICS_OVERRIDE_KILL' => 'Kill',
	'TOPTOPICS_OVERRIDE_CURRENT_NORMAL' => 'Current state: normal',
	'TOPTOPICS_OVERRIDE_CURRENT_BOOST' => 'Current state: boosted',
	'TOPTOPICS_OVERRIDE_CURRENT_DEMOTE' => 'Current state: demoted',
	'TOPTOPICS_OVERRIDE_CURRENT_KILL' => 'Current state: hidden',
	'TOPTOPICS_COLLAPSE_SHOW' => 'Show top topics',
	'TOPTOPICS_COLLAPSE_HIDE' => 'Hide top topics',
	'TOPTOPICS_SHOW_FORUM_SUMMARY' => 'Show Top Topics on forum pages',
	'TOPTOPICS_SHOW_FORUM_SUMMARY_EXPLAIN' => 'Allow the Top Topics list to appear on individual forum pages.',
	'CLICK_TO_DISLIKE' => 'click to dislike this post',
	'CLICK_TO_UNDISLIKE' => 'click to remove your dislike from this post',
	'LOGIN_TO_DISLIKE_POST' => 'login to dislike this post',
	'CANT_DISLIKE_OWN_POST' => 'sorry, you cannot dislike your own post',
	'TOPTOPICS_MIN_POSTS_TO_DISLIKE' => 'you need at least %d posts before you can dislike content',
	'TOPTOPICS_MIN_REPUTATION_TO_DISLIKE' => 'you need reputation %1$s or higher to dislike content. Your current reputation is %2$s',
	'TOPTOPICS_MIN_REPUTATION_TO_REPORT' => 'you need reputation %1$s or higher to report content. Your current reputation is %2$s',
	'TOPTOPICS_RATE_LIMIT_MINUTE' => 'you have reached the downvote rate limit for this minute',
	'TOPTOPICS_RATE_LIMIT_DAY' => 'you have reached the downvote rate limit for today',
	'TOPTOPICS_REMOVE_LIKE_FIRST' => 'remove your like first',
	'TOPTOPICS_REMOVE_DISLIKE_FIRST' => 'remove your dislike first',
	'TOPTOPICS_DISLIKES_COUNT' => [
		0 => '%d dislikes',
		1 => '%d dislike',
		2 => '%d dislikes',
	],
]);
