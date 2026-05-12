# WordPress Video Lead Tracking Plugin - 20 Phase Implementation Plan

## Goal

Build a custom WordPress plugin that gates an HTML5 educational video behind a simple lead form, identifies users by mobile number, tracks detailed video watch ranges, merges multi-device activity by mobile number, generates per-lead reports, and displays aggregate video heatmap analytics inside WordPress admin.

---

# Phase 1 - Final Architecture and Plugin Skeleton

## Objective

Create the base plugin structure and define internal architecture.

## Tasks

1. Create plugin folder:

video-lead-tracker/

2. Create main plugin file:

text
video-lead-tracker.php

3. Add plugin header.
4. Define constants:

php
VLT_VERSION
VLT_PLUGIN_FILE
VLT_PLUGIN_DIR
VLT_PLUGIN_URL
VLT_DB_VERSION

5. Create directory structure:

text
/includes
/includes/Admin
/includes/API
/includes/Core
/includes/Database
/includes/Frontend
/includes/Services
/includes/Reports
/includes/Export
/assets/js
/assets/css
/templates

6. Implement autoloading or manual class loading.
7. Register activation hook.
8. Register deactivation hook.
9. Register uninstall behavior based on settings.

## Required Classes

Suggested classes:

php
VLT_Plugin
VLT_Activator
VLT_Deactivator
VLT_DB
VLT_Settings
VLT_REST_Controller
VLT_Frontend
VLT_Admin
VLT_Tracker
VLT_Aggregator
VLT_Exporter
VLT_OTP_Service

## Deliverable

Plugin activates successfully without fatal errors.

---

# Phase 2 - Database Schema and Migrations

## Objective

Create all required custom database tables.

## Tasks

1. Implement database migration system.
2. Use `dbDelta()` for table creation.
3. Store database version in WordPress option:

text
vlt_db_version

4. Create tables:

text
wp_vlt_leads
wp_vlt_lead_names
wp_vlt_visitors
wp_vlt_sessions
wp_vlt_page_visits
wp_vlt_videos
wp_vlt_video_events
wp_vlt_video_ranges
wp_vlt_video_user_summary
wp_vlt_video_heatmap
wp_vlt_logs

5. Optional later:

text
wp_vlt_otp_codes
wp_vlt_video_heatmap_uniques

## Important Implementation Rules

- Use `$wpdb->prefix`.
- Use prepared queries.
- Use `utf8mb4`.
- Do not delete tables on plugin deactivation.
- Delete data only on uninstall if setting explicitly allows it.

## Deliverable

On activation, tables are created correctly.

---

# Phase 3 - Settings System

## Objective

Create plugin settings in WordPress admin.

## Tasks

1. Add admin menu:

text
Video Lead Tracker

2. Add submenu pages:

text
Overview
Leads
Video Analytics
Heatmap
Exports
Settings
Logs

3. Implement Settings API or custom settings save.
4. Add settings sections:

### General

- Enable tracking
- Tracking page ID
- Video key
- Video title
- Video URL
- Video duration
- Delete data on uninstall

### Form

- Form title
- Name label
- Mobile label
- Button text
- Success message

### Tracking

- Minimum valid range seconds
- Heartbeat interval
- Heatmap default bucket size
- Track anonymous events before form
- Store IP mode: disabled/hash/raw
- Store user agent yes/no

### OTP

- Enable OTP
- Provider
- API key
- Sender
- Template
- Expiry
- Resend cooldown
- Max attempts

### Export

- Enable XLSX
- Enable CSV fallback

## Deliverable

Admin can configure plugin settings.

---

# Phase 4 - Shortcode and Frontend Rendering

## Objective

Render lead form and HTML5 video through shortcode.

## Shortcode

text
[vlt_video_lead_gate video_key="main-training-video" src="https://example.com/video.mp4"]

## Tasks

1. Register shortcode.
2. Render wrapper div:

html
<div class="vlt-wrapper" data-video-key="main-training-video"></div>

3. Render lead form:

html
<form class="vlt-lead-form">
  <input type="text" name="name">
  <input type="tel" name="mobile">
  <button type="submit">...</button>
</form>

4. Render HTML5 video hidden by default:

html
<video class="vlt-video" controls preload="metadata">
  <source src="..." type="video/mp4">
</video>

5. Enqueue frontend JS and CSS only when shortcode is present.
6. Pass REST API base URL and nonce to JS via `wp_localize_script`.

## Deliverable

A page with shortcode displays form and hidden video.

---

# Phase 5 - Browser Identity Layer

## Objective

Create persistent browser/device identity using localStorage and cookie.

## Tasks

1. In JS, check localStorage for:

json
{
  "visitor_uuid": "...",
  "lead_id": 123,
  "identity_token": "...",
  "mobile_hash": "...",
  "created_at": "...",
  "last_seen_at": "..."
}

