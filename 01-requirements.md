# WordPress Video Lead Tracking Plugin - Product Requirements

## 1. Project Overview

We need a custom WordPress plugin for tracking users who visit a public landing page containing a free educational business video.

The page URL will be shared publicly on social media. There is no pre-imported audience list and no pre-existing customer database. Every person who submits the form becomes a lead.

The plugin must:

- Show a simple lead form before the video.
- Collect name and mobile number.
- Use mobile number as the unique identity of a user.
- Remember returning users on the same browser/device and not ask the form again.
- Merge stats across different devices if the same mobile number is submitted.
- Track HTML5 video watch behavior accurately.
- Store watched video ranges.
- Generate individual user reports.
- Generate aggregate video heatmap reports.
- Provide an admin dashboard inside WordPress.
- Allow WordPress admins to view reports.
- Provide export capability, preferably XLSX.
- Store all data permanently unless manually deleted by admin.
- Optionally support OTP verification through SMS in a later phase.

---

## 2. Main Business Flow

### 2.1 Public Traffic Source

The video page URL is shared on social networks.

Example:

https://example.com/free-business-training/

Users come from:

- Instagram
- Telegram
- WhatsApp
- LinkedIn
- Direct link
- Other social platforms

No unique link is used per user.

---

## 3. User Identity Rules

### 3.1 Primary Unique Identifier

The unique identifier for each person is:

text
mobile_number

If multiple submissions use the same mobile number, they must be treated as the same lead/person.

### 3.2 Name Handling

If the same mobile number is submitted with different names, all names must be preserved.

Example:

| mobile | submitted names |
| --- | --- |
| 09123456789 | Ali, Ali Reza, Test, aaa |

The system may have one "primary_name", but all submitted names must be stored in a separate history table or JSON field.

### 3.3 Device/Browser Identity

Because the same person can visit from different devices:

- Same browser/device should be remembered using localStorage and cookie.
- Different browser/device should still be merged by mobile number once the form is submitted.

### 3.4 Returning User Behavior

If a user submits name and mobile once, then refreshes the page or returns later from the same browser:

- The plugin should detect the existing visitor from browser storage.
- It should not show the form again.
- It should show the video directly.
- All new sessions/events should be attached to the same lead/mobile.

### 3.5 Cross-device Behavior

If a user first visits with mobile phone and later visits with laptop:

- The laptop will not know the previous browser localStorage.
- The user will submit the form again.
- If the mobile number is the same, the system must merge the laptop session into the existing lead.
- All stats should count this as one person.

### 3.6 Fake Number Concern

No strict concern about fake numbers in MVP. The system can trust the submitted mobile number.

Optional future phase: OTP verification.

---

## 4. Lead Form Requirements

### 4.1 Fields

The lead form should include:

- Full name
- Mobile number

### 4.2 Validation

Mobile number should be normalized and validated.

For Iranian mobile numbers:

Accepted formats may include:

text
09123456789
9123456789
+989123456789
00989123456789

All should be normalized to:

text
989123456789

or another consistent internal format.

The plugin settings should define the normalization strategy.

Recommended internal format:

text
98xxxxxxxxxx

### 4.3 Form Display Rules

If no known visitor/lead exists in browser storage:

- Show form.
- Hide video.

If known visitor/lead exists:

- Hide form.
- Show video.

If browser storage exists but lead is not found in database:

- Clear invalid browser storage.
- Show form again.

---

## 5. Optional OTP Requirements

OTP is optional and may be implemented after MVP.

### 5.1 OTP Purpose

Verify that the submitted mobile number belongs to the user.

### 5.2 OTP Flow

1. User enters name and mobile.
2. System validates and normalizes mobile.
3. System sends OTP via SMS provider.
4. User enters OTP.
5. System verifies OTP.
6. If correct:
   - Create or update lead.
   - Save visitor identity in browser storage.
   - Show video.
7. If incorrect:
   - Show error.
   - Allow limited retry.

### 5.3 OTP Settings

Admin should be able to configure:

