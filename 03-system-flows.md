# WordPress Video Lead Tracking Plugin - System Flows

## 1. Purpose

This document defines the exact system flows for frontend user behavior, backend processing, identity resolution, video tracking, range tracking, summary generation, and admin reporting.

The goal is to remove ambiguity during implementation.

---

# 2. Main User Flows

## 2.1 First-time Visitor Flow

### Preconditions

- User opens the public video page from a social media link.
- No existing valid browser identity is stored.

### Steps

1. Frontend loads.
2. JS checks localStorage and cookie for existing identity.
3. No valid identity exists.
4. Frontend calls:

POST /wp-json/vlt/v1/session/init

5. Backend creates:
   - new `visitor_uuid`
   - new `session_uuid`
   - new `wp_vlt_visitors` row
   - new `wp_vlt_sessions` row
6. Backend returns:
   - `visitor_uuid`
   - `session_uuid`
   - `known_lead = false`
   - `show_form = true`
7. Frontend stores visitor/session token.
8. Form is shown.
9. Video remains hidden.
10. Page view event is tracked.

### Expected Result

- Visitor exists anonymously.
- Session exists.
- User sees form.

---

## 2.2 First-time Visitor Submits Form

### Preconditions

- Anonymous visitor/session already exists.
- Form is visible.

### Steps

1. User enters:
   - name
   - mobile number
2. Frontend validates required fields.
3. Frontend submits:

http
POST /wp-json/vlt/v1/lead/submit

4. Backend normalizes mobile number.
5. Backend searches for existing lead by normalized mobile.

#### If lead does not exist:

- create new row in `wp_vlt_leads`

#### If lead exists:

- reuse existing `lead_id`

6. Insert submitted name into `wp_vlt_lead_names`.
7. Attach `visitor_uuid` to `lead_id`.
8. Attach `session_uuid` to `lead_id`.
9. Reattach any existing anonymous page visits/events/ranges of same visitor/session to this `lead_id`.
10. Generate `identity_token`.
11. Save token hash in `wp_vlt_visitors`.
12. Return:
   - `lead_id`
   - `identity_token`
   - `show_video = true`
13. Frontend stores identity in localStorage and cookie.
14. Form is hidden.
15. Video is shown.

### Expected Result

- Lead is created or resolved.
- Browser is remembered.
- Video becomes available.

---

## 2.3 Returning Visitor on Same Browser

### Preconditions

- User already submitted form previously.
- Browser still has valid localStorage or cookie identity.

### Steps

1. User opens same page again later.
2. Frontend reads localStorage/cookie.
3. Frontend calls:

http
POST /wp-json/vlt/v1/session/init

with stored `visitor_uuid` and `identity_token`.

4. Backend validates identity.
5. Backend creates a new `session_uuid` for the new visit.
6. Backend returns:
   - same `visitor_uuid`
   - new `session_uuid`
   - resolved `lead_id`
   - `known_lead = true`
   - `show_form = false`
7. Frontend hides form.
8. Frontend shows video directly.

### Expected Result

- User is not asked for name/mobile again.
- New session is created.
- Activity continues under same lead.

---

## 2.4 Same Person Returns from Another Device

### Preconditions

- The user previously submitted form on device A.
- Device B has no browser identity.
- The user enters the same mobile number again.

### Steps

1. Device B visits page.
2. Session init creates a new anonymous visitor/session.
3. Form is shown.
4. User submits same mobile number.
5. Backend normalizes mobile.
6. Backend finds existing lead by mobile.
7. Backend attaches new `visitor_uuid` and `session_uuid` to the same `lead_id`.
8. Backend inserts new submitted name into `wp_vlt_lead_names`.
9. Future data from device B counts toward same lead summary.

### Expected Result

- Both devices belong to one lead.
- Stats are merged by mobile number.

---

## 2.5 Same Mobile with Different Names

### Preconditions

- Existing lead already exists for mobile number.

### Steps

1. User submits same mobile again with a different name.
2. Backend resolves same `lead_id`.
3. Backend inserts new row into `wp_vlt_lead_names`.
4. Existing lead remains unchanged except optional `last_seen_at`.
5. Lead detail page shows all names ever submitted.

