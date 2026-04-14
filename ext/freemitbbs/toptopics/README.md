# Top Topics

`freemitbbs/toptopics` promotes topics onto the board index and forum pages using a time-decayed ranking model. It builds on phpBB topic metadata plus Post Love likes and this extension's dislikes.

The extension also maintains a materialized per-user reputation score that can gate negative actions such as dislikes and post reports.

## What the ranker considers

For each candidate topic inside the configured lookback window:

- `likes`: total likes across posts in the topic
- `dislikes`: total dislikes across posts in the topic
- `flags`: total open phpBB reports across posts in the topic
- `content_length`: approximate plain-text length of the first post
- `replies`: approved topic posts minus the first post
- `views`: `topic_views`
- `age_hours`: hours since `topic_time`
- `user_posts`: topic author's total post count, used as a small trust factor

Topics outside the lookback window, moved topics, and topics hidden by phpBB content visibility rules are excluded before ranking.

## User reputation and negative-action gating

User reputation is computed separately from topic ranking, but it intentionally reuses the same signal family so moderation and promotion are aligned.

### What user reputation considers

For a given user across all approved posts:

- likes received across all of the user's approved posts
- dislikes received across all of the user's approved posts
- open phpBB reports across all of the user's approved posts
- approximate plain-text length across all of the user's approved posts

This is intentionally post-centric and lifetime-based. The reputation model does not distinguish between topic starters and replies, and it does not apply age decay. Topic-level reply/view dynamics stay in the Top Topics ranker, while user reputation looks only at the quality and reception of the posts a user actually wrote.

### Reputation formula

```text
points = likes_received - dislikes_received - 2 * open_flags_received
content_signal = ln(1 + min(total_authored_content_length, 40000) / 500)

reputation =
    points
  + content_weight * content_signal
```

The only ranking weight reused here is:

- `toptopics_content_weight`

The reply/view weights remain topic-ranking controls only.

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
- `content_length_total`

The extension refreshes affected authors when reputation inputs change:

- like and dislike add/remove
- post submit or edit
- post deletion
- post visibility changes
- report open, close, and delete

Page reads only fetch the stored score. If a user has no row yet, the extension initializes it once on demand.

## Core formula

The ranker first builds a positive engagement score:

```text
points = likes - dislikes
content_signal = ln(1 + min(content_length, 4000) / 120)
reply_signal = ln(1 + replies)
view_velocity = views / max(1, age_hours)
view_signal = ln(1 + view_velocity)

signal_score =
    points
  + content_weight * content_signal
  + reply_weight * reply_signal
  + view_weight * view_signal
```

Then it applies Hacker News style time decay:

```text
rank = (signal_score - 1) / (age_hours + age_offset_hours) ^ gravity
```

Higher `rank` means the topic sorts higher in the Top Topics list. Final ordering is `rank DESC`, with newer `topic_time` winning ties.

## Meaning of the terms

- `points`: net reaction score. Likes push up, dislikes push down.
- `content_signal`: logarithmic first-post quality signal based on approximate plain-text length. It is intentionally capped so a huge opening post does not dominate ranking.
- `reply_signal`: logarithmic reply contribution. Replies help, but with diminishing returns.
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
if replies >= discussion_reply_minimum
and replies > max(1, likes) * discussion_reply_like_ratio:
    rank *= discussion_penalty
```

This is meant to push down noisy or contentious threads that generate lots of replies without strong positive signal.

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
flags == 0
replies == 0
views == 0
```

In practice, normal phpBB topics usually have at least some views, so the main filters that matter operationally are the lookback window, visibility rules, and hide thresholds.

## ACP settings that matter most

These settings have the largest effect on ranking behavior:

- `toptopics_content_weight`: how strongly opening-post quality helps before decay
- `toptopics_reply_weight`: how strongly replies help before decay
- `toptopics_view_weight`: how strongly view velocity helps before decay
- `toptopics_min_reputation_dislike`: minimum user reputation required to cast a dislike
- `toptopics_min_reputation_report`: minimum user reputation required to report a post
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
    ((net reactions)
     + (weighted first-post quality)
     + (weighted reply engagement)
     + (weighted view velocity))
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