- Enable/disable OTP
- SMS provider
- API key
- SMS sender line
- Message template
- OTP length
- OTP expiry time
- Resend cooldown
- Max attempts
- Daily limit per mobile/IP

### 5.4 OTP Security

The plugin should store only hashed OTP codes, not plain OTP.

OTP attempts should be rate-limited by:

- Mobile number
- IP address
- Visitor UUID

---

## 6. Page Visit Tracking Requirements

The plugin must track visits to the video page.

For each page visit/session:

- visitor_uuid
- session_uuid
- lead_id if available
- page URL
- referrer
- UTM parameters
- user agent
- IP address hash or optional raw IP depending on privacy setting
- device/browser metadata if possible
- first_seen_at
- last_seen_at
- page open time
- page close/hidden events if possible

### 6.1 Report Needed

Admin should see:

- Total visitors
- Total leads
- Total sessions
- Total page views
- Returning visitors
- New visitors
- Visitors who submitted the form
- Visitors who did not submit the form
- Leads who watched video
- Leads who did not watch video after submitting form

Important:

There is no imported audience list. Therefore, "not came" report only means:

- people who started the process but did not complete form
- leads who submitted form but did not start video
- known leads who have no watch events

The system cannot know people from social media who never clicked the link.

---

## 7. HTML5 Video Tracking Requirements

The video player is native HTML5 video.

The plugin must track:

- video_loaded
- video_play
- video_pause
- video_seek
- video_ended
- video_progress/heartbeat
- video_error
- page_hidden
- page_visible
- beforeunload/pagehide range commit

### 7.1 Watch Range Tracking

The main requirement is to know exactly which parts of the video each user watched.

The system should track watched ranges using video timeline seconds.

Example:

User watches:

text
0s to 20s
50s to 90s
80s to 100s

Raw ranges should be stored as:

text
[0,20], [50,90], [80,100]

For individual unique watch calculation, ranges should be merged:

text
[0,20], [50,100]

For heatmap cumulative count, overlapping watch should count multiple times.

### 7.2 Playback Speed

If user changes playback speed:

- The system should ignore real-world time speed difference.
- Only video timeline ranges matter.
- If the user watched video seconds 100 to 150 at 2x speed, it still counts as range 100 to 150 watched.

### 7.3 Completion

There is no special completion goal.

Still, reports can show useful derived metrics:

- max_progress_percent
- unique_watch_percent
- reached_end yes/no
- total_watch_seconds
- unique_watch_seconds

But there is no business rule like "95% is completed" unless configured later.

### 7.4 Seek Behavior

If user seeks forward from 10s to 90s:

- The skipped range 10-90 must not count as watched.
- The current active watching range should be committed before seek if it was valid.

If user seeks backward and rewatches a section:

- Raw ranges should preserve rewatch.
- Heatmap should count that section again.
- Unique watch should not double-count that section for individual summary.

### 7.5 Minimum Valid Range

Recommended default:

text
minimum_valid_range_seconds = 1

Ranges shorter than this may be ignored or stored based on settings.

---

## 8. Heatmap Requirements

### 8.1 Storage

The plugin should store:

- raw watch ranges
- per-user summary
- aggregate heatmap

### 8.2 Heatmap Granularity

Requirement:

- Store raw ranges.
- Aggregate heatmap per second.
- Display heatmap with configurable bucket size.

Examples of display bucket sizes:

- 1 second
- 5 seconds
- 10 seconds
- 30 seconds

### 8.3 Heatmap Metrics

For each video second or bucket:

- total_views_count
- unique_leads_count if possible
- rewatch_count
- drop_off indicators
- percentage of leads who watched that second

---

## 9. Admin Dashboard Requirements

Only logged-in WordPress admins should access reports.

Minimum capability:

text
manage_options

Dashboard pages:

1. Overview
2. Leads
3. Sessions/Page Visits
4. Video Analytics
5. Lead Detail
6. Heatmap
7. Export
8. Settings
9. Logs/Debug

### 9.1 Overview Metrics

Show:

- Total leads
- Total unique visitors
- Total sessions
- Total page views
- Total video starters
- Total users who reached video end
- Average unique watch percent
- Average total watch time
- Top referrers
- UTM breakdown