### Expected Result

- One lead per mobile.
- Multiple names preserved historically.

---

# 3. Session and Visitor Flows

## 3.1 Session Definition

A new session is created on each page entry/init.

A session represents one visit instance.

### Session ends when

- page unload occurs
- long inactivity timeout occurs
- browser closes
- or simply remains open-ended with `last_activity_at`

Recommended implementation:

- set `started_at`
- update `last_activity_at`
- set `ended_at` on pagehide/unload if possible
- if not possible, session can remain with null ended_at

---

## 3.2 Visitor Definition

A visitor is a browser/device identity.

A visitor can have:

- zero or one lead initially
- later be attached to a lead after form submission

One lead may have many visitors.

Example:

- mobile browser -> visitor A
- laptop browser -> visitor B
- both attached to lead 15

---

# 4. Page Tracking Flow

## 4.1 On Initial Page Load

Frontend should:

1. init session
2. send `page_view`

Example payload:

json
{
  "visitor_uuid": "uuid-1",
  "session_uuid": "uuid-2",
  "identity_token": "token",
  "event_type": "page_view",
  "page_url": "https://example.com/free-business-training/",
  "time_on_page_seconds": 0,
  "metadata": {
"document_title": "Free Business Training"
  }
}

---

## 4.2 Page Visibility Changes

Use browser Visibility API.

### On hidden

- send `page_hidden`
- commit active video range if video is currently playing

### On visible

- send `page_visible`

---

## 4.3 On Unload / Page Exit

Use:

- `navigator.sendBeacon()` if available
- fallback to `fetch(..., { keepalive: true })`

Send:

- `page_unload`
- final video range commit if needed

---

# 5. Video Tracking Flow

## 5.1 Video Initialization

When HTML5 video metadata is loaded:

1. detect duration
2. send `video_loaded` event
3. verify backend video record exists by `video_key`
4. if needed create/update video record

---

## 5.2 Play Flow

When user clicks play:

1. if not already playing:
   - set `isPlaying = true`
   - set `rangeStart = currentTime`
   - set `lastVideoTime = currentTime`
2. send `play` event

---

## 5.3 Pause Flow

When user pauses:

1. if `isPlaying = true`:
   - commit range from `rangeStart` to currentTime
2. set `isPlaying = false`
3. clear `rangeStart`
4. send `pause` event

---

## 5.4 Seek Flow

### On `seeking`

1. if currently playing:
   - commit current active range from `rangeStart` to currentTime before seek jump
2. send `seek_start`

### On `seeked`

1. send `seek_end`
2. if video is still playing:
   - set `rangeStart = currentTime`
   - continue tracking from new position

Important:

Skipped area must not count as watched.

---

## 5.5 Ended Flow

When video ends:

1. if currently playing:
   - commit range from `rangeStart` to duration
2. set `isPlaying = false`
3. send `ended`

---

## 5.6 Heartbeat Flow

Frontend should run heartbeat while video is playing.

Recommended interval:

text
10 seconds

At each heartbeat:

1. send lightweight `heartbeat` event or progress event
2. optionally commit current range chunk for reliability
3. continue active range from current video time

Recommended implementation:

- commit chunk every heartbeat for crash resilience
- reset `rangeStart` to latest currentTime after successful commit

This reduces loss of data if browser/tab closes unexpectedly.

---

# 6. Range Commit Logic

## 6.1 Commit Conditions

A video watch range should be committed when one of the following happens:

- pause
- seek
- ended
- page hidden
- page unload
- heartbeat checkpoint
- manual recovery

---

## 6.2 Valid Range Rules

A range is valid if:

- `to_second > from_second`
- `(to_second - from_second) >= minimum_valid_range_seconds`

Recommended default:

text
1 second

---

## 6.3 Normalization Rules

Before saving:

- round values to 3 decimal places for raw storage
- for heatmap aggregation, use integer seconds

Example:

text
from_second = 10.234
to_second = 15.981

Raw range stored exactly to 3 decimals.

Heatmap affected seconds:

text
10, 11, 12, 13, 14, 15

