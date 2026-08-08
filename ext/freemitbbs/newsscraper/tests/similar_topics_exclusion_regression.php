<?php

require_once __DIR__ . '/../../../vse/similartopics/core/similar_topics.php';

final class similar_topics_filter_db
{
	public function sql_in_set($field, $values, $negate = false): string
	{
		$values = array_map('intval', (array) $values);

		return $field . ($negate ? ' NOT IN ' : ' IN ') . '(' . implode(', ', $values) . ')';
	}
}

function build_similar_topics_filter(array $config, array $passworded_forums): array
{
	$reflection = new ReflectionClass(\vse\similartopics\core\similar_topics::class);
	$subject = $reflection->newInstanceWithoutConstructor();

	foreach ([
		'config' => $config,
		'db' => new similar_topics_filter_db(),
		'passworded_forums' => $passworded_forums,
	] as $property_name => $value)
	{
		$property = $reflection->getProperty($property_name);
		if (PHP_VERSION_ID < 80100)
		{
			$property->setAccessible(true);
		}
		$property->setValue($subject, $value);
	}

	$method = $reflection->getMethod('apply_forum_filters');
	if (PHP_VERSION_ID < 80100)
	{
		$method->setAccessible(true);
	}

	return [$subject, $method];
}

function check_filter(bool $condition, string $message): void
{
	if (!$condition)
	{
		throw new RuntimeException($message);
	}
}

[$subject, $method] = build_similar_topics_filter(['newsscraper_digest_forum_id' => 59], [12]);
$sql_array = ['WHERE' => 't.topic_visibility = 1'];
$result = $method->invokeArgs($subject, [&$sql_array]);
check_filter($result === true, 'Default forum filtering should continue.');
check_filter(str_contains($sql_array['WHERE'], 'f.forum_id NOT IN (12, 59)'), 'Digest and passworded forums must both be excluded.');
check_filter(str_contains($sql_array['WHERE'], 'f.similar_topics_ignore = 0'), 'The normal Similar Topics ignore flag must remain active.');

[$subject, $method] = build_similar_topics_filter(['newsscraper_digest_forum_id' => 59], [12]);
$sql_array = ['WHERE' => 't.topic_visibility = 1'];
$result = $method->invokeArgs($subject, [&$sql_array, json_encode([12, 59, 44])]);
check_filter($result === true, 'A valid explicitly included forum should remain searchable.');
check_filter(str_contains($sql_array['WHERE'], 'f.forum_id IN (44)'), 'Digest and passworded forums must be removed from an explicit include list.');

[$subject, $method] = build_similar_topics_filter(['newsscraper_digest_forum_id' => 59], [12]);
$sql_array = ['WHERE' => 't.topic_visibility = 1'];
$result = $method->invokeArgs($subject, [&$sql_array, json_encode([12, 59])]);
check_filter($result === false, 'Filtering should stop when an include list contains only excluded forums.');

[$subject, $method] = build_similar_topics_filter([], []);
$sql_array = ['WHERE' => 't.topic_visibility = 1'];
$result = $method->invokeArgs($subject, [&$sql_array]);
check_filter($result === true, 'Filtering without a configured digest forum should continue.');
check_filter(!str_contains($sql_array['WHERE'], 'NOT IN'), 'No additional forum exclusion should be added without a configured digest forum.');

echo "Similar Topics news digest exclusion regression passed (4 cases)\n";
