# 新闻文摘 cited source inventory

Generated from `db_2026-06-08.sql.gz` on 2026-06-08.

## Method

- Scope: `phpbb_posts.post_text` from the production dump.
- Extracted unique `http` and `https` URLs from the `phpbb_posts` insert data.
- Normalized hosts by lowercasing and removing `www.`, `m.`, and `amp.` prefixes.
- Counted unique URLs by host. Counts below are cited URL counts, not post counts.
- Excluded internal board links, image/CDN hosts, generic file hosts, forums, wikis, social/video platforms, and developer/tooling sites from the scraper seed list.

Raw extraction summary:

- Approximate posts in dump: 45,797.
- Unique URLs found in posts: 13,287.
- Normalized domains found in posts: 519.

Top excluded non-news/source hosts:

| Count | Host | Reason |
|---:|---|---|
| 2709 | `i.postimg.cc` | image host |
| 1865 | `uploads.themitbbs.com` | board upload host |
| 1564 | `postimg.cc` | image host |
| 1288 | `youtube.com` | video platform |
| 1188 | `x.com` | social platform |
| 529 | `tradingview.com` | chart/widget host |
| 386 | `zh.wikipedia.org` | reference site |
| 377 | `youtu.be` | video platform |
| 344 | `upload.wikimedia.org` | media host |
| 199 | `zhihu.com` | UGC/social knowledge site |
| 191 | `freemitbbs.com` | internal board |
| 149 | `themitbbs.com` | related board/internal |
| 136 | `bilibili.com` | video platform |
| 125 | `newmitbbs.com` | forum |
| 114 | `v.douyin.com` | video/social platform |

## Initial scraper seed list

These are the best first candidates for `newsscraper`: repeatedly cited, article-like, and likely to have stable article pages or feeds. Counts group obvious host variants where useful.

V1 scope decisions:

- Only crawlable article pages are in scope for broad news scraping. News aggregation sites, forums, and UGC-heavy sites may be used when they expose a stable top-posts feed/ranking page, such as Zhihu hot lists or Hacker News.
- Use a two-stage AI pipeline: first send only candidate titles and source metadata to AI for audience-interest filtering; fetch full content and generate Chinese digest only for selected items.
- Digest titles must fit one line in a two-column front-page block. Target and enforce a strict maximum of about 22 Chinese characters.
- The maximum number of digest items from one source is an ACP setting.
- The total number of digest items shown on the front page is an ACP setting.
- Official/government sites should not be broad-scraped. They may be added later only through narrowly scoped press-release/news pages.

Recommended scraper pipeline:

1. Fetch source feed/listing pages and extract URL, title, source, publish time, and optional short description.
2. Deduplicate by canonical URL/hash and skip already-seen URLs.
3. Send a compact title list to AI with instructions to select items likely to interest this BBS audience.
4. Store rejected candidates with reason/score so they are not re-evaluated repeatedly.
5. Fetch full article text only for selected URLs.
6. Generate Chinese title/content digest for selected URLs and post/store them for the `新闻文摘` forum/front-page block. AI-generated digest titles must be no longer than about 22 Chinese characters; the server should truncate defensively if needed.

Useful ACP settings for this stage:

- Candidate titles evaluated per run.
- Maximum selected articles per run.
- Minimum AI interest score for full-article digest generation.
- Per-source cap and total front-page digest count.
- Optional source allow/disable toggles.
- Digest title maximum length, default 22 characters.
- Source type, such as article feed, news listing page, or aggregator top-posts feed.

