<?php

require_once __DIR__ . '/../service/scraper.php';

$reflection = new ReflectionClass(\freemitbbs\newsscraper\service\scraper::class);
$scraper = $reflection->newInstanceWithoutConstructor();
$matcher = $reflection->getMethod('titles_are_near_duplicates');
if (PHP_VERSION_ID < 80100)
{
	$matcher->setAccessible(true);
}

$cases = [
	[
		'特朗普取消对伊朗军事打击，称谈判已获最高层批准',
		'特朗普取消原定今晚对伊朗的打击行动',
		true,
	],
	[
		'日本动漫粉丝请愿特朗普停止使用角色形象',
		'日本民众抗议特朗普使用动漫形象',
		true,
	],
	[
		'美军在阿曼湾击中油轮，三名印度船员失踪',
		'美军在阿曼湾开火致3名印度海员失踪，印方召见美外交官抗议',
		true,
	],
	[
		'AI价格战开打：OpenAI考虑大幅降价争夺Anthropic客户',
		'OpenAI考虑大幅降价以应对Anthropic竞争',
		true,
	],
	[
		'特朗普取消对伊朗军事打击',
		'OpenAI考虑大幅降价以应对Anthropic竞争',
		false,
	],
	[
		'美军在阿曼湾击中油轮',
		'特朗普取消原定今晚对伊朗的打击行动',
		false,
	],
];

$failures = [];
foreach ($cases as $index => [$left, $right, $expected])
{
	$actual = (bool) $matcher->invoke($scraper, $left, $right);
	if ($actual !== $expected)
	{
		$failures[] = sprintf(
			'#%d expected %s got %s: %s / %s',
			$index + 1,
			$expected ? 'duplicate' : 'distinct',
			$actual ? 'duplicate' : 'distinct',
			$left,
			$right
		);
	}
}

if ($failures)
{
	fwrite(STDERR, "newsscraper dedupe regression failed\n" . implode("\n", $failures) . "\n");
	exit(1);
}

echo "newsscraper dedupe regression passed (" . count($cases) . " cases)\n";
