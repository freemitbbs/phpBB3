<?php

require_once __DIR__ . '/../service/scraper.php';

class content_risk_context_test_scraper extends \freemitbbs\newsscraper\service\scraper
{
	public array $payload = [];
	public array $local_digest_titles = [];

	public function __construct()
	{
	}

	public function select(array $candidates): array
	{
		return $this->select_interesting_candidates($candidates);
	}

	protected function api_model(): string
	{
		return 'deepseek-v4-flash';
	}

	protected function api_request(array $payload): array
	{
		$this->payload = $payload;

		return ['choices' => [['message' => ['content' => '{"selected":[{"id":1,"score":90,"reason":"relevant"}]}']]]];
	}

	protected function recent_digest_titles_for_selection(): array
	{
		return ['可能触发第三方内容风控的历史摘要标题'];
	}

	protected function recent_posted_titles_for_dedupe(?array $digest_titles = null): array
	{
		$this->local_digest_titles = $digest_titles ?? [];

		return $this->local_digest_titles;
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
}

$scraper = new content_risk_context_test_scraper();
$selected = $scraper->select([[
	'source_label' => 'Test',
	'title' => 'Normal technology candidate',
	'published_time' => time(),
	'url_hash' => 'hash',
	'url' => 'https://example.com/article',
	'source_key' => 'test',
]]);

$user_payload = json_decode((string) ($scraper->payload['messages'][1]['content'] ?? ''), true);
$system_prompt = (string) ($scraper->payload['messages'][0]['content'] ?? '');
$cases = [
	'user payload is valid JSON' => is_array($user_payload),
	'historical digest titles are excluded from API request' => is_array($user_payload) && !array_key_exists('recent_digest_titles', $user_payload),
	'system prompt no longer requests digest-title context' => !str_contains($system_prompt, 'recent_digest_titles'),
	'historical digest titles remain available to local dedupe' => $scraper->local_digest_titles === ['可能触发第三方内容风控的历史摘要标题'],
	'candidate selection still succeeds' => count($selected) === 1,
];

$failures = array_keys(array_filter($cases, static fn (bool $passed): bool => !$passed));
if ($failures)
{
	fwrite(STDERR, "News scraper content-risk context regression failed:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo 'News scraper content-risk context regression passed (' . count($cases) . " cases)\n";