2. Also check cookie backup.
3. If missing, request session init from backend.
4. Store returned visitor_uuid and identity_token.
5. If known lead is returned, hide form and show video.
6. If unknown, show form.

## Rules

- Do not store raw mobile in browser storage.
- Store token and mobile hash only.
- If backend says token invalid, clear localStorage/cookie and show form.
- Refreshing page should not show form again after successful lead submission.

## Deliverable

Returning user on same browser is recognized.

---

# Phase 6 - REST API Base

## Objective

Create REST API namespace and shared authentication/validation helpers.

## Namespace

text
/vlt/v1

## Tasks

1. Register REST routes.
2. Implement request validation.
3. Implement nonce/token validation.
4. Implement JSON response helpers.
5. Implement error response helpers.
6. Implement rate limiting basics for form submission and tracking.
7. Implement IP hash utility.
8. Implement user agent hash utility.
9. Implement UUID generator.

## Routes to Create

text
POST /session/init
POST /lead/submit
POST /track/page
POST /track/video-event
POST /track/video-range

Optional later:

text
POST /otp/send
POST /otp/verify

## Deliverable

REST endpoints exist and return structured JSON.

---

# Phase 7 - Session Initialization

## Objective

Create or restore visitor and create new session.

## Endpoint

http
POST /wp-json/vlt/v1/session/init

## Tasks

1. Accept optional visitor_uuid and identity_token.
2. Validate existing visitor if token is valid.
3. Create new visitor if no valid identity exists.
4. Create new session_uuid every page load.
5. Store:

- landing URL
- referrer
- UTM params
- IP hash
- user agent
- device info if implemented

6. Return:

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

## Deliverable

Every page load creates a session and resolves visitor identity.

---

# Phase 8 - Lead Submission and Mobile-based Merge

## Objective

Create or update lead by mobile number and attach visitor/session.

## Endpoint

http
POST /wp-json/vlt/v1/lead/submit

## Tasks

1. Validate name.
2. Validate mobile.
3. Normalize mobile to internal format.
4. Search lead by normalized mobile.
5. If lead exists:
   - update last_seen_at
   - optionally update primary_name if empty
6. If lead does not exist:
   - create new lead
7. Always insert submitted name into `wp_vlt_lead_names`.
8. Attach visitor to lead.
9. Attach session to lead.
10. Update previous anonymous page visits/events/ranges for this visitor/session to lead_id.
11. Generate identity_token.
12. Save token hash to visitor row.
13. Return lead_id and token.
14. Frontend stores identity in localStorage and cookie.

## Important Rule

If same mobile is submitted from different devices, all data must belong to the same lead.

## Deliverable

Mobile number becomes the unique user identity.

---

# Phase 9 - Optional OTP Module

## Objective

Add OTP verification if enabled in settings.

## Recommendation

This phase may be skipped for MVP and implemented later.

## Tasks

1. Create `wp_vlt_otp_codes` table if not created.
2. Implement SMS provider abstraction:

php
interface VLT_SMS_Provider_Interface {
public function send($mobile, $message);
}

3. Implement provider class for selected SMS gateway.
4. Add endpoint:

http
POST /otp/send

5. Generate OTP.
6. Hash OTP before storing.
7. Store expiry time.
8. Limit resend attempts.
9. Add endpoint:

http
POST /otp/verify

10. Verify code.
11. Mark lead as verified.
12. Continue to video after verification.

## Deliverable

If OTP is enabled, lead is created/activated only after OTP verification.

---

# Phase 10 - Page Visit Tracking

## Objective

Track page-level activity.

## Frontend Events

Send:

- page_view
- page_visible
- page_hidden
- page_unload
- heartbeat

## Tasks

1. On session init, send page_view.
2. Use Visibility API for page hidden/visible.
3. Use heartbeat interval.
4. Use `navigator.sendBeacon()` for unload/pagehide if available.
5. Fallback to fetch with `keepalive`.

## Endpoint

http
POST /track/page

## Data

json
{
  "visitor_uuid": "...",
  "session_uuid": "...",
  "event_type": "page_view",
  "page_url": "...",
  "time_on_page_seconds": 0,
  "metadata": {}
}

## Deliverable

Admin can see page visits and sessions.

---

# Phase 11 - HTML5 Video Event Tracking

## Objective

Track raw HTML5 video events.

## Events to Listen

js
loadedmetadata
play
pause
seeking
seeked
ended
ratechange
error
timeupdate

## Tasks

1. Attach listeners to video element.
2. Track current video time.
3. Track playback rate.
4. Send video events to backend.
5. Avoid excessive event spam.
6. Use heartbeat for progress instead of sending every `timeupdate`.

## Endpoint

http
POST /track/video-event

## Deliverable

Raw video events are stored.

---

