<?php

/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang, [
	'ACP_NEWSSCRAPER' => '新闻摘要',
	'ACP_NEWSSCRAPER_GRP' => '新闻摘要',
	'NEWSSCRAPER_EXPLAIN' => '从配置的新闻源抓取标题，先只把标题列表交给 AI 筛选，再为入选文章生成中文摘要并发到指定版面。原始网页和原文正文不会入库。',
	'NEWSSCRAPER_FIELDSET_GENERAL' => '基本设置',
	'NEWSSCRAPER_FIELDSET_PIPELINE' => '流程限制',
	'NEWSSCRAPER_FIELDSET_SOURCES' => '新闻源',
	'NEWSSCRAPER_FIELDSET_API' => 'AI API',
	'NEWSSCRAPER_ENABLED' => '启用定时抓取',
	'NEWSSCRAPER_ENABLED_EXPLAIN' => '关闭后 cron 不会抓取或发文；首页仍可显示已有摘要。',
	'NEWSSCRAPER_DIGEST_FORUM_ID' => '摘要版面 ID',
	'NEWSSCRAPER_DIGEST_FORUM_ID_EXPLAIN' => '生成的新闻摘要主题发到这个版面。迁移会自动创建并管理该版面；通常不需要手工修改。',
	'NEWSSCRAPER_INTERVAL_SECONDS' => '运行间隔',
	'NEWSSCRAPER_INTERVAL_SECONDS_EXPLAIN' => '两次抓取 cron 之间的最短秒数。默认 7200。',
	'NEWSSCRAPER_FRONTPAGE_COUNT' => '首页显示条数',
	'NEWSSCRAPER_FRONTPAGE_COUNT_EXPLAIN' => '首页新闻摘要标题最多显示多少条。默认 20。',
	'NEWSSCRAPER_CANDIDATES_PER_RUN' => '每次筛选标题数',
	'NEWSSCRAPER_CANDIDATES_PER_RUN_EXPLAIN' => '每次 cron 最多把多少个新标题交给 AI 预筛选。默认 60。',
	'NEWSSCRAPER_MAX_SELECTED_PER_RUN' => '每次最多入选文章',
	'NEWSSCRAPER_MAX_SELECTED_PER_RUN_EXPLAIN' => '每次 cron 最多抓取正文并生成摘要的文章数。默认 4。',
	'NEWSSCRAPER_MIN_INTEREST_SCORE' => '最低兴趣分',
	'NEWSSCRAPER_MIN_INTEREST_SCORE_EXPLAIN' => 'AI 给出的 0 到 100 分，低于此分数不抓正文。默认 65。',
	'NEWSSCRAPER_PER_SOURCE_CAP' => '单源每次上限',
	'NEWSSCRAPER_PER_SOURCE_CAP_EXPLAIN' => '每个新闻源在一次 cron 中最多生成多少篇摘要。默认 2。',
	'NEWSSCRAPER_TITLE_MAX_CHARS' => '摘要标题最长字符',
	'NEWSSCRAPER_TITLE_MAX_CHARS_EXPLAIN' => '为首页两栏单行布局强制限制标题长度。默认 30。',
	'NEWSSCRAPER_SEEN_RETENTION_DAYS' => '已见 URL 保留天数',
	'NEWSSCRAPER_SEEN_RETENTION_DAYS_EXPLAIN' => '超过此天数的 rejected/failed URL 标记会被清理；已发文 URL 标记保留。',
	'NEWSSCRAPER_SOURCES_EXPLAIN' => '新华社世界新闻作为明确新闻页可用，但默认未启用。官方/政府类来源只应使用窄范围新闻页。',
	'NEWSSCRAPER_API_FALLBACK_EXPLAIN' => 'endpoint/model/API key 留空时会优先使用 topicmover 的 DeepSeek 设置。这样默认只维护一个密钥。',
	'NEWSSCRAPER_API_ENDPOINT' => 'API endpoint 覆盖',
	'NEWSSCRAPER_API_ENDPOINT_EXPLAIN' => 'OpenAI 兼容 chat completions endpoint。留空时依次使用 topicmover、DeepSeek 默认值。',
	'NEWSSCRAPER_MODEL' => '模型覆盖',
	'NEWSSCRAPER_MODEL_EXPLAIN' => '模型名称。留空时依次使用 topicmover、deepseek-v4-flash，也支持 deepseek-v4-pro。',
	'NEWSSCRAPER_API_KEY' => 'API key 覆盖',
	'NEWSSCRAPER_API_KEY_EXPLAIN' => '留空表示保留当前 scraper key；如果未保存 scraper key，则使用 topicmover API key。',
	'NEWSSCRAPER_API_KEY_CLEAR' => '清除已保存的 scraper API key',
	'NEWSSCRAPER_USING_TOPICMOVER_API_KEY' => '当前未保存 scraper key，将使用 topicmover API key。',
]);
