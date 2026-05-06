# Top Topics

`freemitbbs/toptopics` promotes topics onto the board index and forum pages using a time-decayed ranking model. It builds on phpBB topic metadata plus Post Love likes, this extension's dislikes, and general post reactions when the `post_reactions` table is available.

The extension also maintains a materialized per-user reputation score that can gate negative actions such as dislikes and post reports.

## What the ranker considers

For each candidate topic inside the configured lookback window:

- `likes`: total likes across posts in the topic
- `dislikes`: total dislikes across posts in the topic
- `reactions`: total general post reactions in the topic, counted separately from Post Love likes/dislikes
- `flags`: total open phpBB reports across posts in the topic
- `content_length`: approximate plain-text length of the first post
- `ranking_replies`: approved replies by users other than the topic author
- `replies`: approved topic posts minus the first post, used for display only
- `views`: `topic_views`
- `age_hours`: hours since `topic_time`
- `user_posts`: topic author's total post count, used as a small trust factor

Topics outside the lookback window, moved topics, and topics hidden by phpBB content visibility rules are excluded before ranking.

## User reputation and negative-action gating

User reputation is computed separately from topic ranking, but it intentionally reuses the same signal family so moderation and promotion are aligned.

### What user reputation considers

For a given user across all approved posts:

- quality length across all of the user's approved posts as a lifetime baseline
- direct feedback on the user's own posts: likes minus dislikes plus general reactions
- open phpBB reports on the user's own posts as a direct penalty

This is lifetime-based and intentionally does not use final Top Topics rank directly. Replies, likes, dislikes, and reactions belong to the authors of the posts that receive them. Topic starters are credited through feedback on their own first post, not through feedback on replies written by other users.

Quality length is not raw database text length. For reputation, the extension removes quote blocks, image/attachment/url BBCode, raw URLs, remaining BBCode tags, HTML tags, whitespace, and punctuation, then counts Unicode letters and numbers. This prevents copied quotes, markup, and link spam from inflating reputation.

### Reputation formula

```text
base_content_score =
    ln(1 + min(total_authored_quality_length, 40000) / 500)
  * toptopics_content_weight
  * 12

direct_feedback_signal =
    likes_received
  - dislikes_received * toptopics_reputation_dislike_weight
  + ln(1 + reactions_received) * toptopics_reaction_weight

direct_feedback_score =
    signed_ln(1 + abs(direct_feedback_signal)) * 16

flag_penalty = ln(1 + open_flags_received) * 12

reputation =
    base_content_score
  + direct_feedback_score
  - flag_penalty
```

The signed logarithm preserves direction: positive direct feedback raises reputation and negative direct feedback lowers it, while repeated feedback has diminishing returns. Dislikes are weighted below likes by default so controversy does not erase active contribution too quickly. Open reports remain a stronger direct penalty.

The configuration weights used here are:

- `toptopics_content_weight`
- `toptopics_reaction_weight`
- `toptopics_reputation_dislike_weight`

### Sidebar reputation badges

Post sidebars show reputation as a simple game-like badge without exposing numeric levels:

```text
迷雾写手  score < 0
初来执笔  0-99
稳定输出  100-499
好文常客  500-1999
镇版作者  2000-4999
传说写手  5000+
```

These ranges are intentionally aspirational for a young board. Early score distribution should not make the top badges easy to reach; the upper tiers are meant to remain meaningful as the site grows.

The English language pack uses equivalent short titles: `Foggy Pen`, `Fresh Ink`, `Steady Voice`, `Signal Maker`, `Forum Pillar`, and `Legendary Pen`.

The sidebar badge intentionally does not show `Lv.` or a numeric level. It shows the title, the raw reputation score, a compact icon, and a small progress bar. The raw score remains visible because it is used by the dislike/report gates, while the title provides the gamified user-facing identity.

### Gated actions

Two ACP thresholds control who may use negative actions:

- `toptopics_min_reputation_dislike`: minimum reputation required to cast a dislike
- `toptopics_min_reputation_report`: minimum reputation required to report a post

The dislike gate is enforced in the Top Topics AJAX controller. The report gate is enforced server-side through phpBB's `core.report_post_auth` event, not only in the template, so direct requests are blocked too.

### Reputation materialization

User reputation is stored in the `toptopics_user_reputation` table together with the underlying counters:

- `likes_received`
- `dislikes_received`
- `open_flags_received`
- `content_length_total`: total quality length, not raw `post_text` length

Per-post quality length is stored in `toptopics_post_quality`. Runtime updates are incremental:

- post submit/edit recalculates only that post's quality length and applies the author delta
- post approval, restore, soft-delete, and deletion add or subtract the affected stored post lengths
- like, dislike, reaction, and report changes refresh the affected post author while keeping the stored content total

The extension refreshes affected authors when reputation inputs change:

- like and dislike add/remove
- general reaction add/remove
- post submit or edit
- post deletion
- post visibility changes
- report open, close, and delete

Page reads only fetch the stored score. If a user has no row yet, the extension initializes it once on demand.

`release_1_1_11` changes the reputation formula and clears `toptopics_user_reputation` so old materialized scores are not reused. `release_1_1_12` changes the interpretation of `content_length_total` from raw length to quality length and clears the same materialized table again. `release_1_1_13` creates `toptopics_post_quality`, backfills existing posts in batches, and clears materialized reputation so future scores are rebuilt from the incremental per-post aggregate. `release_1_1_24` switches reputation to the topic/reply contribution model and clears materialized reputation again. `release_1_1_25` switches reputation to direct post feedback and clears materialized reputation again. `release_1_1_26` adds the fractional dislike reputation penalty and clears materialized reputation again.