### 9.2 Leads Table

Columns:

- lead_id
- primary name
- all submitted names
- normalized mobile
- first seen
- last seen
- sessions count
- page views count
- video started yes/no
- reached end yes/no
- unique watch percent
- total watch seconds
- unique watch seconds
- last activity

Filters:

- date range
- video started yes/no
- reached end yes/no
- min watch percent
- referrer/UTM
- search by name/mobile

### 9.3 Lead Detail Page

Show:

- lead profile
- all submitted names
- all associated visitors/devices
- sessions
- page visits
- raw video events
- watched raw ranges
- merged unique ranges
- per-video summary
- timeline visualization

### 9.4 Heatmap Page

Show visual chart for video timeline.

Features:

- bucket size selector
- total views per second/bucket
- unique viewers per second/bucket
- rewatch intensity
- export heatmap data
- highlight most watched parts
- highlight drop-off areas

---

## 10. Export Requirements

Priority:

1. Admin dashboard reports first.
2. Export second.

Preferred export format:

text
XLSX

If XLSX is complex initially, CSV can be implemented first, but final plugin should support XLSX.

Export files:

- Leads report
- Lead summaries
- Page visits
- Sessions
- Raw video events
- Watched ranges
- Heatmap
- Full per-lead analytics

XLSX should support Persian/UTF-8 text.

For CSV fallback, use UTF-8 with BOM.

---

## 11. Data Retention

All data should be stored permanently.

No automatic deletion by default.

Admin may later add manual cleanup tools, but MVP should not delete data.

---

## 12. Privacy and Security

Even though this is an internal marketing tool, implement basic security:

- Sanitize all inputs.
- Validate mobile format.
- Use WordPress nonces for admin actions.
- Use REST nonce or signed visitor token for frontend API.
- Restrict reports to admins.
- Escape all output in admin pages.
- Use prepared SQL queries.
- Avoid storing plain OTP if OTP is enabled.
- Optional setting for storing raw IP or hashed IP.

---

## 13. Browser Storage Requirements

The plugin should use both:

- localStorage
- cookie

Stored client-side data:

json
{
  "visitor_uuid": "...",
  "lead_id": 123,
  "mobile_hash": "...",
  "identity_token": "...",
  "created_at": "...",
  "last_seen_at": "..."
}

Do not store raw mobile number in localStorage if avoidable.

Use an identity token generated by backend.

### 13.1 Browser Storage Recovery

If localStorage exists but cookie does not:

- restore cookie from localStorage if token is valid.

If cookie exists but localStorage does not:

- restore localStorage from cookie/session API if token is valid.

If token is invalid:

- clear browser identity
- show form

---

## 14. Database Requirements

The plugin should create custom WordPress database tables.

Recommended table prefix:

text
wp_vlt_

where `vlt` means Video Lead Tracking.

Actual table names must use `$wpdb->prefix`.

---

## 15. Recommended Database Tables

### 15.1 `wp_vlt_leads`

Stores unique leads by normalized mobile.

Fields:

- id BIGINT UNSIGNED PK AUTO_INCREMENT
- primary_name VARCHAR(190) NULL
- normalized_mobile VARCHAR(32) NOT NULL UNIQUE
- mobile_hash CHAR(64) NOT NULL
- is_verified TINYINT(1) DEFAULT 0
- first_seen_at DATETIME NOT NULL
- last_seen_at DATETIME NULL
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL

Indexes:

- UNIQUE(normalized_mobile)
- INDEX(mobile_hash)
- INDEX(first_seen_at)
- INDEX(last_seen_at)

---

### 15.2 `wp_vlt_lead_names`

Stores all submitted names for a mobile number.

Fields:

- id BIGINT UNSIGNED PK AUTO_INCREMENT
- lead_id BIGINT UNSIGNED NOT NULL
- submitted_name VARCHAR(190) NOT NULL
- normalized_mobile VARCHAR(32) NOT NULL
- visitor_uuid CHAR(36) NULL
- session_uuid CHAR(36) NULL
- ip_hash CHAR(64) NULL
- user_agent_hash CHAR(64) NULL
- submitted_at DATETIME NOT NULL

