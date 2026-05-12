# WordPress Video Lead Tracking Plugin - Tracking and Reporting Rules

## 1. Purpose

This document defines the exact computation rules for identity, tracking, summaries, reports, and heatmap generation.

This is the source of truth for data interpretation.

---

# 2. Identity Rules

## 2.1 Unique Lead Identity

A unique lead is identified by:

normalized_mobile

This is the only business-level unique identifier for a person.

### Consequences

- Same mobile submitted multiple times = one lead
- Same mobile on multiple devices = one lead
- Same mobile with different names = one lead

---

## 2.2 Name Rules

Every submitted name must be preserved.

The system should not overwrite historical names.

Recommended behavior:

- `wp_vlt_lead_names` stores all submissions
- `wp_vlt_leads.primary_name` may be:
  - the first non-empty submitted name
  - or the latest non-empty submitted name
  - or admin-configurable later

Recommended default:

text
primary_name = first non-empty submitted name

---

## 2.3 Visitor Rules

A visitor is browser/device-based.

A visitor can later become associated with a lead.

A lead may have multiple visitors.

---

## 2.4 Session Rules

A session is page-entry-based.

Each new page open/init creates a new session.

A session belongs to one visitor and may belong to one lead.

---

# 3. Mobile Normalization Rules

Accepted examples:

- 09123456789
- 9123456789
- +989123456789
- 00989123456789
- 989123456789

Recommended normalized output:

text
989123456789

## 3.1 Suggested Algorithm

1. Remove spaces, dashes, parentheses, non-numeric except leading `+`
2. Convert:
   - `+98xxxxxxxxxx` -> `98xxxxxxxxxx`
   - `0098xxxxxxxxxx` -> `98xxxxxxxxxx`
   - `09xxxxxxxxx` -> `98xxxxxxxxxx`
   - `9xxxxxxxxx` -> `98xxxxxxxxxx`
3. Reject if final format is not exactly:

text
98 + 10 digits

---

# 4. Video Tracking Rules

## 4.1 Business Meaning of "Watched"

A part of video is considered watched only if playback actually passed through that timeline range.

### It is NOT watched if

- user skipped via seek
- user only loaded metadata
- user moved scrubber without playing through range

---

## 4.2 Event Meaning

### `video_loaded`

Metadata loaded. Does not mean user watched anything.

### `play`

User started or resumed playback.

### `pause`

User paused playback.

### `seek_start`

User started seeking.

### `seek_end`

User finished seeking.

### `heartbeat`

Periodic reliability/progress marker.

### `range_commit`

A confirmed watched timeline segment.

### `ended`

Playback reached natural end.

### `error`

Video playback error occurred.

### `rate_change`

Playback speed changed.

---

# 5. Range Rules

## 5.1 Raw Range Storage Rule

Every valid committed watch segment must be stored in `wp_vlt_video_ranges`.

Raw ranges must not be merged before storage.

This is important for:

- replay analysis
- heatmap accuracy
- future recalculation

---

## 5.2 Range Validity Rule

A range is valid only if:

text
to_second > from_second
duration >= minimum_valid_range_seconds

Default minimum:

text
1 second

---

## 5.3 Merge Rule for Individual Unique Watch

For per-lead summary, overlapping and adjacent ranges should be merged.

Example input:

text
[0,5], [3,8], [20,30], [30,40]

Merged output:

text
[0,8], [20,40]

Recommended adjacency rule:

If the end of one range is equal to or very close to the start of the next range, merge them.

Use tolerance:

text
0.25 seconds

So if:

text
[10.0, 20.0], [20.1, 30.0]

they may be merged if gap tolerance allows.

Recommended default:

- merge overlapping ranges
- merge contiguous ranges if gap <= 0.25 sec

---

## 5.4 Total Watch Seconds Rule

`total_watch_seconds` is the sum of durations of all valid raw ranges.

Example:

Raw ranges:

text
[0,10], [20,40], [30,50]

Durations:

- 10
- 20
- 20

Total:

text
50

Even though some parts overlap, total watch counts replay.

---

## 5.5 Unique Watch Seconds Rule

`unique_watch_seconds` is the total length of merged ranges.

Example:

Raw ranges:

text
[0,10], [20,40], [30,50]

Merged:

text
[0,10], [20,50]

Unique watch seconds:

text
10 + 30 = 40

---

## 5.6 Unique Watch Percent Rule

If video duration is known and > 0:

text
unique_watch_percent = (unique_watch_seconds / video_duration_seconds) * 100

Round to 2 decimals.

Cap at 100 if rounding exceeds 100 slightly.

---

## 5.7 Max Video Time Rule

`max_video_time_seconds` is the maximum `to_second` across all valid raw ranges or explicit ended position.

This indicates how far into the video the user has reached.

This is not the same as unique watch.

Example:

User watches:

text
0-20
200-250

Then:

- max_video_time_seconds = 250
- unique_watch_seconds = 70

---

## 5.8 Reached End Rule

Because there is no business completion threshold, `reached_end` is only a derived metric.

Set `reached_end = true` if either:

1. `ended` event exists
2. or `max_video_time_seconds >= duration_seconds - tolerance`

Recommended tolerance:

text
2 seconds

---

# 6. Heatmap Rules

## 6.1 Heatmap Data Source

Heatmap must be generated from raw committed ranges.

