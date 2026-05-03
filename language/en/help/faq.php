<?php
/**
 *
 * This file is part of the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * For full copyright and license information, please see
 * the docs/CREDITS.txt file.
 *
 */

/**
 * DO NOT CHANGE
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'HELP_FAQ_BLOCK_ABOUT' => 'About this site',
	'HELP_FAQ_BLOCK_ACCOUNT_POSTING' => 'Accounts and posting',
	'HELP_FAQ_BLOCK_DISCOVERY' => 'Topic discovery and visibility',
	'HELP_FAQ_BLOCK_FEEDBACK' => 'Likes, dislikes, and reactions',
	'HELP_FAQ_BLOCK_REPUTATION' => 'Reputation',

	'HELP_FAQ_ABOUT_SITE_QUESTION' => 'What is Freemit BBS?',
	'HELP_FAQ_ABOUT_SITE_ANSWER' => 'Freemit BBS is a Chinese-language discussion forum for users around the world. It aims to preserve an older BBS style of public conversation: more room to speak, challenge information, and let opposing views meet. See the full <a href="/app.php/about">About page</a> for the site philosophy.',
	'HELP_FAQ_ABOUT_VALUES_QUESTION' => 'What kind of discussion does the site encourage?',
	'HELP_FAQ_ABOUT_VALUES_ANSWER' => 'The site values expression, discussion, and diversity of viewpoints. Views may conflict, positions may differ, and public speech can be discussed publicly. The discussion matters more than forcing one agreed conclusion.',
	'HELP_FAQ_ABOUT_MANAGEMENT_QUESTION' => 'How is content managed?',
	'HELP_FAQ_ABOUT_MANAGEMENT_ANSWER' => 'The site follows a minimal-intervention model. Site operators keep the service running, but do not lead discussion direction or decide which opinion is correct. In normal use, replies, likes, dislikes, visibility, and reputation signals shape what receives attention.',
	'HELP_FAQ_ABOUT_BOUNDARIES_QUESTION' => 'What are the site boundaries?',
	'HELP_FAQ_ABOUT_BOUNDARIES_ANSWER' => 'This is not a rule-free space. Illegal content and behavior that clearly disrupts board order, such as flooding or malicious interference, may be handled. Outside those boundaries, the site tries to avoid manual intervention.',

	'HELP_FAQ_ACCOUNT_READ_POST_QUESTION' => 'Can I read without registering?',
	'HELP_FAQ_ACCOUNT_READ_POST_ANSWER' => 'Public forums can usually be read without an account. Posting, replying, liking, disliking, viewing personal records, sending private messages, and changing personal settings require a logged-in account.',
	'HELP_FAQ_ACCOUNT_POSTING_QUESTION' => 'How do I start a topic or reply?',
	'HELP_FAQ_ACCOUNT_POSTING_ANSWER' => 'Open the right forum and choose "New Topic" to start a discussion. Open an existing topic and choose "Post Reply" to join it. Use a clear title and place the discussion in the most relevant forum.',
	'HELP_FAQ_ACCOUNT_FORMATTING_QUESTION' => 'What formatting can I use?',
	'HELP_FAQ_ACCOUNT_FORMATTING_ANSWER' => 'Posts support common BBCode, links, images, and media embeds. Attachment uploads depend on the current forum and account permissions. HTML is not executed as page code.',
	'HELP_FAQ_ACCOUNT_SETTINGS_QUESTION' => 'How do I change my account settings?',
	'HELP_FAQ_ACCOUNT_SETTINGS_ANSWER' => 'After logging in, open the User Control Panel to change your password, email, avatar, signature, language, timezone, notifications, and privacy-related preferences. For login trouble, try resetting your password or deleting the site cookie.',

	'HELP_FAQ_DISCOVERY_TOP_RECENT_QUESTION' => 'What is the difference between Top Topics and Recent Topics?',
	'HELP_FAQ_DISCOVERY_TOP_RECENT_ANSWER' => 'Top Topics are sorted by a time-decayed score that considers likes, dislikes, replies, views, report penalties, and discussion quality signals. Recent Topics show recent activity. Both lists respect your read permissions and homepage forum-exclusion preferences.',
	'HELP_FAQ_DISCOVERY_DISLIKED_POSTS_QUESTION' => 'What happens to posts disliked by many users?',
	'HELP_FAQ_DISCOVERY_DISLIKED_POSTS_ANSWER' => 'Post visibility uses net dislikes: TopTopics dislikes minus Post Love likes. Before the collapse threshold is reached, the text color fades as net dislikes increase. At the threshold, the post is collapsed in the topic page, and readers can still expand it manually.',
	'HELP_FAQ_DISCOVERY_MAIN_POST_QUESTION' => 'What if the disliked post is the topic starter?',
	'HELP_FAQ_DISCOVERY_MAIN_POST_ANSWER' => 'If the first post of a topic reaches the net-dislike collapse threshold, the whole topic is removed from the homepage Top Topics and Recent Topics lists. If a reply reaches the threshold, only that reply is collapsed; the topic is not removed just because of a reply.',
	'HELP_FAQ_DISCOVERY_PREFERENCES_QUESTION' => 'Can I control homepage topic lists?',
	'HELP_FAQ_DISCOVERY_PREFERENCES_ANSWER' => 'Yes. In the User Control Panel you can choose whether Top Topics appear on forum pages, which forums should be hidden from homepage topic lists, and whether reply and view counts appear on mobile topic lists.',

	'HELP_FAQ_FEEDBACK_LIKES_DISLIKES_QUESTION' => 'What do likes and dislikes mean?',
	'HELP_FAQ_FEEDBACK_LIKES_DISLIKES_ANSWER' => 'A like means you found a post valuable and gives the post and author a positive signal. A dislike means you think the post should receive less prominence and gives a negative signal. You cannot like and dislike the same post at the same time; remove one before using the other. The dislike button shows raw dislikes, while collapse and list removal use dislikes minus likes.',
	'HELP_FAQ_FEEDBACK_REACTIONS_QUESTION' => 'Do emoji reactions affect the dislike threshold?',
	'HELP_FAQ_FEEDBACK_REACTIONS_ANSWER' => 'No. Emoji reactions and other reactions are not TopTopics likes or dislikes. They may count as general engagement for Top Topics ranking, but they do not affect text fading, post collapse, or removal from Top/Recent lists.',
	'HELP_FAQ_FEEDBACK_CANNOT_DISLIKE_QUESTION' => 'Why can I not dislike a post?',
	'HELP_FAQ_FEEDBACK_CANNOT_DISLIKE_ANSWER' => 'Common reasons include being logged out, trying to dislike your own post, not having enough posts yet, not having enough reputation, hitting the per-minute or per-day dislike limit, or already having liked that post. The button tooltip or error message shows the specific reason.',
	'HELP_FAQ_FEEDBACK_RECORDS_QUESTION' => 'Where can I see likes and dislikes I received?',
	'HELP_FAQ_FEEDBACK_RECORDS_ANSWER' => 'When logged in, use the User Control Panel link for received likes/dislikes. The page only shows records from forums you are allowed to read.',

	'HELP_FAQ_REPUTATION_WHAT_QUESTION' => 'What is reputation?',
	'HELP_FAQ_REPUTATION_WHAT_ANSWER' => 'Reputation is a user signal calculated from visible public writing and community feedback. It appears in post sidebars and profiles so readers can see a rough long-term contribution signal.',
	'HELP_FAQ_REPUTATION_BUILD_QUESTION' => 'How does reputation change?',
	'HELP_FAQ_REPUTATION_BUILD_ANSWER' => 'Substantial visible posts increase reputation. Received likes increase it. Received dislikes and open reports reduce it. The system filters out quotes, images, links, and markup noise so the score mostly reflects actual written contribution.',
	'HELP_FAQ_REPUTATION_GATES_QUESTION' => 'What does reputation restrict?',
	'HELP_FAQ_REPUTATION_GATES_ANSWER' => 'Reputation can gate negative actions such as disliking and reporting posts. This reduces abuse from new or low-quality accounts. Once your account meets the requirement, the relevant buttons become available.',
));