Indexes:

- INDEX(lead_id)
- INDEX(normalized_mobile)
- INDEX(submitted_at)

---

### 15.3 `wp_vlt_visitors`

Stores browser/device-level anonymous identity.

Fields:

- id BIGINT UNSIGNED PK AUTO_INCREMENT
- visitor_uuid CHAR(36) NOT NULL UNIQUE
- lead_id BIGINT UNSIGNED NULL
- identity_token_hash CHAR(64) NULL
- first_seen_at DATETIME NOT NULL
- last_seen_at DATETIME NULL
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL

Indexes:

- UNIQUE(visitor_uuid)
- INDEX(lead_id)
- INDEX(last_seen_at)

---

### 15.4 `wp_vlt_sessions`

Stores visit sessions.

Fields:

- id BIGINT UNSIGNED PK AUTO_INCREMENT
- session_uuid CHAR(36) NOT NULL UNIQUE
- visitor_uuid CHAR(36) NOT NULL
- lead_id BIGINT UNSIGNED NULL
- started_at DATETIME NOT NULL
- ended_at DATETIME NULL
- last_activity_at DATETIME NULL
- landing_url TEXT NULL
- referrer TEXT NULL
- utm_source VARCHAR(190) NULL
- utm_medium VARCHAR(190) NULL
- utm_campaign VARCHAR(190) NULL
- utm_content VARCHAR(190) NULL
- utm_term VARCHAR(190) NULL
- ip_hash CHAR(64) NULL
- user_agent TEXT NULL
- device_type VARCHAR(50) NULL
- browser VARCHAR(100) NULL
- os VARCHAR(100) NULL
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL

Indexes:

- UNIQUE(session_uuid)
- INDEX(visitor_uuid)
- INDEX(lead_id)
- INDEX(started_at)
- INDEX(last_activity_at)
- INDEX(utm_source)
- INDEX(utm_campaign)

---

### 15.5 `wp_vlt_page_visits`

Stores page view events.

Fields:

- id BIGINT UNSIGNED PK AUTO_INCREMENT
- session_uuid CHAR(36) NOT NULL
- visitor_uuid CHAR(36) NOT NULL
- lead_id BIGINT UNSIGNED NULL
- page_id BIGINT UNSIGNED NULL
- page_url TEXT NOT NULL
- event_type VARCHAR(50) NOT NULL
- event_at DATETIME NOT NULL
- time_on_page_seconds INT UNSIGNED NULL
- metadata LONGTEXT NULL

Event types:

- page_view
- page_visible
- page_hidden
- page_unload
- heartbeat

Indexes:

- INDEX(session_uuid)
- INDEX(visitor_uuid)
- INDEX(lead_id)
- INDEX(event_type)
- INDEX(event_at)

---

### 15.6 `wp_vlt_videos`

Stores videos configured for tracking.

Fields:

- id BIGINT UNSIGNED PK AUTO_INCREMENT
- video_key VARCHAR(190) NOT NULL UNIQUE
- title VARCHAR(255) NULL
- page_id BIGINT UNSIGNED NULL
- video_url TEXT NULL
- duration_seconds INT UNSIGNED NULL
- is_active TINYINT(1) DEFAULT 1
- created_at DATETIME NOT NULL
- updated_at DATETIME NOT NULL

Indexes:

- UNIQUE(video_key)
- INDEX(page_id)
- INDEX(is_active)

---

### 15.7 `wp_vlt_video_events`

Stores raw video events.

Fields:

- id BIGINT UNSIGNED PK AUTO_INCREMENT
- video_id BIGINT UNSIGNED NOT NULL
- session_uuid CHAR(36) NOT NULL
- visitor_uuid CHAR(36) NOT NULL
- lead_id BIGINT UNSIGNED NULL
- event_type VARCHAR(50) NOT NULL
- video_time_seconds DECIMAL(10,3) NULL
- from_second DECIMAL(10,3) NULL
- to_second DECIMAL(10,3) NULL
- playback_rate DECIMAL(5,2) NULL
- duration_seconds DECIMAL(10,3) NULL
- event_at DATETIME NOT NULL
- metadata LONGTEXT NULL

