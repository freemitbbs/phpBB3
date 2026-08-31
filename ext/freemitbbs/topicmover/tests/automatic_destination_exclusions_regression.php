<?php

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
	require_once __DIR__ . '/../service/mover.php';

	class test_automatic_mover extends \freemitbbs\topicmover\service\mover
	{
		public function __construct(\phpbb\config\config $config)
		{
			$this->config = $config;
		}

		public function excluded_forums(): array
		{
			return $this->excluded_forum_ids();
		}
	}

	$config = new \phpbb\config\config([
		'topicmover_excluded_forum_ids' => '12, 18',
		'freemitbbs_adult_forum_ids' => '60, 61 18',
	]);
	$excluded = (new test_automatic_mover($config))->excluded_forums();

	$expected_ids = [12, 18, 60, 61];
	$failures = [];
	foreach ($expected_ids as $forum_id)
	{
		if (!isset($excluded[$forum_id]))
		{
			$failures[] = 'forum #' . $forum_id . ' was not excluded';
		}
	}

	if (count($excluded) !== count($expected_ids))
	{
		$failures[] = 'unexpected exclusion set: ' . implode(',', array_keys($excluded));
	}

	if ($failures)
	{
		fwrite(STDERR, "Automatic Topic Mover destination exclusion regression failed:\n- " . implode("\n- ", $failures) . "\n");
		exit(1);
	}

	echo 'Automatic Topic Mover destination exclusion regression passed (' . count($expected_ids) . " excluded forums)\n";
}