---

## 6.4 Heartbeat Chunking Strategy

Example:

- user starts at 0
- watches continuously to 37

If heartbeat every 10 seconds:

Stored ranges may become:

text
0-10
10-20
20-30
30-37

This is acceptable and recommended for reliability.

Merged ranges later become:

text
0-37

---

# 7. Identity Resolution Flow

## 7.1 Valid Browser Identity

A browser identity is valid if:

- `visitor_uuid` exists
- `identity_token` exists
- token hash matches backend visitor record
- optional expiration policy passes (current requirement: no expiration)

If valid:

- restore same visitor
- create new session
- resolve associated lead if exists

---

## 7.2 Invalid Browser Identity

If localStorage or cookie contains invalid token:

- backend responds `identity_invalid = true`
- frontend clears localStorage and cookie
- frontend calls fresh session init or shows form

---

## 7.3 Merging Anonymous Data into Lead

When anonymous visitor submits form:

- update all records of same `visitor_uuid` and current `session_uuid`
- attach `lead_id` where missing
- optionally update older anonymous records for same visitor too

Recommended behavior:

Attach all previous anonymous records of same `visitor_uuid` to new `lead_id`, because this browser belongs to same person once identified.

---

# 8. Admin Reporting Flow

## 8.1 Overview Page Load

1. Admin opens overview.
2. Backend queries summary metrics from aggregate tables and direct SQL.
3. Date filters may be applied.
4. Show cards, charts, top referrers, and source breakdown.

---

## 8.2 Leads Page Load

1. Admin opens leads list.
2. Backend runs paginated query.
3. Filters may include:
   - date range
   - name/mobile search
   - started video
   - reached end
   - min watch percent
   - source/UTM
4. Render table.

---

## 8.3 Lead Detail Page Load

1. Admin clicks one lead.
2. Backend loads:
   - lead record
   - all names
   - visitors
   - sessions
   - page visits
   - video events
   - video ranges
   - summary
3. Render timeline and stats.

---

## 8.4 Heatmap Page Load

1. Admin selects video.
2. Backend loads cached heatmap or rebuilds if missing.
3. Bucket size selected by UI.
4. Aggregate second-level heatmap into bucket-level display.
5. Render chart and table.

---

# 9. Export Flow

## 9.1 Leads Export

1. Admin chooses filters.
2. Admin clicks export.
3. Backend validates admin capability.
4. Backend queries filtered data.
5. Backend builds XLSX or CSV file.
6. File is streamed for download.

---

## 9.2 Heatmap Export

1. Admin selects video and date/filter if supported.
2. Backend returns rows like:

text
second_index | total_views_count | unique_leads_count

or aggregated bucket rows.

---

# 10. OTP Flow Optional

## 10.1 OTP Send

1. User enters name + mobile.
2. Frontend calls `otp/send`.
3. Backend:
   - normalizes mobile
   - generates OTP
   - stores hash
   - sends SMS
4. Frontend shows OTP input.

---

## 10.2 OTP Verify

1. User enters received code.
2. Frontend calls `otp/verify`.
3. Backend validates code.
4. If valid:
   - create/resolve lead
   - attach visitor/session
   - mark lead verified
   - return identity token
5. Frontend stores identity and shows video.

---

# 11. Error Handling Flows

## 11.1 Lead Submit Failure

If lead submission fails:

- show frontend error
- do not show video
- keep form visible
- log error in `wp_vlt_logs`

---

## 11.2 Tracking Failure

If tracking event/range API fails:

- optionally queue event client-side in memory
- retry lightweight if feasible
- do not block video playback
- log repeated backend failures

---

## 11.3 Session Init Failure

If session init fails:

- show safe fallback message
- optionally still show form but tracking disabled
- log failure

---

# 12. Priority of Data Correctness

Implementation priority should be:

1. Preserve raw ranges correctly
2. Resolve lead by mobile correctly
3. Avoid duplicate leads for same mobile
4. Avoid asking form again on same browser
5. Generate correct summaries from raw data
6. Generate heatmap from raw data

If any conflict arises, raw range correctness is more important than precomputed summary convenience.