# Phase 12 - Watched Range Commit Logic

## Objective

Accurately store the ranges of video timeline watched by each user.

## Core Concept

When video is playing:

- Start active range at currentTime.
- Continue while playing normally.
- Commit range when:
  - pause
  - seek starts
  - ended
  - page hidden
  - page unload
  - heartbeat checkpoint

## Frontend State

Maintain:

js
isPlaying
rangeStart
lastVideoTime
lastCommitTime
playbackRate

## Commit Rules

1. If not playing, do not commit.
2. If `to_second <= from_second`, ignore.
3. If range duration < minimum_valid_range_seconds, ignore.
4. On seek, commit current range before seek.
5. On ended, commit current range to duration.
6. On pagehide/unload, commit using sendBeacon.
7. Playback speed does not affect the video timeline range.

## Endpoint

http
POST /track/video-range

## Deliverable

Watched ranges are stored accurately in `wp_vlt_video_ranges`.

---

# Phase 13 - Video Summary Aggregation

## Objective

Calculate per-lead/per-video summary.

## Tasks

1. After each range insert, update summary.
2. Implement helper to fetch all ranges for a lead/video.
3. Merge overlapping ranges.
4. Calculate:

text
total_watch_seconds
unique_watch_seconds
unique_watch_percent
max_video_time_seconds
started
reached_end
sessions_count
first_play_at
last_activity_at
raw_ranges_json
merged_ranges_json

5. For anonymous visitor, summary can be by visitor_uuid.
6. Once lead submits form, migrate anonymous summary to lead summary.

## Range Merge Example

Input:

json
[[0,20],[50,90],[80,100]]

Merged:

json
[[0,20],[50,100]]

## Deliverable

Lead-level video reports show accurate watch statistics.

---

# Phase 14 - Heatmap Aggregation

## Objective

Generate aggregate per-second video heatmap.

## Storage Strategy

- Store raw ranges permanently.
- Build per-second heatmap from ranges.
- Cache aggregate in `wp_vlt_video_heatmap`.

## Tasks

1. For each committed range, determine affected seconds.
2. Increment total_views_count for each second.
3. Implement scheduled full rebuild command/action for accuracy.
4. Implement admin button:

text
Rebuild Heatmap

5. Display heatmap in configurable buckets:

- 1s
- 5s
- 10s
- 30s

## Accuracy Note

For `total_views_count`, raw range incremental update is acceptable.

For `unique_leads_count`, safest method is periodic/full rebuild from raw ranges.

## Deliverable

Admin can view aggregate heatmap.

---

# Phase 15 - Admin Overview Dashboard

## Objective

Create high-level reporting dashboard.

## Metrics

Show cards for:

- Total leads
- Total visitors
- Total sessions
- Total page views
- Total video starters
- Total reached end
- Average unique watch percent
- Average total watch seconds
- Average unique watch seconds

Show charts/tables:

- Leads over time
- Sessions over time
- Referrer breakdown
- UTM source/campaign breakdown
- Video start rate
- Watch distribution

## Access

Only users with:

php
current_user_can('manage_options')

## Deliverable

Admin can see main analytics overview.

---

# Phase 16 - Leads Report and Lead Detail

## Objective

Create detailed lead reporting.

## Leads Table Columns

- lead_id
- primary_name
- all_names
- normalized_mobile
- first_seen_at
- last_seen_at
- sessions_count
- page_views_count
- video_started
- reached_end
- total_watch_seconds
- unique_watch_seconds
- unique_watch_percent
- max_video_time_seconds

## Filters

- date range
- video started yes/no
- reached end yes/no
- minimum watch percent
- search by name/mobile
- UTM source
- UTM campaign

## Lead Detail Page

Show:

- lead profile
- all submitted names
- associated visitor_uuid values
- sessions
- page visits
- video events
- raw ranges
- merged ranges
- summary
- timeline visualization

## Deliverable

Admin can inspect each lead individually.

---

# Phase 17 - Video Analytics and Heatmap UI

## Objective

Create video-specific analytics pages.

## Video Analytics Page

Show:

- video title
- duration
- total starts
- total watch seconds
- average unique watch percent
- reached end count
- most watched ranges
- drop-off areas

## Heatmap UI

Implement visual heatmap:

- horizontal timeline
- color intensity based on views
- tooltip per bucket
- bucket selector
- table below chart

Tooltip should show:

- second/bucket
- total views
- unique leads if available
- percentage of leads
- rewatch intensity

## Deliverable

Admin can visually understand video engagement.

---

# Phase 18 - Export System

## Objective

Provide export capability.

## Priority

Admin dashboard is higher priority than export. But export should be added after reporting works.

## Formats

Preferred:

text
XLSX

Fallback:

text
CSV with UTF-8 BOM

## Export Types