## Core formula

The ranker first builds a positive engagement score:

```text
points = likes - dislikes
content_signal = ln(1 + min(content_length, 4000) / 120)
reply_signal = ln(1 + ranking_replies)
view_velocity = views / max(1, age_hours)
view_signal = ln(1 + view_velocity)
reaction_signal = ln(1 + reactions)

signal_score =
    points
  + content_weight * content_signal
  + reply_weight * reply_signal
  + view_weight * view_signal
  + reaction_weight * reaction_signal
```

Then it applies Hacker News style time decay:

```text
rank = (signal_score - 1) / (age_hours + age_offset_hours) ^ gravity
```

Higher `rank` means the topic sorts higher in the Top Topics list. Final ordering is `rank DESC`, with newer `topic_time` winning ties.

## Meaning of the terms

- `points`: net like score. Post Love likes push up, Top Topics dislikes push down.
- `reaction_signal`: logarithmic general-reaction contribution. This is separate from likes/dislikes and defaults to a modest weight so emoji reactions can help without dominating ranking.
- `content_signal`: logarithmic first-post quality signal based on approximate plain-text length. It is intentionally capped so a huge opening post does not dominate ranking.
- `reply_signal`: logarithmic non-author reply contribution. The topic author's own follow-up posts do not lift the topic through this term.
- `view_velocity`: views normalized by age, so recent traffic matters more than lifetime accumulated traffic.
- `age_offset_hours`: fixed cushion in the denominator. This prevents brand new topics from spiking too aggressively.
- `gravity`: time-decay exponent. Higher values make older topics drop faster.

## Boosts and penalties

After the base rank is computed, the extension applies several adjustments.

### Early velocity boost

If a topic receives enough likes quickly after creation:

```text
if early_like_count >= early_like_minimum
and early_like_count / velocity_hours >= early_velocity_threshold:
    rank *= velocity_boost
```

This favors topics that gain traction early, not just topics that eventually accumulate reactions.

### Discussion imbalance penalty

If replies are very high relative to likes:

```text
if ranking_replies >= discussion_reply_minimum
and ranking_replies > max(1, likes) * discussion_reply_like_ratio:
    rank *= discussion_penalty
```

This is meant to push down noisy or contentious threads that generate lots of non-author replies without strong positive signal.

### Report penalties

`flags` means open phpBB post reports, not a custom vote type.

```text
if flags >= flag_hard_threshold:
    rank *= flag_hard_penalty
else if flags >= flag_warning_threshold:
    rank *= flag_warning_penalty
```

Topics can also be excluded completely:

```text
if flags >= hide_flag_threshold:
    exclude topic

if points <= hide_point_threshold:
    exclude topic
```

## Manual admin overrides

Admins can apply a per-topic manual override from the topic page:

- `Normal`: no manual override
- `Boost`: multiply the final rank by `manual_boost_multiplier`
- `Demote`: multiply the final rank by `manual_demote_multiplier`
- `Kill`: remove the topic from Top Topics entirely

These overrides are applied after the organic ranking and penalty steps. `Kill` always wins. `Boost` and `Demote` are intended as a final editorial layer when admins want to correct the automatic ordering.

## Trust factor

The topic author's post count contributes a small capped multiplier:

```text
rank *= 1 + min(trust_boost_cap, log(user_posts + 2) / 50)
```

This is intentionally weak. It should nudge ranking, not dominate it.

## Candidate filtering

A topic is skipped before ranking if all tracked engagement is absent:

```text
likes == 0
dislikes == 0
reactions == 0
flags == 0
replies == 0
views == 0
```

In practice, normal phpBB topics usually have at least some views, so the main filters that matter operationally are the lookback window, visibility rules, and hide thresholds.

## Ranking and gating settings that matter most

These settings have the largest effect on ranking, visibility, and negative-action behavior:

- `toptopics_content_weight`: how strongly opening-post quality helps before decay
- `toptopics_reply_weight`: how strongly replies help before decay
- `toptopics_view_weight`: how strongly view velocity helps before decay
- `toptopics_reaction_weight`: how strongly general post reactions help before decay. The ranker uses a default of `0.3` if this config value is absent.
- `toptopics_min_reputation_dislike`: minimum user reputation required to cast a dislike
- `toptopics_min_reputation_report`: minimum user reputation required to report a post
- `toptopics_reputation_dislike_weight`: how much one dislike subtracts from reputation feedback compared with one like. The default `0.35` treats dislikes as weaker controversy signals.
- `toptopics_manual_boost_multiplier`: how strongly an admin boost lifts a topic
- `toptopics_manual_demote_multiplier`: how strongly an admin demotion pushes a topic down
- `toptopics_age_offset_hours`: how much recency is softened
- `toptopics_gravity`: how fast older topics fall
- `toptopics_velocity_boost`: how much early-like momentum is rewarded
- `toptopics_discussion_penalty`: how hard reply-heavy threads are pushed down
- `toptopics_flag_*`: moderation penalties and hide thresholds

## Practical reading of the model

The current ranking is best understood as:

```text
rank =
    ((net likes/dislikes)
     + (weighted first-post quality)
     + (weighted reply engagement)
     + (weighted view velocity)
     + (weighted general reactions))
    / time_decay
    * boosts
    * penalties
```

That means:

- fresh topics with fast engagement tend to rise
- longer, more substantial opening posts get a modest boost over one-line topics
- replies matter more than raw views
- old topics do not stay promoted just because they accumulated views over time
- open reports suppress ranking separately from the engagement terms
