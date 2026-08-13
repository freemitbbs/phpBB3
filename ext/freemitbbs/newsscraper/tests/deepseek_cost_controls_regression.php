<?php

require_once __DIR__ . '/../../topicmover/service/mover.php';
require_once __DIR__ . '/../service/scraper.php';

class cost_control_test_scraper extends \freemitbbs\newsscraper\service\scraper
{
	public array $payloads = [];

	public function __construct()
	{
	}

	public function select(array $candidates): array
	{
		return $this->select_interesting_candidates($candidates);
	}

	public function digest(array $candidate, string $article_text): array
	{
		return $this->generate_digest($candidate, $article_text);
	}

	protected function api_model(): string
	{
		return 'deepseek-v4-flash';
	}

	protected function api_request(array $payload): array
	{
		$this->payloads[] = $payload;
		$content = (int) ($payload['max_tokens'] ?? 0) === 1000
			? '{"selected":[{"id":1,"score":90,"reason":"relevant"}]}'
			: '{"title":"测试标题","content":"测试摘要正文"}';

		return ['choices' => [['message' => ['content' => $content]]]];
	}

	protected function recent_digest_titles_for_selection(): array
	{
		return [];
	}

	protected function recent_posted_titles_for_dedupe(?array $digest_titles = null): array
	{
		return [];
	}

	protected function recent_topic_titles_for_selection(): array
	{
		return [];
	}

	protected function recent_junban_topic_titles_for_selection(): array
	{
		return [];
	}

	protected function min_interest_score(): int
	{
		return 65;
	}

	protected function max_selected_per_run(): int
	{
		return 4;
	}

	protected function title_max_chars(): int
	{
		return 30;
	}
}

class cost_control_test_mover extends \freemitbbs\topicmover\service\mover
{
	public array $payload = [];

	public function __construct()
	{
	}

	public function classify(array $topic, array $forums): array
	{
		return $this->classify_topic($topic, $forums);
	}

	protected function api_model(): string
	{
		return 'deepseek-v4-flash';
	}

	protected function api_request(array $payload): array
	{
		$this->payload = $payload;

		return ['choices' => [['message' => ['content' => '{"destination_forum_id":3,"confidence":0.9,"reason":"fit"}']]]];
	}
}

$scraper = new cost_control_test_scraper();
$scraper->select([[
	'source_label' => 'Test',
	'title' => 'Test candidate',
	'published_time' => time(),
	'url_hash' => 'hash',
	'url' => 'https://example.com/article',
	'source_key' => 'test',
]]);
$scraper->digest([
	'source_label' => 'Test',
	'title' => 'Test candidate',
	'url' => 'https://example.com/article',
], str_repeat('article ', 100));

$mover = new cost_control_test_mover();
$mover->classify(
	['title' => 'Test topic', 'first_post' => null, 'latest_replies' => []],
	[3 => ['forum_id' => 3, 'name' => 'Test forum', 'description' => '']]
);

$payload_cases = [
	'title selection' => [$scraper->payloads[0] ?? [], 1000],
	'digest' => [$scraper->payloads[1] ?? [], 1200],
	'topic classification' => [$mover->payload, 400],
];
$failures = [];
foreach ($payload_cases as $name => [$payload, $expected_max_tokens])
{
	if (($payload['thinking']['type'] ?? '') !== 'disabled')
	{
		$failures[] = $name . ' did not disable thinking mode';
	}
	if ((int) ($payload['max_tokens'] ?? 0) !== $expected_max_tokens)
	{
		$failures[] = $name . ' max_tokens did not equal ' . $expected_max_tokens;
	}
}

if ($failures)
{
	fwrite(STDERR, "DeepSeek cost controls regression failed\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo 'DeepSeek cost controls regression passed (' . (count($payload_cases) * 2) . " cases)\n";
