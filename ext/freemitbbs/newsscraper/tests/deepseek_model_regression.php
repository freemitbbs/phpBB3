<?php

require_once __DIR__ . '/../../topicmover/service/mover.php';
require_once __DIR__ . '/../service/scraper.php';

$services = [
	\freemitbbs\topicmover\service\mover::class,
	\freemitbbs\newsscraper\service\scraper::class,
];
$cases = [
	['', 'deepseek-v4-flash'],
	['deepseek-chat', 'deepseek-v4-flash'],
	['deepseek-reasoner', 'deepseek-v4-flash'],
	['deepseek-v4-flash', 'deepseek-v4-flash'],
	['deepseek-v4-pro', 'deepseek-v4-pro'],
	['custom-model', 'custom-model'],
];
$failures = [];

foreach ($services as $service_class)
{
	$reflection = new ReflectionClass($service_class);
	$service = $reflection->newInstanceWithoutConstructor();
	$normalizer = $reflection->getMethod('normalize_api_model');
	if (PHP_VERSION_ID < 80100)
	{
		$normalizer->setAccessible(true);
	}

	foreach ($cases as [$input, $expected])
	{
		$actual = (string) $normalizer->invoke($service, $input);
		if ($actual !== $expected)
		{
			$failures[] = sprintf('%s: %s expected %s got %s', $service_class, var_export($input, true), $expected, $actual);
		}
	}
}

if ($failures)
{
	fwrite(STDERR, "DeepSeek model regression failed\n" . implode("\n", $failures) . "\n");
	exit(1);
}

echo 'DeepSeek model regression passed (' . (count($services) * count($cases)) . " cases)\n";