Not from play events.

Not from summary only.

---

## 6.2 Per-second Increment Rule

For a raw range:

text
[from_second, to_second)

Convert to second indexes:

- start = floor(from_second)
- end = ceil(to_second) - 1

Example:

text
from = 10.2
to = 15.7

Affected seconds:

text
10, 11, 12, 13, 14, 15

Each affected second gets:

text
total_views_count += 1

---

## 6.3 Replay Counting Rule

If the same lead watches the same second multiple times, that second should count multiple times in `total_views_count`.

Example:

User ranges:

text
0-10
5-15

Seconds 5-9 are counted twice in total views.

This is required behavior.

---

## 6.4 Unique Leads Per Second Rule

If supported, `unique_leads_count` counts each lead only once per second, even if replayed many times.

Example:

Lead watches second 8 three times.

Then:

- total_views_count += 3
- unique_leads_count += 1

Because this can be expensive, it may be computed through:
- periodic rebuild
- secondary uniqueness table
- cached query

---

## 6.5 Heatmap Display Buckets

Storage remains per-second.

UI may group into buckets.

Example bucket size = 5 seconds:

- bucket 0 = seconds 0-4
- bucket 1 = seconds 5-9
- etc.

Bucket metrics may use:

- sum of total views
- sum or average depending on chart design
- recommended: sum total views, optional average per second

---

# 7. Page Report Rules

## 7.1 Total Visitors

Count distinct `visitor_uuid`.

## 7.2 Total Leads

Count distinct `lead_id`.

## 7.3 Total Sessions

Count distinct `session_uuid`.

## 7.4 Total Page Views

Count rows in `wp_vlt_page_visits` where `event_type = page_view`.

## 7.5 Returning Visitors

A returning visitor is a visitor with more than one session.

---

# 8. Video Report Rules

## 8.1 Video Started

A lead is considered to have started video if one of these is true:

1. at least one `play` event exists
2. at least one valid raw range exists
3. summary `started = true`

Recommended source of truth:

text
summary.started

which should be set during aggregation.

---

## 8.2 Did Not Start Video

A lead did not start video if:

- lead exists
- lead visited gated page or submitted form
- no play event
- no valid range
- summary.started = false

---

## 8.3 Watch Distribution

Reports may group leads by unique watch percent ranges, for example:

- 0%
- 0-10%
- 10-25%
- 25-50%
- 50-75%
- 75-100%

---

# 9. "Community" Reporting Rules

Because traffic is public and there is no imported target audience list, the system cannot know people who never visited the page.

Therefore, "community report" must be defined only within known tracked users.

Valid community segments:

1. Anonymous visitors who opened page
2. Leads who submitted form
3. Leads who started video
4. Leads who did not start video
5. Leads who reached end
6. Returning visitors
7. Leads with low watch percent
8. Leads with high watch percent

Invalid report concept:

- "All Instagram followers who did not come"

This is out of scope because no audience master list exists.

---

# 10. Returning User Rules

## 10.1 Same Browser Return

If browser identity is valid:

- user should not see form again
- new session should be created
- all new activity belongs to same lead

## 10.2 Same Mobile on New Browser

If same mobile is submitted later on another browser:

- resolve same lead
- attach new visitor and session
- merge stats into same person

---

# 11. Anonymous-to-Lead Attachment Rules

If user watches some video anonymously before form submit is complete, and anonymous tracking is enabled:

- anonymous page events
- anonymous video events
- anonymous ranges

must be attached to the lead once mobile is submitted.

Recommended rule:

Attach all events/ranges for the same `visitor_uuid` that have null `lead_id`.

---

# 12. Derived Reporting Metrics

## 12.1 Average Total Watch Seconds

Average of `total_watch_seconds` across leads with summary rows.

## 12.2 Average Unique Watch Seconds

Average of `unique_watch_seconds` across leads with summary rows.

## 12.3 Average Unique Watch Percent

Average of `unique_watch_percent` across leads with summary rows.

## 12.4 Drop-off Signal

A drop-off signal can be inferred from heatmap:

- identify seconds/buckets where view counts sharply decline relative to previous region

This is visual/analytic, not a strict business rule.

---

# 13. Data Integrity Rules

## 13.1 Raw Data Priority

If summary and raw data conflict, raw data is the source of truth.

Admin should be able to rebuild summaries from raw ranges.

## 13.2 Heatmap Rebuild

Admin should be able to rebuild heatmap from raw ranges.

## 13.3 No Automatic Deletion

No tracking data should be automatically deleted.

---

# 14. Security Rules

1. Reports available only to WordPress admins.
2. REST endpoints must validate nonce/token.
3. Inputs must be sanitized.
4. SQL must use prepared statements.
5. Browser identity should not expose raw mobile if avoidable.
6. OTP, if used, must be hashed and rate-limited.

---

# 15. Export Rules

## 15.1 Export Priority

Dashboard usability > export implementation.

## 15.2 Export Encoding

CSV fallback must use:

text
UTF-8 with BOM

## 15.3 XLSX Support

Preferred final export format is XLSX.

---

# 16. Rebuild Rules

Admin tools should exist for:

- rebuild lead summary from raw ranges
- rebuild heatmap from raw ranges
- reattach orphan anonymous data where possible

These tools are important because summary/heatmap are derived data.
