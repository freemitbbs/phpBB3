<?php

// Run only against the local board started by script/phpbb-test-start.
if (PHP_SAPI !== 'cli')
{
	exit(1);
}
define('IN_PHPBB', true);
$phpbb_root_path = dirname(__DIR__, 4) . '/';
$phpEx = 'php';
require $phpbb_root_path . 'config.php';
if ($dbname !== 'phpbb_test' || $dbhost !== 'localhost' || $dbport !== '/tmp/phpbb-mysql.sock')
{
	throw new RuntimeException('This test requires the local phpbb_test database.');
}
require $phpbb_root_path . 'common.php';
require_once $phpbb_root_path . 'includes/functions_user.php';
require_once $phpbb_root_path . 'includes/functions_admin.php';
$user->session_begin();
$auth->acl($user->data);
$user->setup();
restore_exception_handler();

$checks = 0;
function check($condition, string $message): void
{
	global $checks;
	if (!$condition)
	{
		throw new RuntimeException($message);
	}
	$checks++;
	echo "PASS $message\n";
}

function row(string $sql): array
{
	global $db;
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result) ?: [];
	$db->sql_freeresult($result);
	return $row;
}

function http($client, string $path, ?array $fields = null, int $expected_status = 200): string
{
	curl_setopt($client, CURLOPT_URL, 'http://127.0.0.1:8090/' . ltrim($path, './'));
	curl_setopt($client, CURLOPT_POST, $fields !== null);
	if ($fields !== null)
	{
		$multipart = (bool) array_filter($fields, static function ($value): bool { return $value instanceof CURLFile; });
		curl_setopt($client, CURLOPT_POSTFIELDS, $multipart ? $fields : http_build_query($fields));
	}
	$html = curl_exec($client);
	if ($html === false || curl_getinfo($client, CURLINFO_HTTP_CODE) !== $expected_status)
	{
		throw new RuntimeException('HTTP failed: ' . $path . ' status=' . curl_getinfo($client, CURLINFO_HTTP_CODE) . ' ' . curl_error($client) . ' ' . substr((string) $html, 0, 300));
	}
	if (preg_match('/Fatal error|SQL ERROR|\[phpBB Debug\]/', $html))
	{
		throw new RuntimeException('phpBB error: ' . $path . ' ' . substr(strip_tags($html), 0, 1500));
	}
	file_put_contents('/tmp/anonymousposts-last-response.html', $html);
	return $html;
}

function form(string $html, string $id): array
{
	$dom = new DOMDocument();
	@$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
	$xpath = new DOMXPath($dom);
	$form = $xpath->query('//form[@id="' . $id . '"]')->item(0);
	if (!$form)
	{
		throw new RuntimeException('Missing form ' . $id . ': ' . mb_substr(strip_tags($html), -4000));
	}
	$fields = [];
	foreach ($xpath->query('.//input[@name]', $form) as $field)
	{
		$type = $field->getAttribute('type');
		if (in_array($type, ['submit', 'button', 'file'], true) || (in_array($type, ['checkbox', 'radio'], true) && !$field->hasAttribute('checked')))
		{
			continue;
		}
		$fields[$field->getAttribute('name')] = $field->getAttribute('value');
	}
	foreach ($xpath->query('.//textarea[@name]', $form) as $field)
	{
		$fields[$field->getAttribute('name')] = $field->textContent;
	}
	$action = $form->getAttribute('action');
	return [$action, $fields];
}

function post_html(string $html, int $post_id): string
{
	$dom = new DOMDocument();
	@$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
	$xpath = new DOMXPath($dom);
	$post = $xpath->query('//*[@id="p' . $post_id . '"]')->item(0);
	if (!$post)
	{
		throw new RuntimeException('Missing rendered post ' . $post_id);
	}
	return $dom->saveHTML($post);
}

function submit_form($client, string $html, array $extra, string $id = 'postform'): string
{
	[$action, $fields] = form($html, $id);
	// phpBB intentionally rejects tokens submitted in their creation second.
	sleep(1);
	return http($client, $action, array_replace($fields, $extra));
}

function client(string $name, string $password)
{
	$client = curl_init();
	curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 30]);
	$html = http($client, 'ucp.php?mode=login');
	submit_form($client, $html, ['username' => $name, 'password' => $password, 'login' => 'Login'], 'login');
	check(strpos(http($client, 'index.php'), $name) !== false, 'login ' . $name);
	return $client;
}