Event types:

- video_loaded
- play
- pause
- seek_start
- seek_end
- range_commit
- heartbeat
- ended
- error
- rate_change

Indexes:

- INDEX(video_id)
- INDEX(session_uuid)
- INDEX(visitor_uuid)
- INDEX(lead_id)
- INDEX(event_type)
- INDEX(event_at)
- INDEX(video_time_seconds)

---

### 15.8 `wp_vlt_video_ranges`

Stores committed watched ranges.

Fields:

- id BIGINT UNSIGNED PK AUTO_INCREMENT
- video_id BIGINT UNSIGNED NOT NULL
- session_uuid CHAR(36) NOT NULL
- visitor_uuid CHAR(36) NOT NULL
- lead_id BIGINT UNSIGNED NULL
- from_second DECIMAL(10,3) NOT NULL
- to_second DECIMAL(10,3) NOT NULL
- duration_seconds DECIMAL(10,3) NOT NULL
- playback_rate DECIMAL(5,2) NULL
- committed_reason VARCHAR(50) NULL
- event_at DATETIME NOT NULL
- created_at DATETIME NOT NULL

Committed reasons:

- pause
- seek
- ended
- hidden
- unload
- heartbeat
- manual

Indexes:

- INDEX(video_id)
- INDEX(session_uuid)
- INDEX(visitor_uuid)
- INDEX(lead_id)
- INDEX(from_second)
- INDEX(to_second)
- INDEX(event_at)

---

### 15.9 `wp_vlt_video_user_summary`

Stores aggregated per-lead/per-video summary.

Fields:

- id BIGINT UNSIGNED PK AUTO_INCREMENT
- video_id BIGINT UNSIGNED NOT NULL
- lead_id BIGINT UNSIGNED NULL
- visitor_uuid CHAR(36) NULL
- sessions_count INT UNSIGNED DEFAULT 0
- started TINYINT(1) DEFAULT 0
- reached_end TINYINT(1) DEFAULT 0
- first_play_at DATETIME NULL
- last_activity_at DATETIME NULL
- total_watch_seconds DECIMAL(12,3) DEFAULT 0
- unique_watch_seconds DECIMAL(12,3) DEFAULT 0
- max_video_time_seconds DECIMAL(10,3) DEFAULT 0
- unique_watch_percent DECIMAL(6,2) DEFAULT 0
- raw_ranges_json LONGTEXT NULL
- merged_ranges_json LONGTEXT NULL
- updated_at DATETIME NOT NULL

Uniqueness:

- UNIQUE(video_id, lead_id) when lead_id is not null
- For anonymous visitors, handle by visitor_uuid

Indexes:

- INDEX(video_id)
- INDEX(lead_id)
- INDEX(visitor_uuid)
- INDEX(started)
- INDEX(reached_end)
- INDEX(unique_watch_percent)
- INDEX(last_activity_at)

Note:
MySQL partial unique indexes are not straightforward. Implement uniqueness carefully in application logic.

---

### 15.10 `wp_vlt_video_heatmap`

Stores aggregate heatmap per video per second.

Fields:

- id BIGINT UNSIGNED PK AUTO_INCREMENT
- video_id BIGINT UNSIGNED NOT NULL
- second_index INT UNSIGNED NOT NULL
- total_views_count BIGINT UNSIGNED DEFAULT 0
- unique_leads_count BIGINT UNSIGNED DEFAULT 0
- unique_visitors_count BIGINT UNSIGNED DEFAULT 0
- updated_at DATETIME NOT NULL

Uniqueness:

- UNIQUE(video_id, second_index)

Indexes:

- INDEX(video_id)
- INDEX(second_index)

---

### 15.11 `wp_vlt_otp_codes`

Optional table for OTP.

Fields:

