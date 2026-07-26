<?php

/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang, [
	'ACP_NEWSSCRAPER' => 'News scraper',
	'ACP_NEWSSCRAPER_GRP' => 'News scraper',
	'NEWSSCRAPER_EXPLAIN' => 'Fetches configured news feeds/listings, sends only titles to AI for audience-interest filtering, generates Chinese digests for selected items, and posts them to the configured digest forum. Raw article text is never stored.',
	'NEWSSCRAPER_FIELDSET_GENERAL' => 'General settings',
	'NEWSSCRAPER_FIELDSET_PIPELINE' => 'Pipeline limits',
	'NEWSSCRAPER_FIELDSET_SOURCES' => 'Sources',
	'NEWSSCRAPER_FIELDSET_API' => 'AI API',
	'NEWSSCRAPER_ENABLED' => 'Enable scraper cron',
	'NEWSSCRAPER_ENABLED_EXPLAIN' => 'When disabled, cron does not fetch or post digests. The front-page block can still show existing digest topics.',
	'NEWSSCRAPER_DIGEST_FORUM_ID' => 'Digest forum ID',
	'NEWSSCRAPER_DIGEST_FORUM_ID_EXPLAIN' => 'Forum where generated 新闻摘要 topics are posted. The migration creates and manages this forum automatically; use this only to override it.',
	'NEWSSCRAPER_INTERVAL_SECONDS' => 'Run interval',
	'NEWSSCRAPER_INTERVAL_SECONDS_EXPLAIN' => 'Minimum seconds between scraper cron runs. Default is 3600.',
	'NEWSSCRAPER_FRONTPAGE_COUNT' => 'Front-page digest count',
	'NEWSSCRAPER_FRONTPAGE_COUNT_EXPLAIN' => 'Maximum number of digest titles shown on the board index. Default is 20.',
	'NEWSSCRAPER_CANDIDATES_PER_RUN' => 'Candidate titles per run',
	'NEWSSCRAPER_CANDIDATES_PER_RUN_EXPLAIN' => 'Maximum new titles sent to AI filtering per cron run. Default is 60.',
	'NEWSSCRAPER_MAX_SELECTED_PER_RUN' => 'Maximum selected articles per run',
	'NEWSSCRAPER_MAX_SELECTED_PER_RUN_EXPLAIN' => 'Maximum articles that can be fetched and digested per cron run. Default is 4.',
	'NEWSSCRAPER_MIN_INTEREST_SCORE' => 'Minimum interest score',
	'NEWSSCRAPER_MIN_INTEREST_SCORE_EXPLAIN' => 'AI score threshold from 0 to 100 before fetching article text. Default is 65.',
	'NEWSSCRAPER_PER_SOURCE_CAP' => 'Per-source cap per run',
	'NEWSSCRAPER_PER_SOURCE_CAP_EXPLAIN' => 'Maximum generated digests from one source in one run. Default is 2.',
	'NEWSSCRAPER_TITLE_MAX_CHARS' => 'Digest title max characters',
	'NEWSSCRAPER_TITLE_MAX_CHARS_EXPLAIN' => 'Server-enforced title length for the two-column front-page layout. Default is 30.',
	'NEWSSCRAPER_SEEN_RETENTION_DAYS' => 'Seen URL retention',
	'NEWSSCRAPER_SEEN_RETENTION_DAYS_EXPLAIN' => 'Rejected and failed URL markers older than this many days are purged. Posted URL markers are kept.',
	'NEWSSCRAPER_SOURCES_EXPLAIN' => 'Xinhua World is available as an explicit news page source but is disabled by default. Use official/government-like sources only when the source URL is narrowly scoped.',
	'NEWSSCRAPER_API_FALLBACK_EXPLAIN' => 'Leave endpoint/model/API key blank to use the topicmover DeepSeek settings where available. This keeps one shared secret unless a separate scraper key is needed.',
	'NEWSSCRAPER_API_ENDPOINT' => 'API endpoint override',
	'NEWSSCRAPER_API_ENDPOINT_EXPLAIN' => 'OpenAI-compatible chat completions endpoint. Blank falls back to topicmover, then DeepSeek default.',
	'NEWSSCRAPER_MODEL' => 'Model override',
	'NEWSSCRAPER_MODEL_EXPLAIN' => 'Model identifier. Blank falls back to topicmover, then deepseek-v4-flash. deepseek-v4-pro is also supported.',
	'NEWSSCRAPER_API_KEY' => 'API key override',
	'NEWSSCRAPER_API_KEY_EXPLAIN' => 'Leave blank to keep current scraper key, or to fall back to topicmover API key if no scraper key is stored.',
	'NEWSSCRAPER_API_KEY_CLEAR' => 'Clear stored scraper API key',
	'NEWSSCRAPER_USING_TOPICMOVER_API_KEY' => 'No scraper key is stored; the topicmover API key will be used.',
]);