$saved = [];
$users = [];
$topics = [];
$upload_file = null;
$failure = null;
$prefix = 'anoncheck' . bin2hex(random_bytes(3));
$password = bin2hex(random_bytes(16));
$forum_id = 2;
$other_forum_id = 15;
try
{
	foreach (['anonymousposts_forum_ids' => (string) $forum_id, 'email_enable' => '0', 'flood_interval' => '0', 'search_interval' => '0', 's3attachments_enabled' => '0'] as $key => $value)
	{
		$saved[$key] = row('SELECT config_value FROM ' . CONFIG_TABLE . " WHERE config_name = '" . $db->sql_escape($key) . "'")['config_value'];
		$config->set($key, $value);
	}
	$group = row('SELECT group_id FROM ' . GROUPS_TABLE . " WHERE group_name = 'REGISTERED'");
	foreach (['owner', 'reader', 'moderator'] as $suffix)
	{
		$user_group = $suffix === 'moderator' ? row('SELECT group_id FROM ' . GROUPS_TABLE . " WHERE group_name = 'GLOBAL_MODERATORS'") : $group;
		$users[$suffix] = user_add([
			'username' => $prefix . $suffix,
			'user_password' => $phpbb_container->get('passwords.manager')->hash($password),
			'user_email' => $prefix . $suffix . '@example.invalid',
			'group_id' => (int) $user_group['group_id'],
			'user_type' => USER_NORMAL,
			'user_lang' => 'zh_cmn_hans',
			'user_sig' => 'PRIVATE_SIGNATURE_' . $prefix,
		]);
	}
	$owner = client($prefix . 'owner', $password);
	$reader = client($prefix . 'reader', $password);
	$moderator = client($prefix . 'moderator', $password);
	$db->sql_query('INSERT INTO ' . FORUMS_WATCH_TABLE . ' ' . $db->sql_build_array('INSERT', [
		'forum_id' => $forum_id, 'user_id' => $users['reader'], 'notify_status' => 0,
	]));
	$html = http($owner, 'posting.php?mode=post&f=' . $forum_id);
	check(strpos($html, 'id="anonymous_post"') !== false, 'anonymous checkbox in enabled forum');
	check(strpos(http($owner, 'posting.php?mode=post&f=' . $other_forum_id), 'id="anonymous_post"') === false, 'no anonymous checkbox in other forum');
	$subject = $prefix . ' privacy topic';
	$message = 'Anonymous body for privacy verification. [smention u=' . $users['reader'] . ']' . $prefix . 'reader[/smention]';
	$upload_file = tempnam('/tmp', 'anonymous-upload-');
	file_put_contents($upload_file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jT1sAAAAASUVORK5CYII='));
	$uploaded = submit_form($owner, $html, ['subject' => $subject, 'message' => $message, 'fileupload' => new CURLFile($upload_file, 'image/png', 'privacy.png'), 'add_file' => 'Add file', 'anonymous_post' => '1']);
	$attachment = row('SELECT * FROM ' . ATTACHMENTS_TABLE . ' WHERE poster_id = ' . $users['owner'] . ' ORDER BY attach_id DESC');
	check(!empty($attachment) && !preg_match('/^\d+_/', $attachment['physical_filename']), 'upload storage filename contains no account ID');
	$preview = submit_form($owner, $uploaded, ['subject' => $subject, 'message' => $message, 'anonymous_post' => '1', 'attach_sig' => 'on', 'preview' => 'Preview']);
	check((bool) preg_match('/id="anonymous_post"[^>]*checked/', $preview), 'preview preserves anonymous choice');
	check(strpos($preview, 'PRIVATE_SIGNATURE_') === false, 'preview suppresses signature');
	submit_form($owner, $preview, ['subject' => $subject, 'message' => $message, 'anonymous_post' => '1', 'attach_sig' => 'on', 'notify' => 'on', 'post' => 'Submit']);
	$post = row('SELECT * FROM ' . POSTS_TABLE . " WHERE post_subject = '" . $db->sql_escape($subject) . "'");
	check(!empty($post), 'anonymous topic submitted');
	$topics[] = (int) $post['topic_id'];
	$post_id = (int) $post['post_id'];
	$topic_id = (int) $post['topic_id'];
	check((int) $post['poster_id'] === ANONYMOUS && $post['post_username'] === '匿名', 'public post identity is anonymous');
	check((int) $post['anonymous_user_id'] === $users['owner'], 'private ownership retained');
	check(!(int) $post['enable_sig'] && !(int) $post['post_postcount'], 'signature and public account count disabled');
	$attachment = row('SELECT * FROM ' . ATTACHMENTS_TABLE . ' WHERE attach_id = ' . (int) $attachment['attach_id']);
	check((int) $attachment['poster_id'] === ANONYMOUS && (int) $attachment['post_msg_id'] === $post_id, 'published attachment does not reveal owner account');
	$account = row('SELECT user_posts, user_lastpost_time FROM ' . USERS_TABLE . ' WHERE user_id = ' . $users['owner']);
	check(!(int) $account['user_posts'] && (int) $account['user_lastpost_time'] > 0, 'account flood timestamp retained without public post count');
	$topic = row('SELECT * FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . $topic_id);
	$forum = row('SELECT * FROM ' . FORUMS_TABLE . ' WHERE forum_id = ' . $forum_id);
	check((int) $topic['topic_poster'] === ANONYMOUS && $topic['topic_first_poster_name'] === '匿名' && $topic['topic_last_poster_name'] === '匿名', 'topic metadata hides account');
	check((int) $forum['forum_last_poster_id'] === ANONYMOUS && $forum['forum_last_poster_name'] === '匿名', 'forum last author hides account');
	$notification = row('SELECT notification_data FROM ' . NOTIFICATIONS_TABLE . ' WHERE user_id = ' . $users['reader'] . ' AND item_id = ' . $topic_id . ' ORDER BY notification_id DESC');
	check(!empty($notification), 'watcher receives notification');
	$notification_data = unserialize($notification['notification_data']);
	check((int) $notification_data['poster_id'] === ANONYMOUS && $notification_data['post_username'] === '匿名', 'notification identity is anonymous');
	$mention = row('SELECT n.notification_data FROM ' . NOTIFICATIONS_TABLE . ' n JOIN ' . NOTIFICATION_TYPES_TABLE . " nt ON nt.notification_type_id = n.notification_type_id WHERE nt.notification_type_name = 'paul999.mention.notification.type.mention' AND n.user_id = " . $users['reader'] . ' AND n.item_id = ' . $post_id);
	check(!empty($mention), 'mentioned reader receives notification');
	$mention_data = unserialize($mention['notification_data']);
	check((int) $mention_data['poster_id'] === ANONYMOUS && $mention_data['username'] === '匿名', 'mention notification hides session identity');
	$html = http($reader, 'viewtopic.php?t=' . $topic_id);
	$rendered_post = post_html($html, $post_id);
	check(strpos($rendered_post, $prefix . 'owner') === false && strpos($rendered_post, 'PRIVATE_SIGNATURE_') === false, 'topic reader sees no owner account or signature in post');
	check(strpos($html, 'id="anonymous_post"') !== false, 'quick reply has anonymous checkbox');
	check(strpos($html, 'mode=edit&amp;p=' . $post_id) === false, 'other reader has no edit button');
	$owner_html = http($owner, 'viewtopic.php?t=' . $topic_id);
	check(strpos($owner_html, 'mode=edit&amp;p=' . $post_id) !== false, 'owner has edit button');
	$mcp = http($moderator, 'mcp.php?i=main&mode=post_details&p=' . $post_id);
	check(strpos($mcp, '匿名帖实际作者（仅版主可见）') !== false && strpos($mcp, $prefix . 'owner') !== false, 'moderator can identify author privately');
	$reader_mcp = http($reader, 'mcp.php?i=main&mode=post_details&p=' . $post_id, null, 403);
	check(strpos($reader_mcp, '匿名帖实际作者（仅版主可见）') === false, 'ordinary reader cannot access private author panel');
	$denied = http($reader, 'posting.php?mode=edit&p=' . $post_id);
	check(strpos($denied, 'id="postform"') === false, 'other reader cannot edit by direct URL');
	$quote = http($reader, 'posting.php?mode=quote&p=' . $post_id);
	[, $quoted_fields] = form($quote, 'postform');
	check(strpos($quoted_fields['message'], '匿名') !== false && strpos($quoted_fields['message'], $prefix . 'owner') === false && strpos($quoted_fields['message'], 'user_id=' . $users['owner']) === false, 'quote hides original account');
	$edit = http($owner, 'posting.php?mode=edit&p=' . $post_id);
	check(strpos($edit, '编辑后仍保持匿名') !== false, 'edit form states anonymity is retained');
	submit_form($owner, $edit, ['subject' => $subject, 'message' => 'Edited anonymous body.', 'attach_sig' => 'on', 'username' => $prefix . 'owner', 'post' => 'Submit']);
	$edited = row('SELECT * FROM ' . POSTS_TABLE . ' WHERE post_id = ' . $post_id);
	check((int) $edited['poster_id'] === ANONYMOUS && $edited['post_username'] === '匿名' && (int) $edited['post_edit_user'] === ANONYMOUS && !(int) $edited['enable_sig'], 'editing cannot expose identity or signature');
	check(strpos(post_html(http($reader, 'viewtopic.php?t=' . $topic_id), $post_id), $prefix . 'owner') === false, 'edit attribution stays anonymous');
	$reply_html = http($reader, 'viewtopic.php?t=' . $topic_id);
	submit_form($reader, $reply_html, ['subject' => 'Re: ' . $subject, 'message' => 'An anonymous reply by another reader.', 'anonymous_post' => '1', 'post' => 'Submit'], 'qr_postform');
	$reply = row('SELECT * FROM ' . POSTS_TABLE . ' WHERE topic_id = ' . $topic_id . ' ORDER BY post_id DESC');
	check((int) $reply['post_id'] !== $post_id && (int) $reply['poster_id'] === ANONYMOUS && (int) $reply['anonymous_user_id'] === $users['reader'], 'quick reply can independently be anonymous');
	$topic = row('SELECT * FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . $topic_id);
	check($topic['topic_last_poster_name'] === '匿名' && (int) $topic['topic_last_poster_id'] === ANONYMOUS, 'anonymous reply preserves last-author privacy');
	$reply_id = (int) $reply['post_id'];
	$denied_delete = http($owner, 'posting.php?mode=delete&p=' . $reply_id);
	check(strpos($denied_delete, 'id="confirm"') === false, 'another anonymous author cannot delete reply');
	$own_reply = post_html(http($reader, 'viewtopic.php?t=' . $topic_id), $reply_id);
	check((bool) preg_match('/href="([^\"]*mode=(?:soft_delete|delete)&amp;p=' . $reply_id . '[^\"]*)"/', $own_reply, $delete_link), 'anonymous reply owner has delete action');
	$confirm = http($reader, html_entity_decode($delete_link[1], ENT_QUOTES));
	submit_form($reader, $confirm, ['confirm' => '是'], 'confirm');
	$deleted = row('SELECT post_visibility FROM ' . POSTS_TABLE . ' WHERE post_id = ' . $reply_id);
	check(!$deleted || (int) $deleted['post_visibility'] === ITEM_DELETED, 'owner can delete own last anonymous reply');
	$ordinary_subject = $prefix . ' ordinary topic';
	submit_form($owner, http($owner, 'posting.php?mode=post&f=' . $forum_id), ['subject' => $ordinary_subject, 'message' => 'Ordinary named post.', 'post' => 'Submit']);
	$ordinary = row('SELECT * FROM ' . POSTS_TABLE . " WHERE post_subject = '" . $db->sql_escape($ordinary_subject) . "'");
	check(!empty($ordinary), 'ordinary topic submitted');
	$topics[] = (int) $ordinary['topic_id'];
	check((int) $ordinary['poster_id'] === $users['owner'] && !(int) $ordinary['anonymous_user_id'], 'unchecked option preserves ordinary identity');
	$invalid = submit_form($owner, http($owner, 'posting.php?mode=post&f=' . $other_forum_id), ['subject' => $prefix . ' invalid', 'message' => 'Unsupported anonymous request.', 'anonymous_post' => '1', 'post' => 'Submit']);
	check(strpos($invalid, '此处不能使用匿名发布') !== false, 'forged anonymous choice outside enabled forum is rejected');
	$db->sql_query('INSERT INTO ' . DRAFTS_TABLE . ' ' . $db->sql_build_array('INSERT', [
		'user_id' => $users['owner'], 'forum_id' => $forum_id, 'topic_id' => 0, 'save_time' => time(),
		'draft_subject' => 'Private draft', 'draft_message' => 'Recovered private draft text.',
	]));
	$draft_id = (int) $db->sql_nextid();
	$draft = http($owner, 'posting.php?mode=post&f=' . $forum_id . '&d=' . $draft_id);
	check((bool) preg_match('/id="anonymous_post"[^>]*checked/', $draft), 'server drafts default to anonymous in enabled forum');
}
catch (Throwable $e)
{
	$failure = $e;
}
finally
{
	foreach ($saved as $key => $value)
	{
		$config->set($key, $value);
	}
	$cache->destroy('config');
	if ($upload_file !== null && file_exists($upload_file))
	{
		unlink($upload_file);
	}
	if ($topics)
	{
		delete_topics('topic_id', $topics);
	}
	foreach ($users as $id)
	{
		user_delete('remove', $id);
	}
}
if ($failure !== null)
{
	fwrite(STDERR, $failure->getMessage() . "\n" . $failure->getTraceAsString() . "\n");
	exit(1);
}
foreach ($saved as $key => $value)
{
	check(row('SELECT config_value FROM ' . CONFIG_TABLE . " WHERE config_name = '" . $db->sql_escape($key) . "'")['config_value'] === $value, 'restored configuration ' . $key);
}
echo "Anonymous posting integration passed ($checks checks).\n";