| Count | Source | Host variants seen |
|---:|---|---|
| 97 | The Guardian | `theguardian.com` |
| 76 | ZeroHedge | `zerohedge.com`, `cms.zerohedge.com` |
| 68 | Yahoo Finance/News | `finance.yahoo.com`, `yahoo.com`, `tw.news.yahoo.com`, `news.yahoo.co.jp`, `sports.yahoo.com`, `health.yahoo.com` |
| 46 | Reuters | `reuters.com` |
| 32 | CNN | `cnn.com`, `edition.cnn.com` |
| 27 | Associated Press | `apnews.com` |
| 25 | New York Post | `nypost.com` |
| 25 | BBC | `bbc.com` |
| 24 | New York Times | `nytimes.com` |
| 23 | Sina | `news.sina.com.cn`, `finance.sina.com.cn`, `finance.sina.cn`, `cj.sina.com.cn`, `collection.sina.cn`, `sina.cn` |
| 20 | The Hill | `thehill.com` |
| 19 | Wall Street Journal | `wsj.com` |
| 18 | Bloomberg | `bloomberg.com` |
| 15 | NBC News | `nbcnews.com` |
| 12 | The American Conservative | `theamericanconservative.com` |
| 12 | Sohu | `sohu.com` |
| 12 | Times of Israel | `timesofisrael.com`, `blogs.timesofisrael.com` |
| 11 | DW | `p.dw.com`, `dw.com` |
| 11 | MSN | `msn.com` |
| 10 | Fox News | `foxnews.com` |
| 10 | Guancha | `guancha.cn`, `user.guancha.cn` |
| 10 | The Independent | `the-independent.com`, `independent.co.uk` |
| 10 | Xinhua | `news.cn`, `xinhuanet.com` |
| 9 | Daily Mail | `dailymail.com`, `dailymail.co.uk` |
| 8 | Newsweek | `newsweek.com` |
| 8 | The Daily Beast | `thedailybeast.com` |
| 8 | Wenxuecity | `wenxuecity.com`, `bbs.wenxuecity.com` |
| 7 | Washington Post | `washingtonpost.com` |
| 6 | Foreign Policy | `foreignpolicy.com` |
| 6 | HK01 | `global.hk01.com`, `hk01.com` |
| 6 | Mediaite | `mediaite.com` |
| 6 | NetEase | `163.com` |
| 6 | QQ News | `news.qq.com`, `view.inews.qq.com` |
| 6 | The Telegraph | `telegraph.co.uk` |
| 5 | CNBC | `cnbc.com` |
| 5 | Politico | `politico.com`, `politico.eu` |
| 4 | Ars Technica | `arstechnica.com` |
| 4 | Asia Nikkei | `asia.nikkei.com` |
| 4 | CBS News | `cbsnews.com` |
| 4 | CCTV/CNTV | `news.cctv.com`, `news.cntv.cn` |
| 4 | CNR | `cnr.cn`, `news.cnr.cn` |
| 4 | Chosun | `chosun.com`, `cnnews.chosun.com` |
| 4 | Fortune | `fortune.com` |
| 4 | Jerusalem Post | `jpost.com` |
| 4 | LA Times | `latimes.com` |
| 4 | NDTV | `ndtv.com` |
| 4 | RFI | `rfi.fr` |
| 4 | WION | `wionews.com` |
| 4 | Zaobao | `zaobao.com.sg` |

## Long-tail candidates

These were cited less often, but are plausible sources to support after the first scraper pass.