1. Leads
2. Lead summaries
3. Sessions
4. Page visits
5. Video events
6. Video ranges
7. Heatmap
8. Full analytics export

## XLSX Implementation Options

Use a lightweight PHP XLSX library if acceptable.

If no dependency is desired:

- implement CSV first
- add XLSX later

## Deliverable

Admin can download reports.

---

# Phase 19 - QA, Edge Cases, and Reliability

## Objective

Test all important real-world scenarios.

## Test Cases

### Identity

1. New visitor sees form.
2. Submit form creates lead.
3. Refresh page does not show form.
4. Close browser and return does not show form.
5. Clear localStorage shows form again.
6. Same mobile from another device merges into same lead.
7. Same mobile with different names stores all names.
8. Invalid token clears identity.

### Video

1. Play from 0 to 10 stores range 0-10.
2. Play then pause stores range.
3. Play then seek forward does not count skipped section.
4. Rewatch same section counts again in raw ranges.
5. Rewatch does not double-count unique watch seconds.
6. Page hidden commits active range.
7. Page unload commits active range.
8. Ended commits final range.
9. Playback rate 2x still stores video timeline range.
10. Very short accidental play is ignored if below minimum range.

### Reporting

1. Lead summary is correct.
2. Heatmap counts overlapping rewatches.
3. Admin filters work.
4. Export works with Persian names.
5. Non-admin cannot access reports.

## Deliverable

Plugin is stable enough for production use.

---

# Phase 20 - Optimization, Hardening, and Release

## Objective

Prepare plugin for real usage.

## Tasks

1. Add indexes if slow queries are found.
2. Add pagination to admin tables.
3. Add background aggregation for heavy heatmap calculations.
4. Add admin notices for missing settings.
5. Add debug logging toggle.
6. Add plugin versioning.
7. Add database migration safety.
8. Add cache for expensive dashboard queries.
9. Add manual rebuild tools:
   - rebuild summaries
   - rebuild heatmap
   - reattach orphan sessions
10. Add documentation page inside plugin.

## Deliverable

Production-ready first version.

---

# Recommended MVP Scope

To reduce complexity, build the MVP in this order:

## MVP Must-have

- Plugin skeleton
- Database tables
- Settings
- Shortcode
- Form name/mobile
- Browser identity with localStorage/cookie
- Lead creation/merge by mobile
- Session tracking
- HTML5 video tracking
- Range tracking
- Lead summary
- Basic admin reports
- Basic heatmap
- CSV export

## After MVP

- XLSX export
- OTP
- Advanced charts
- Advanced heatmap unique-lead counting
- More SMS providers
- Advanced privacy settings

---

# Notes for Claude Code

Please implement this as a clean WordPress plugin.

Important principles:

1. Use WordPress coding/security best practices.
2. Sanitize all input.
3. Escape all output.
4. Use prepared SQL queries.
5. Do not rely on Google Analytics or external analytics services.
6. Store raw ranges permanently.
7. Use mobile number as the unique lead identity.
8. Preserve all submitted names for same mobile.
9. Use localStorage/cookie to avoid asking form again on same browser.
10. Merge activity across devices by mobile number.
11. Admin reports should be accessible only to WordPress admins.
12. Prioritize working admin dashboard before XLSX export.
13. OTP should be modular and optional.


---

# نکته مهم درباره «گزارش جامعه»

با شرایطی که گفتی، چون لینک عمومی در شبکه‌های اجتماعی منتشر می‌شود و هیچ لیست اولیه‌ای از مخاطبان نداری، سیستم فقط این جامعه‌ها را می‌تواند گزارش کند:

1. کسانی که وارد صفحه شده‌اند.
2. کسانی که فرم را پر کرده‌اند و لید شده‌اند.
3. کسانی که فرم را پر کرده‌اند ولی ویدیو را شروع نکرده‌اند.
4. کسانی که ویدیو را شروع کرده‌اند.
5. کسانی که بخش‌های مختلف ویدیو را دیده‌اند.
6. کسانی که برگشته‌اند و دوباره دیده‌اند.
7. کسانی که با یک شماره از چند دستگاه آمده‌اند.

اما سیستم نمی‌تواند بفهمد:

> چه کسانی از کل فالوئرهای اینستاگرام/تلگرام لینک را دیده‌اند ولی اصلاً کلیک نکرده‌اند.

چون هیچ لیست مرجعی از آن‌ها در سیستم نیست.

پس در داکیومنت هم نوشتم که مفهوم «نیامده‌ها» در این پروژه یعنی:

- کاربرانی که صفحه را باز کردند ولی فرم را کامل نکردند.
- لیدهایی که فرم را پر کردند ولی ویدیو را شروع نکردند.
- لیدهایی که قبلاً آمده‌اند ولی در بازه گزارش جدید فعالیت نداشته‌اند.