- id BIGINT UNSIGNED PK AUTO_INCREMENT
- normalized_mobile VARCHAR(32) NOT NULL
- otp_hash CHAR(64) NOT NULL
- visitor_uuid CHAR(36) NULL
- session_uuid CHAR(36) NULL
- expires_at DATETIME NOT NULL
- verified_at DATETIME NULL
- attempts_count INT UNSIGNED DEFAULT 0
- ip_hash CHAR(64) NULL
- created_at DATETIME NOT NULL

Indexes:

- INDEX(normalized_mobile)
- INDEX(visitor_uuid)
- INDEX(expires_at)
- INDEX(created_at)

---

### 15.12 `wp_vlt_logs`

Stores debug/system logs.

Fields:

- id BIGINT UNSIGNED PK AUTO_INCREMENT
- level VARCHAR(20) NOT NULL
- context VARCHAR(100) NULL
- message TEXT NOT NULL
- metadata LONGTEXT NULL
- created_at DATETIME NOT NULL

Indexes:

- INDEX(level)
- INDEX(context)
- INDEX(created_at)

---

## 16. Frontend REST API Requirements

The plugin should expose REST API endpoints.

Namespace example:

text
/vlt/v1

Endpoints:

### 16.1 Init Session

http
POST /wp-json/vlt/v1/session/init

Purpose:

- create or restore visitor
- create new session
- return tracking tokens
- check if lead already known

Request:

json
{
  "visitor_uuid": "optional",
  "identity_token": "optional",
  "page_url": "...",
  "referrer": "...",
  "utm": {}
}

Response:

json
{
  "success": true,
  "visitor_uuid": "...",
  "session_uuid": "...",
  "known_lead": true,
  "lead_id": 123,
  "identity_token": "...",
  "show_form": false
}

---

### 16.2 Submit Lead

http
POST /wp-json/vlt/v1/lead/submit

Request:

json
{
  "visitor_uuid": "...",
  "session_uuid": "...",
  "name": "Ali",
  "mobile": "09123456789"
}

Response:

json
{
  "success": true,
  "lead_id": 123,
  "normalized_mobile": "989123456789",
  "identity_token": "...",
  "show_video": true
}

Behavior:

- Normalize mobile.
- Find or create lead by normalized mobile.
- Store submitted name.
- Attach current visitor/session to lead.
- Attach previous anonymous events from same visitor/session to lead.
- Return identity token.
- Save identity in browser storage.

---

### 16.3 Send OTP Optional

http
POST /wp-json/vlt/v1/otp/send

Optional.

---

### 16.4 Verify OTP Optional

http
POST /wp-json/vlt/v1/otp/verify

Optional.

---

### 16.5 Track Page Event

http
POST /wp-json/vlt/v1/track/page

Request:

json
{
  "visitor_uuid": "...",
  "session_uuid": "...",
  "identity_token": "...",
  "event_type": "page_view",
  "page_url": "...",
  "time_on_page_seconds": 12,
  "metadata": {}
}

---

### 16.6 Track Video Event

http
POST /wp-json/vlt/v1/track/video-event

Request:

json
{
  "visitor_uuid": "...",
  "session_uuid": "...",
  "identity_token": "...",
  "video_key": "main-training-video",
  "event_type": "play",
  "video_time_seconds": 12.442,
  "from_second": null,
  "to_second": null,
  "playback_rate": 1,
  "duration_seconds": 1500,
  "metadata": {}
}

---

### 16.7 Commit Video Range

http
POST /wp-json/vlt/v1/track/video-range

Request:

json
{
  "visitor_uuid": "...",
  "session_uuid": "...",
  "identity_token": "...",
  "video_key": "main-training-video",
  "from_second": 10.2,
  "to_second": 32.8,
  "playback_rate": 1.5,
  "committed_reason": "pause"
}

Behavior:

- Validate token/session.
- Resolve lead_id if available.
- Store raw event.
- Store range if valid.
- Update summary.
- Update heatmap or queue heatmap aggregation.

---

## 17. Aggregation Logic Requirements

### 17.1 Per-user Summary

For each lead and video:

- gather all ranges for that lead/video
- calculate total_watch_seconds = sum of all raw range durations
- calculate merged_ranges
- calculate unique_watch_seconds = sum of merged ranges
- calculate unique_watch_percent = unique_watch_seconds / video duration * 100
- calculate max_video_time_seconds = max(to_second)
- started = true if at least one play event or valid range exists
- reached_end = true if ended event exists or max_video_time close to duration

### 17.2 Anonymous Summary

If a user has not submitted form yet:

- store by visitor_uuid.
- when lead submits form, migrate/attach anonymous summary to lead.

### 17.3 Merge by Mobile

When same mobile is submitted from another device:

- find existing lead
- attach new visitor/session to existing lead
- recompute or update summary for that lead
- preserve all names

---

## 18. Heatmap Aggregation Logic

For each raw committed range:

Example:

text
from_second = 10.2
to_second = 15.7

Affected integer seconds:

text
10, 11, 12, 13, 14, 15

For each second:

- increment total_views_count by 1

For unique_leads_count:

- avoid double counting same lead for the same second.
- This may require either:
  - recomputing unique counts from ranges periodically, or
  - maintaining a separate table for lead-second uniqueness.

Recommended approach for accuracy:

Add optional table:

### `wp_vlt_video_heatmap_uniques`

Fields:

- id BIGINT UNSIGNED PK AUTO_INCREMENT
- video_id BIGINT UNSIGNED NOT NULL
- second_index INT UNSIGNED NOT NULL
- lead_id BIGINT UNSIGNED NULL
- visitor_uuid CHAR(36) NULL
- created_at DATETIME NOT NULL

Uniqueness:

- UNIQUE(video_id, second_index, lead_id) for leads
- Application logic for anonymous visitor uniqueness

This table can be large. If performance is a concern, implement unique counts in scheduled aggregation instead.

For MVP:

- Store raw ranges accurately.
- Compute heatmap from raw ranges on admin page or via cron.
- Cache results in `wp_vlt_video_heatmap`.

---

## 19. WordPress Admin Settings

Settings page should include:

### 19.1 General

- Enable tracking
- Select tracking page/post
- Video key
- Video title
- Video duration
- Video CSS selector
- Delete data on uninstall yes/no

### 19.2 Form

- Form title
- Name field label
- Mobile field label
- Submit button text
- Success message
- Mobile normalization mode

### 19.3 Tracking

- Minimum valid range seconds
- Heartbeat interval
- Heatmap bucket display default
- Track anonymous before form yes/no
- Store raw user agent yes/no
- Store IP as hash/raw/disabled

### 19.4 OTP Optional

- Enable OTP
- SMS provider
- API key
- Sender number
- OTP template
- OTP expiry
- Resend cooldown
- Max attempts

### 19.5 Export

- Enable XLSX
- CSV fallback
- Export date range default

---

## 20. Shortcodes / Blocks

The plugin should provide a shortcode:

text
[vlt_video_lead_gate video_key="main-training-video"]

This shortcode renders:

- lead form if unknown user
- video player if known user
- tracking JS

Alternative:

text
[vlt_video src="https://example.com/video.mp4" video_key="main-training-video"]

The plugin may also allow selecting an existing HTML5 video by CSS selector.

MVP recommended:

Use shortcode that outputs both form and HTML5 video.

---

## 21. Acceptance Criteria

The plugin is considered working when:

1. Admin can install and activate plugin.
2. Plugin creates required database tables.
3. Admin can place shortcode on a page.
4. Unknown visitor sees name/mobile form.
5. After submit, lead is created.
6. Video is shown after submit.
7. Refreshing page does not show form again on same browser.
8. Returning later on same browser does not show form again.
9. Same mobile from another device maps to same lead.
10. Different submitted names for same mobile are preserved.
11. Video play/pause/seek/end events are stored.
12. Watched ranges are stored.
13. Individual lead summary is calculated.
14. Heatmap can be viewed.
15. Admin can view reports inside WordPress.
16. Only admins can access reports.
17. Data is not automatically deleted.
18. Export is available at least in CSV, preferably XLSX.
19. Tracking works with HTML5 video.
20. No external analytics service is required.