| Count | Source/domain |
|---:|---|
| 3 | `chinadigitaltimes.net` |
| 3 | `economictimes.indiatimes.com` |
| 3 | `indiatoday.in` |
| 3 | `mirror.co.uk` |
| 3 | `nature.com` |
| 3 | `navalnews.com` |
| 3 | `thegatewaypundit.com` |
| 3 | `futurism.com` |
| 2 | `aa.com.tr` |
| 2 | `abc.net.au` |
| 2 | `abc7.com` |
| 2 | `afr.com` |
| 2 | `asiatimes.com` |
| 2 | `atlantablackstar.com` |
| 2 | `business-standard.com` |
| 2 | `businessinsider.com` |
| 2 | `businesstimes.com.sg` |
| 2 | `channelnewsasia.com` |
| 2 | `chicago.suntimes.com` |
| 2 | `chinanews.com.cn` |
| 2 | `ctvnews.ca` |
| 2 | `digitaltrends.com` |
| 2 | `economist.com` |
| 2 | `ft.com` |
| 2 | `i24news.tv` |
| 2 | `hk.on.cc` |
| 2 | `military.com` |
| 2 | `militarywatchmagazine.com` |
| 2 | `metro.co.uk` |
| 2 | `nationalreview.com` |
| 2 | `npr.org` |
| 2 | `oregonlive.com` |
| 2 | `paloaltoonline.com` |
| 2 | `pbs.org` |
| 2 | `people.com` |
| 2 | `republicworld.com` |
| 2 | `rfa.org` |
| 2 | `scmp.com` |
| 2 | `seattletimes.com` |
| 2 | `sfchronicle.com` |
| 2 | `sfgate.com` |
| 2 | `stheadline.com` |
| 2 | `techspot.com` |
| 2 | `technologyreview.com` |
| 2 | `theintercept.com` |
| 2 | `themirror.com` |
| 2 | `thesun.co.uk` |
| 2 | `timesofindia.indiatimes.com` |
| 2 | `tribune.com.pk` |
| 2 | `turkiyetoday.com` |
| 2 | `voachinese.com` |
| 2 | `wired.com` |
| 1 | `arabnews.com` |
| 1 | `cdc.gov` |
| 1 | `cna.com.tw` |
| 1 | `ctinews.com` |
| 1 | `federalreserve.gov` |
| 1 | `jiemian.com` |
| 1 | `koreaherald.com` |
| 1 | `kyivpost.com` |
| 1 | `news-pravda.com` |
| 1 | `news.bjx.com.cn` |
| 1 | `news.sciencenet.cn` |
| 1 | `oe24.at` |
| 1 | `phys.org` |
| 1 | `science.org` |
| 1 | `shicheng.news` |
| 1 | `techxplore.com` |
| 1 | `the-sun.com` |
| 1 | `whitehouse.gov` |

## Sources to handle separately

These are cited often, but should not be broad-scraped as normal article sites. Some may be used through explicit top-posts feeds/ranking pages:

- Social/video: `x.com`, `twitter.com`, `youtube.com`, `youtu.be`, `bilibili.com`, `douyin.com`, `tiktok.com`, `xiaohongshu.com`, `instagram.com`, `facebook.com`, `reddit.com`, `t.me`.
- UGC/knowledge platforms: `zhihu.com`, `zhuanlan.zhihu.com`, `medium.com`, `substack.com`, `mp.weixin.qq.com`. Use only explicit hot/top feeds in v1 unless a source has stable article markup.
- Forums/community sites: `newmitbbs.com`, `huaren.us`, `mitbbs.cn`, `wforum.com`, `1point3acres.com`, `backchina.com`. Use only explicit top-posts feeds/ranking pages.
- Reference/content stores: `wikipedia.org`, `wikimedia.org`, `arxiv.org`, `britannica.com`.
- Market/chart tools: `tradingview.com`, `polymarket.com`, `seekingalpha.com`, `moomoo.com`.
- Official/government sites: `mfa.gov.cn`, `fmprc.gov.cn`, `whitehouse.gov`, `cdc.gov`, `federalreserve.gov`, `travel.state.gov`, `justice.gov`, `scio.gov.cn`, `mofcom.gov.cn`. These are not broad news sources; support only explicit press-release/news URLs if configured.

Examples of aggregator/top-feed source support:

- Zhihu hot list/top stories: ingest titles/links from a stable ranking endpoint or page, then AI-prefilter titles before fetching detail pages.
- Hacker News front page/top/newest: ingest titles/links from the official item feed/API, then AI-prefilter for BBS audience interest.
- Reddit/Hacker News-style sources: prefer official JSON/RSS/API feeds over scraping rendered HTML.

## Scrape probe results

Probe run on 2026-06-08 with a browser-like user agent. The probe checked two needs separately:

- Title discovery: can we get a current title/link/date list from RSS, JSON/API, or listing HTML?
- Article extraction: can we fetch enough article body text for a Chinese digest without browser automation?

Ready for v1 title discovery and full digest generation:

| Source | Title discovery | Article extraction | Notes |
|---|---|---|---|
| The Guardian | RSS works | Generic article paragraphs work | Strong v1 source. |
| BBC | RSS works | Generic article paragraphs work | Strong v1 source. |
| DW | RSS works | Generic article paragraphs work | Strong v1 source. |
| CNBC | RSS works | Generic article paragraphs mostly work | Keep an eye on bot/JS markers; use feed first. |
| Daily Mail | RSS works | Generic article paragraphs work | Noisy source; AI title filter should be strict. |
| Ars Technica | RSS works | Generic article paragraphs work | Good for tech/AI items. |
| ZeroHedge | RSS works | Generic article paragraphs work | Strong cited source; cap per-source output. |
| Fox News | RSS-like XML works | JSON-LD `articleBody` works | Strong v1 source. |
| Sohu | Listing HTML works | Generic article paragraphs work | Needs HTML-listing source config. |
| Sina World | Listing HTML works | Source-specific selector works | Use `#article_content p`; old Sina RSS is stale and should not be used. |
| Xinhua | Listing HTML works | Source-specific selector works | Use `#detailContent p`; broad official scraping still excluded except explicit news pages. |
| Wenxuecity | Listing HTML works | `#articleContent p` works | Good fit for the front-page style. |
| Zaobao | Listing HTML works | Generic article paragraphs work | Strong Chinese-language source. |
| Hacker News | Official JSON API works | External target varies | Use as title/top-feed source; fetch target articles by target host rules. |

Good for title discovery, but not reliable for full digest without special handling:

| Source | Title discovery | Article extraction | Notes |
|---|---|---|---|
| NYTimes | RSS works | Article fetch returned 403 | Use only if RSS description is enough or skip full digest. |
| The Hill | RSS works | Article fetch returned 403 | Good title feed, poor direct body access from this environment. |
| Bloomberg | RSS works | Article fetch returned 403 | Use RSS for title discovery; full body likely unavailable. |
| Washington Post | RSS works | Article fetch reset/failed | Title discovery works; full body unreliable. |
| WSJ | RSS works but was stale in probe | Article page gave only short/paywalled text | Not a strong v1 source unless a fresher feed is found. |
| RFI Chinese | RSS works | Article fetch returned 403 | RSS can seed titles; full body needs special handling. |
| AP | Listing HTML works and advertises RSS | RSS endpoint returned 401 | Needs source-specific listing parser rather than RSS. |

Skip or special handling for v1:

| Source | Probe result | Decision |
|---|---|---|
| Reuters | Listing returned 401; RSS guess returned 404 | Skip until a stable allowed feed/API is found. |
| CNN | HTTP RSS worked but returned stale 2023 items | Skip current RSS endpoints found in probe. |
| Politico | RSS guesses returned 404 | Skip until a current feed URL is found. |
| Zhihu | Hot-list API returned 401; billboard HTML returned 403 | Needs special cookies/API handling; skip v1 unless a stable accessible endpoint is found. |
| QQ News | Listing pages returned HTML but generic extraction found no useful anchors | Needs JS/API-specific handling; skip v1. |
| NetEase | Listing pages returned mostly navigation links with generic extraction | Needs source-specific parsing; lower priority. |
| Guancha | Homepage listing works, but article fetch returned bot-cookie JavaScript challenge | Use only for titles unless article handling is solved. |

Implementation notes from the probe:

- Prefer RSS/Atom/JSON/API where available; they reliably provide title/link/date and reduce scraper fragility.
- Store source type per source: `feed`, `html_listing`, `aggregator_api`, or `special`.
- Article-body extraction should support a generic paragraph strategy plus source-specific selectors.
- Initial source-specific selectors worth implementing: `#article_content p` for Sina, `#detailContent p` for Xinhua, and `#articleContent p` for Wenxuecity.
- Keep blocked/paywalled sources in the candidate-title layer only unless the feed includes enough legal excerpt text for a short digest.

## Resolved product questions

1. First release uses crawlable article pages for broad news scraping; aggregation/forum sources are allowed only through stable top-posts feeds/ranking pages.
2. Per-source cap and total front-page digest count are ACP settings.
3. Official/government sources are excluded from broad scraping unless limited to press-release/news pages.
4. AI prefilters title lists before full article fetch/translation/digest generation.
5. Digest titles are constrained to a single-line two-column layout, default maximum about 22 Chinese characters.
