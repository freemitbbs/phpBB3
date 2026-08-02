<?php

namespace
{
	define('ANONYMOUS', 1);
	define('ITEM_UNAPPROVED', 0);
	define('ITEM_APPROVED', 1);
	define('ITEM_UNLOCKED', 0);
	define('ITEM_LOCKED', 1);
	define('FORUM_CAT', 0);
	define('FORUM_POST', 1);
	define('POST_NORMAL', 0);
	define('POST_STICKY', 1);
	define('POST_ANNOUNCE', 2);
	define('POST_GLOBAL', 3);
}

namespace phpbb\auth
{
	class auth
	{
		public array $permissions = [];

		public function acl_get($option, $forum_id = 0): bool
		{
			return $this->permissions[(int) $forum_id][(string) $option] ?? true;
		}
	}
}

namespace phpbb\config
{
	class config implements \ArrayAccess
	{
		public function __construct(protected array $values)
		{
		}

		public function offsetExists(mixed $offset): bool
		{
			return isset($this->values[$offset]);
		}

		public function offsetGet(mixed $offset): mixed
		{
			return $this->values[$offset] ?? '';
		}

		public function offsetSet(mixed $offset, mixed $value): void
		{
			$this->values[$offset] = $value;
		}

		public function offsetUnset(mixed $offset): void
		{
			unset($this->values[$offset]);
		}
	}
}

namespace freemitbbs\topicmover\tests
{
	require_once __DIR__ . '/../service/author_move.php';

	class test_author_move extends \freemitbbs\topicmover\service\author_move
	{
		public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config)
		{
			$this->auth = $auth;
			$this->config = $config;
		}

		public function destination_allowed(array $forum, int $topic_type): bool
		{
			return $this->is_destination_allowed($forum, $topic_type);
		}
	}

	$auth = new \phpbb\auth\auth();
	$config = new \phpbb\config\config([
		'freemitbbs_blog_forum_managed' => 58,
		'newsscraper_digest_forum_managed' => 59,
	]);
	$service = new test_author_move($auth, $config);
	$topic = [
		'topic_poster' => 42,
		'topic_visibility' => ITEM_APPROVED,
		'topic_moved_id' => 0,
		'topic_status' => ITEM_UNLOCKED,
		'topic_type' => POST_NORMAL,
		'forum_id' => 11,
		'forum_type' => FORUM_POST,
		'forum_status' => ITEM_UNLOCKED,
		'forum_password' => '',
	];
	$forum = [
		'forum_id' => 15,
		'forum_type' => FORUM_POST,
		'forum_status' => ITEM_UNLOCKED,
		'forum_password' => '',
	];

	$cases = [];
	$cases['eligible author topic'] = $service->can_move($topic, 42) === true;
	$cases['different author denied'] = $service->can_move($topic, 43) === false;
	$cases['anonymous author denied'] = $service->can_move($topic, ANONYMOUS) === false;
	$cases['managed source denied'] = $service->can_move(array_replace($topic, ['forum_id' => 58]), 42) === false;
	$cases['locked topic denied'] = $service->can_move(array_replace($topic, ['topic_status' => ITEM_LOCKED]), 42) === false;
	$cases['global topic denied'] = $service->can_move(array_replace($topic, ['topic_type' => POST_GLOBAL]), 42) === false;
	$cases['password source denied'] = $service->can_move(array_replace($topic, ['forum_password' => 'secret']), 42) === false;

	$auth->permissions[11]['f_post'] = false;
	$cases['source posting permission required'] = $service->can_move($topic, 42) === false;
	$auth->permissions[11]['f_post'] = true;

	$cases['eligible destination'] = $service->destination_allowed($forum, POST_NORMAL) === true;
	$cases['managed destination denied'] = $service->destination_allowed(array_replace($forum, ['forum_id' => 59]), POST_NORMAL) === false;
	$cases['password destination denied'] = $service->destination_allowed(array_replace($forum, ['forum_password' => 'secret']), POST_NORMAL) === false;

	$auth->permissions[15]['f_noapprove'] = false;
	$cases['moderated destination denied'] = $service->destination_allowed($forum, POST_NORMAL) === false;
	$auth->permissions[15]['f_noapprove'] = true;

	$auth->permissions[15]['f_sticky'] = false;
	$cases['sticky permission required'] = $service->destination_allowed($forum, POST_STICKY) === false;
	$auth->permissions[15]['f_sticky'] = true;

	$auth->permissions[15]['f_announce'] = false;
	$cases['announcement permission required'] = $service->destination_allowed($forum, POST_ANNOUNCE) === false;

	$failures = array_keys(array_filter($cases, static function (bool $passed): bool {
		return !$passed;
	}));
	if ($failures)
	{
		fwrite(STDERR, "Topic author-move permission regression failed:\n- " . implode("\n- ", $failures) . "\n");
		exit(1);
	}

	echo 'Topic author-move permission regression passed (' . count($cases) . " cases)\n";
}
