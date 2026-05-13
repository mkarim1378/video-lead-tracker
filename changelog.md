# Changelog

All notable changes to **Video Lead Tracker** are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.2.1] - 2026-05-13

### Added
- Phase 25: Data Reset and Retention Management
  - **Settings → Data Management tab**: four scoped reset panels — Video Analytics (by video), Lead & Data (by lead ID), Page Analytics (by page ID), and Full Analytics Reset. Single-scope resets require a confirmation checkbox; Full Reset requires typing `DELETE`.
  - REST endpoints `POST /vlt/v1/admin/reset` and `POST /vlt/v1/admin/purge-logs` with `manage_options` capability check. Every reset action is logged to `vlt_logs` before executing.
  - **Full reset** truncates all analytics tables (leads, sessions, visitors, ranges, events, summaries, heatmap, page visits, logs). Video registry and plugin settings are preserved.
  - **Lead reset** removes the lead row, all associated ranges, events, summaries, sessions, page visits, and lead-name history, and clears `lead_id` from any visitor rows.
  - **Logs page**: "Purge All Logs" button calls `/admin/purge-logs` and reloads the page on success.
  - **Event Retention** setting (`event_retention_days`, default 0 = disabled) in Settings → Tracking. A daily WP-Cron job (`vlt_daily_cleanup`) deletes `vlt_video_events` rows older than the configured number of days. Cron is unscheduled on plugin deactivation.
  - `wp_localize_script` now provides `vltAdminData` (REST base + nonce) to `vlt-admin.js` on all VLT admin pages.

### Changed
- `VLT_VERSION` bumped 1.2.0 → 1.2.1.

---

## [1.2.0] - 2026-05-13

### Added
- Phase 24: Exit Point and Drop-off Analytics
  - `vlt-video-tracker.js` sends a `video_exit` event (with `exit_reason`: `pause`, `ended`, `hidden`, or `unload`) on every meaningful exit trigger — pause, ended, visibilitychange hidden, pagehide, and beforeunload. Unload exits use `sendBeacon` for reliability.
  - REST endpoint `track/video-event` now accepts `video_exit` event type. For non-completion exits (reason ≠ `ended`), it updates `last_position` on the viewer's `vlt_video_user_summary` row.
  - New `last_position` column added to `vlt_video_user_summary` (schema version 1.2 → 1.3). `dbDelta` adds it automatically on upgrade.
  - **Heatmap page**: new "Drop-off Curve" metric button shows where viewers stopped watching. Bars are drawn from `last_position` data bucketed at the same size as the watch heatmap. Top-3 exit buckets are highlighted in red.
  - **Video Analytics detail**: new "Peak Exit" KPI card showing the most common 5-second exit bucket (formatted as M:SS).

### Changed
- `VLT_DB_VERSION` bumped 1.2 → 1.3.
- `VLT_VERSION` bumped 1.1.2 → 1.2.0.

---

## [1.1.2] - 2026-05-13

### Added
- Phase 23: Per-Video Analytics Dashboard
  - **Overview page** (`Video Lead Tracker → Overview`): video filter dropdown lets admins scope all five KPI cards (Total Leads, Verified, Sessions, Videos, Watch Hours), Recent Leads table, and Top Videos table to a single video. Selecting "— All Videos —" restores the global view.
  - **Leads page** (`Video Lead Tracker → Leads`): same video filter dropdown scopes the lead list to only leads who watched the selected video. Search and sort remain functional within the filtered set. The active video filter is preserved across pagination and column sort links.
  - Each video-scoped Overview view uses its own transient cache key (`vlt_overview_cache_{video_id}`) so per-video and global caches do not collide.

### Changed
- `VLT_VERSION` bumped 1.1.1 → 1.1.2.

---

## [1.1.1] - 2026-05-13

### Added
- Phase 22: Payamito SMS Provider Integration
  - `send_payamito()` implemented in `VLT_OTP_Service` using Payamito SmartSMS REST API (`POST /api/SmartSMS/Send`).
  - Payamito requires two separate credentials: `username` (نام کاربری) and `password` (ApiKey from developer settings). New `otp_username` setting added to Settings → OTP.
  - On success (`RetStatus === 1`), logs the Payamito message ID. On failure, logs the error `Value` code for diagnosis without exposing credentials or mobile numbers.
  - Guard: if username, API key, or sender are not configured, logs a config error and skips the HTTP call.
  - HTTP timeout set to 15 s to accommodate Payamito's 3-attempt SmartSMS retry logic.

### Changed
- Settings OTP section: `otp_api_key` label updated to "API Key (password)" to clarify it is the Payamito ApiKey, not the login password.
- `VLT_VERSION` bumped 1.1.0 → 1.1.1.

---

## [1.1.0] - 2026-05-13

### Added
- Phase 21: Multi-Video Registry
  - New `vlt_videos` registry table extended with per-video form fields: `poster_url`, `form_title`, `name_label`, `mobile_label`, `submit_button_text`, `success_message`, `enable_otp`.
  - **Videos admin page** (`Video Lead Tracker → Videos`): full CRUD — add, edit, delete videos. Each video has its own key, title, URL, poster, duration, form labels, OTP toggle, and active state.
  - Shortcode now resolves video config from the registry by `key` attribute; falls back to the first registered video if no key is given.
  - On upgrade from DB version 1.1 → 1.2, existing single-video settings are automatically migrated into the registry as the first video.
  - OTP enabled/disabled is now a per-video setting; OTP provider infrastructure (API key, sender, template, etc.) remains global in Settings → OTP.
  - `VLT_DB` gained `get_all_videos()`, `get_first_video()`, `delete_video()`, `maybe_seed_video_registry()`.

### Changed
- Settings page: video-specific fields (Video Key, Video Title, Video URL, Video Duration) and lead form fields (Form Title, Name/Mobile labels, Submit text, Success message) removed — all now configured per-video in the Videos admin page.
- `VLT_DB_VERSION` bumped 1.1 → 1.2 to trigger schema upgrade.
- `VLT_VERSION` bumped 1.0.1 → 1.1.0.

### Fixed
- `.mo` compilation: replaced broken Python script with `msgfmt` — all Persian translations now load correctly.
- `.po` duplicate `msgid` entries removed (`Logs`, `Failed to send verification code`).

---

## [1.0.1] - 2026-05-12

### Fixed
- Mobile input field: maximum 11 digits enforced, auto-prepend `0` when user starts with `9`, Persian (U+06F0–U+06F9) and Arabic-Indic (U+0660–U+0669) numerals converted to Latin automatically, non-digit characters blocked.
- "Please wait…" button text was displayed in English on Persian-language sites; fixed by correcting the `msgid` in the `.po` file — the literal `\xe2\x80\xa6` escape was stored instead of the actual `…` character, preventing a translation match.
- Search placeholder in Leads admin page displayed garbled `\xe2\x80\xa6` escape sequence; both the PHP source and `.po` msgid/msgstr updated to use the actual `…` character.
- OTP provider field changed from free-text input to a select dropdown; default option is Payamito (پیامیتو).

### Changed
- CSS colour scheme: replaced WordPress default blue (`#0073aa`, `#005f8d`, `rgba(0,115,170,…)`) with site brand colours — CTA buttons and accents use navy `#2d2c74` (hover: `#1d1c5e`), focus ring uses `rgba(45,44,116,0.15)`.
- OTP provider default value changed from empty string to `payamito`.
- `VLT_VERSION` bumped 1.0.0 → 1.0.1.

---

## [1.0.0] - 2026-05-12

### Added
- Phase 20: Optimization & Release
  - **Transient caching** for Overview dashboard — all 5 KPI queries + Recent Leads + Top Videos cached for 5 minutes (`vlt_overview_cache`). Cache is invalidated automatically after every `VLT_Aggregator::aggregate()` call, ensuring live data after each range commit.
  - **Compound DB indexes** on `vlt_video_user_summary` — `KEY video_lead (video_id, lead_id)` and `KEY video_visitor (video_id, visitor_uuid)` — covering the two most-frequent upsert lookup patterns. Added to schema so both new installs (`dbDelta`) and existing installs (`VLT_DB_VERSION` 1.0 → 1.1 upgrade) receive the indexes automatically.

### Changed
- `VLT_DB_VERSION` bumped 1.0 → 1.1 to trigger schema upgrade on existing installs
- Version bumped 0.21.0 → **1.0.0** (first stable release)

### Fixed
- Fatal error on `vlt-logs` and `vlt-exports` pages: both menus still pointed to `render_placeholder()` which was removed in Phase 20. `vlt-exports` menu item removed (export is available inline on each report page); `render_logs()` implemented for `vlt-logs` with level filter and last-200-entry table.
- Shortcode tag corrected: canonical name is now `[video_lead_tracker]` with `key` and `url` attributes (matching README documentation). `[vlt_video_lead_gate]` retained as a legacy alias. Settings page descriptions updated to reflect the correct shortcode name and attribute.

### Removed
- `VLT_Admin::render_placeholder()` — dead code, all 5 admin pages are now fully implemented

---

## [0.21.0] - 2026-05-12

### Fixed
- Phase 19: QA & Edge Cases
  - **Bug — Heatmap JS key mismatch**: `drawBars()` read `b.bucket_start` but PHP emits `b.second` → bars had no tooltip text and axis labels were blank. Fixed JS to use `b.second`.
  - **Bug — Double-counting after lead identification**: `attach_lead_to_visitor()` updated ranges/events/sessions but left `vlt_video_user_summary` rows as anonymous (`lead_id IS NULL`). Subsequent aggregations created a second summary row for the same viewer, inflating viewer counts and averages.
    - `VLT_DB::attach_lead_to_visitor()` now collects affected `video_id`s, migrates summary rows (`SET lead_id = %d`), and returns the list.
    - `handle_lead_submit()` uses the returned list to re-run `VLT_Aggregator::aggregate()` for each migrated video, ensuring merged ranges are recomputed under the correct `lead_id`.
  - **Edge case — Heatmap bucket size**: `VLT_Settings::get('heatmap_bucket_size')` is not a defined setting → always 0 → `max(1, 0) = 1` → 1-second buckets → up to 7200 bars for a 2-hour video. `render_heatmap()` now auto-calculates bucket size to target ≤ 120 bars, stepping through `[1, 5, 10, 30, 60, 300, 600]` seconds.
  - **Edge case — Leads pagination overflow**: manually entering `?paged=999` beyond the last page showed an empty table with "Showing X to 0 of N". `$paged` is now clamped to `$total_pages` after computing it, and `$offset` is recalculated accordingly.

### Changed
- Version bumped 0.20.0 → 0.21.0

---

## [0.20.0] - 2026-05-12

### Added
- Phase 18: CSV Export System
  - **`VLT_Exporter`** class (`includes/Export/class-vlt-exporter.php`):
    - `init()` — registers `admin_post_vlt_export` action hook
    - `handle()` — validates nonce + capability, routes to export type
    - `export_leads()` — all leads with name, mobile, verified, videos watched, avg watch %, sessions, first/last seen
    - `export_videos()` — all active videos with viewers, avg %, watch hours, completions, sessions
    - `export_heatmap($video_id)` — per-second data for a single video (second, total views, unique visitors, unique leads)
    - `export_url($type, $extra)` — nonce-signed URL builder used by admin views
    - UTF-8 BOM prepended for correct Excel display on Windows
  - **Admin export buttons** (`page-title-action` links):
    - Leads list → "Export CSV" (all leads)
    - Video Analytics list → "Export CSV" (all videos)
    - Heatmap page (when a video is selected) → "Export Heatmap CSV"
  - `VLT_Exporter::init()` wired into `VLT_Plugin::init()`
  - 4 new fa_IR translations; .mo recompiled (169 entries)

### Changed
- Version bumped 0.19.0 → 0.20.0

---

## [0.19.0] - 2026-05-12

### Added
- Phase 17: Video Analytics + Heatmap UI
  - **Video Analytics list** (`render_video_analytics_list()`): table of all active videos with total views, unique visitors, unique leads, avg watch %, completed count, total watch hours
  - **Video Analytics detail** (`render_video_analytics_detail()`):
    - 5 KPI cards: Total Views, Unique Visitors, Unique Leads, Avg Watch %, Completed
    - **Watch Distribution** horizontal bar chart (4 buckets: 0–25%, 25–50%, 50–75%, 75–100%) with percentage-width bars
    - **Top Viewers** table: rank, viewer name/mobile (masked) or Anonymous, watch %, completed badge
  - **Heatmap page** (`render_heatmap()`):
    - Video selector dropdown — redirects to `?video_id=` on submit
    - Bucketed bar chart (bucket size auto-scaled from duration: 1s, 5s, 10s, 30s, 60s)
    - Data embedded via `data-buckets`, `data-max-total`, `data-max-unique`, `data-bucket-size` attributes
    - Metric toggle buttons (Total Views / Unique Visitors / Unique Leads) — vanilla JS, no jQuery
    - Bar heights computed as percentage of metric maximum; hover tooltip shows time range + value
    - Time axis with labels every ~10th bar (`M:SS` / `H:MM:SS` format)
  - `initHeatmap()`, `drawBars()`, `formatTime()` added to `assets/js/vlt-admin.js`
  - CSS additions: `.vlt-distribution`, `.vlt-dist-row/label/bar-wrap/bar/count`, `.vlt-hm-selector/toolbar/label/chart/bar/axis/tick/tick--label`
  - 14 new fa_IR translations; .mo recompiled (165 entries)

### Changed
- Version bumped 0.18.0 → 0.19.0

---

## [0.18.0] - 2026-05-12

### Added
- Phase 16: Leads Report and Lead Detail page
  - **`render_leads()`** — routes to list or detail view based on `?lead_id=` query param
  - **Leads list** (`render_lead_list()`):
    - Paginated table (20 per page) with full `paginate_links()` top + bottom navigation
    - Columns: #, Name (link to detail), Mobile (masked), Verified badge, Videos Watched, Avg Watch (progress bar), Sessions, First Seen
    - Sortable columns with `sort_link()` helper (ASC/DESC toggle, arrow indicator)
    - Search by name or normalized mobile (`LIKE` via `$wpdb->esc_like()`)
    - "Showing X to Y of Z" count display
    - "Clear" button when search is active
    - Whitelist-guarded `ORDER BY` to prevent SQL injection
  - **Lead detail** (`render_lead_detail()`):
    - Breadcrumb `← Leads` navigation
    - Meta header: mobile (unmasked), verified badge, first seen, last seen
    - **Video Watch History** table: watch %, unique watch seconds, sessions, completed badge, first play, last activity
    - **Recent Sessions** table (last 20): UUID (truncated with full title tooltip), started, duration (last_activity - started), device, browser, OS, landing URL (path only with full URL as tooltip/link)
  - `sort_link()` private helper — generates sortable `<th>` anchor with dashicon arrow
  - CSS additions: search form, progress bar, row number column, lead detail header meta strip, section titles, URL cell truncation
  - 24 new fa_IR translations; .mo recompiled (152 entries)

### Changed
- Plugin version bumped to `0.18.0`

---

## [0.17.0] - 2026-05-12

### Added
- Phase 15: Admin Overview Dashboard — `VLT_Admin::render_overview()` fully implemented:
  - Five KPI cards: Total Leads, Verified, Sessions, Active Videos, Watch Hours (unique)
  - **Recent Leads** table: last 10 leads with masked mobile, verified badge, avg watch %, first-seen date
  - **Top Videos** table: top 8 active videos ordered by total unique watch hours, showing viewers, completions, avg completion %, watch hours
  - "View all" / "View analytics" buttons linking to future submenu pages
  - `mask_mobile()` helper — shows first 5 + `•••` + last 4 digits
  - `format_duration()` helper — formats seconds as `M:SS` or `H:MM:SS`
- Overview CSS: KPI card row, two-column grid, badges, monospace mobile, muted helpers, full-width view-all button
- 22 new fa_IR translations for all admin UI strings; obsolete Phase 15 placeholder string removed from .po; .mo recompiled (128 entries)

### Changed
- Plugin version bumped to `0.17.0`

---

## [0.16.0] - 2026-05-12

### Added
- Phase 14: per-second heatmap aggregation
  - `VLT_Aggregator::update_heatmap()` — loops over every integer second covered by a committed range; calls two DB helpers per second
  - `VLT_DB::heatmap_increment_total()` — `INSERT … ON DUPLICATE KEY UPDATE` to create or increment `total_views_count` for one second
  - `VLT_DB::heatmap_try_unique()` — `INSERT IGNORE` into `vlt_video_heatmap_uniques` keyed by `(video_id, second_index, visitor_uuid)`; returns true when this is the viewer's first time watching that second
  - `VLT_DB::heatmap_increment_unique()` — increments `unique_visitors_count` (always) and `unique_leads_count` (when viewer is identified) after a new unique is confirmed
  - `vlt_video_heatmap_uniques` schema updated: unique key is now `(video_id, second_index, visitor_uuid)` — visitor_uuid is always present, lead_id stored as data column for reference
  - `VLT_DB::create_heatmap_uniques_table()` now called from `VLT_Activator::activate()`
  - `handle_track_video_range()` now calls both `aggregate()` and `update_heatmap()` after each successful range insert

### Changed
- Plugin version bumped to `0.16.0`

---

## [0.15.0] - 2026-05-12

### Added
- Phase 13: video summary aggregation — `VLT_Aggregator::aggregate()` and 6 new `VLT_DB` helpers:
  - `get_video_by_id()` — fetch video row by primary key
  - `get_video_ranges_for($video_id, $lead_id, $visitor_uuid)` — all raw ranges for a viewer; queries by `lead_id` when set, else by `visitor_uuid`
  - `has_video_event_type()` — boolean check for a specific event type
  - `get_first_video_event_at()` — `MIN(event_at)` for a given event type
  - `count_video_sessions()` — `COUNT(DISTINCT session_uuid)` from ranges
  - `upsert_video_user_summary()` — SELECT + INSERT or UPDATE pattern (no unique key required)
- `VLT_Aggregator::aggregate()` computes and upserts `vlt_video_user_summary` after every range commit:
  - Merges overlapping ranges (0.25 s tolerance) → `unique_watch_seconds` and `unique_watch_percent`
  - Sums raw range durations → `total_watch_seconds`
  - `reached_end`: `ended` event OR `max_video_time_seconds >= duration − 2 s`
  - Stores `raw_ranges_json` and `merged_ranges_json` for heatmap phase
- `handle_track_video_range()` now calls `VLT_Aggregator::aggregate()` after each successful range insert

### Changed
- Plugin version bumped to `0.15.0`

---

## [0.14.0] - 2026-05-12

### Added
- `POST /vlt/v1/track/video-range` fully implemented (Phase 12):
  - Silently drops invalid ranges (`to <= from` or `duration < min_valid_range_seconds`) without error
  - Silently ignores if video record doesn't exist yet
  - Inserts into `vlt_video_ranges` with `from_second`, `to_second`, `duration_seconds`, `playback_rate`, `committed_reason`
- `VLT_DB::create_video_range()` — inserts a row into `vlt_video_ranges`
- Range tracking state machine in `vlt-video-tracker.js` (Phase 12):
  - `startRange()` — records `rangeStart = currentTime` on play / post-seek resume
  - `commitRange(reason)` — validates and POSTs via `vltApiFetch`; reasons: `pause`, `seek`, `ended`, `heartbeat`
  - `commitRangeBeacon(reason)` — POSTs via `sendBeacon` (Blob + `application/json`) with `fetch keepalive` fallback; reasons: `page_hidden`, `unload`
  - Seek flow: commit current range before seek → restart range after `seeked` if was playing
  - Heartbeat: commit current segment + immediately restart a new range from current time
  - `visibilitychange` + `pagehide` / `beforeunload` listeners for unload commits
  - Suppresses spurious `pause` event fired by browsers during seeking (`isSeeking` guard)

### Changed
- Plugin version bumped to `0.14.0`

---

## [0.13.0] - 2026-05-12

### Added
- `POST /vlt/v1/track/video-event` fully implemented (Phase 11):
  - Valid event types: `video_loaded`, `play`, `pause`, `seek_start`, `seek_end`, `heartbeat`, `ended`, `error`, `rate_change`
  - Auto-creates or resolves `vlt_videos` row by `video_key`; stores `duration_seconds` on first `video_loaded`
  - Inserts into `vlt_video_events` with full context: `video_time_seconds`, `from_second`, `to_second`, `playback_rate`, `duration_seconds`, `metadata`
  - Silently ignores requests when `enable_tracking` setting is off
- `VLT_DB::get_video_by_key()`, `create_video()`, `update_video()`, `create_video_event()` — video CRUD helpers
- `assets/js/vlt-video-tracker.js` — HTML5 video event tracker:
  - Boots on `vlt:videoReady` custom event dispatched by `vlt-frontend.js`
  - Listens: `loadedmetadata` → `video_loaded`, `play`, `pause`, `seeking` → `seek_start`, `seeked` → `seek_end`, `ended`, `ratechange` → `rate_change`, `error`
  - Heartbeat `setInterval` while playing (replaces noisy `timeupdate` — ~4 events/sec)
  - Suppresses spurious `pause` fired by browsers during seeking (`isSeeking` flag)
  - Sends via `window.vltApiFetch` (shared with `vlt-frontend.js`)
- `vlt-video-tracker.js` registered with `vlt-frontend` dependency and enqueued on shortcode pages

### Changed
- Plugin version bumped to `0.13.0`

---

## [0.12.0] - 2026-05-12

### Added
- Persian (`fa_IR`) translation — 106 strings covering all admin, settings, frontend, OTP, and REST API messages
  - `languages/video-lead-tracker.pot` — translation template (gettext POT format)
  - `languages/video-lead-tracker-fa_IR.po` — Persian source translations
  - `languages/video-lead-tracker-fa_IR.mo` — compiled binary loaded by WordPress
- `load_plugin_textdomain()` called in `VLT_Plugin::init()` pointing to `languages/` directory

### Changed
- All user-facing REST API error messages wrapped with `__()` (rate limit, validation, lead creation, mobile format)
- All `VLT_OTP_Service` user-facing error messages wrapped with `__()` (cooldown, send failure, expired, locked, invalid)
- Plugin version bumped to `0.12.0`

---

## [0.11.0] - 2026-05-12

### Added
- `POST /vlt/v1/track/page` fully implemented (Phase 10):
  - Accepts `page_view`, `page_visible`, `page_hidden`, `page_unload`, `heartbeat` event types
  - Silently ignores requests when `enable_tracking` setting is off
  - Inserts into `vlt_page_visits` with `session_uuid`, `visitor_uuid`, `lead_id`, `page_id`, `page_url`, `event_type`, `event_at`, `time_on_page_seconds`, `metadata`
  - Updates session `last_activity_at` on `heartbeat`, `page_hidden`, `page_unload` events
- `VLT_DB::create_page_visit()` — inserts a row into `vlt_page_visits`
- Page tracking in `vlt-frontend.js` (`startPageTracking()`):
  - Sends `page_view` immediately after session init (with `referrer` + UTM in metadata)
  - Tracks cumulative visible time (`visibleSeconds`) paused while tab is hidden
  - `visibilitychange` listener → fires `page_visible` / `page_hidden`
  - Heartbeat `setInterval` (respects `heartbeat_interval` setting) — skips when tab hidden
  - `pagehide` + `beforeunload` fallback → fires `page_unload` via `navigator.sendBeacon()` (Blob + `application/json`), falling back to `fetch({ keepalive: true })`
- `vltConfig.pageId` — WP post ID passed from PHP for page visit records

### Changed
- Plugin version bumped to `0.11.0`

---

## [0.10.0] - 2026-05-12

### Added
- `VLT_OTP_Service` — full OTP module (Phase 9):
  - `send()`: enforces per-mobile resend cooldown, generates a cryptographically random 6-digit code, stores SHA-256 hash + expiry in `vlt_otp_codes`, dispatches via configured provider
  - `verify()`: fetches latest non-expired non-verified OTP, increments attempt counter before checking (brute-force safe), uses `hash_equals()`, marks code verified on success
  - Provider dispatch: Kavenegar (`kavenegar`), SMS.ir (`sms_ir`), or generic fallback (logs code to `vlt_logs` for development)
- `POST /vlt/v1/otp/send` — rate-limited 5 req/5min per IP; validates `normalized_mobile`; calls `VLT_OTP_Service::send()`
- `POST /vlt/v1/otp/verify` — rate-limited 10 req/5min per IP; validates code format; calls `VLT_OTP_Service::verify()`; sets `is_verified = 1` on the lead row
- OTP step UI in `vlt-frontend.js`:
  - After form submit, if `vltConfig.otp.enabled`, shows OTP container instead of video
  - Auto-sends OTP on step entry; displays masked mobile hint
  - Resend button with live countdown timer (respects `otp_resend_cooldown` setting)
  - On successful verify, transitions to video
- OTP HTML container rendered by `VLT_Frontend::render_html()` (code input, verify button, resend section)
- OTP config passed via `vltConfig.otp` (`enabled`, `cooldown`) and 6 new i18n keys
- OTP step styles in `vlt-frontend.css` (hint, resend button, countdown)

### Changed
- Plugin version bumped to `0.10.0`

---

## [0.9.0] - 2026-05-12

### Added
- `POST /vlt/v1/lead/submit` fully implemented (Phase 8):
  - Rate-limited to 10 req/5min per IP
  - Validates required `name` and `mobile` fields
  - Normalizes Iranian mobile numbers via `VLT_REST_Controller::normalize_mobile()`
  - Finds or creates lead by `normalized_mobile`; promotes `primary_name` only if previously blank
  - Always inserts a `vlt_lead_names` history row (tracks every submission)
  - Generates a new `identity_token` on every successful submit
  - Calls `VLT_DB::attach_lead_to_visitor()` to bulk-migrate anonymous sessions/events to the lead
  - Updates visitor row with `lead_id` + new `identity_token_hash`
  - Returns `{ lead_id, identity_token, normalized_mobile, mobile_hash, show_video: true }`
- `VLT_REST_Controller::normalize_mobile()` — converts `09xxxxxxxxx`, `9xxxxxxxxx`, `+98xxxxxxxxxx`, `0098xxxxxxxxxx` to canonical `98xxxxxxxxxx`; returns `null` on invalid input
- `VLT_DB::create_lead_name()` — inserts into `vlt_lead_names` (name history)

### Changed
- Plugin version bumped to `0.9.0`

---

## [0.8.0] - 2026-05-12

### Added
- `POST /vlt/v1/session/init` fully implemented (Phase 7):
  - Rate-limited to 60 req/min per IP
  - Validates `visitor_uuid` + `identity_token` pair against DB hash
  - Known visitor: refreshes `last_seen_at`, reuses token, resolves `lead_id`
  - New visitor: generates `visitor_uuid`, creates identity token, stores SHA-256 hash
  - Creates a fresh `session_uuid` on every call with UTM, referrer, IP hash, UA, device/browser/OS
  - Returns `{ visitor_uuid, session_uuid, known_lead, lead_id, identity_token, show_form, identity_invalid }`
- `VLT_REST_Controller::detect_browser()` — Opera / Edge / Chrome / Firefox / Safari / IE detection
- `VLT_REST_Controller::detect_os()` — iOS / Android / Windows / macOS / Linux detection
- `VLT_DB` generic helpers: `insert()` (skips null fields) and `update_where()`
- `VLT_DB` visitor CRUD: `create_visitor()`, `get_visitor_by_uuid()`, `update_visitor()`
- `VLT_DB` session CRUD: `create_session()`, `update_session()`
- `VLT_DB` lead stubs: `get_lead_by_mobile()`, `create_lead()`, `update_lead()` (used in Phase 8)
- `VLT_DB::attach_lead_to_visitor()` — bulk UPDATE across 5 tables when anonymous data is merged into a lead

### Changed
- Plugin version bumped to `0.8.0`

---

## [0.7.0] - 2026-05-12

### Added
- `VLT_REST_Controller` — full Phase 6 REST base:
  - 5 routes registered: `POST /session/init`, `/lead/submit`, `/track/page`, `/track/video-event`, `/track/video-range` (handlers are stubs returning 501 until Phases 7-12)
  - `success()` / `error()` — structured response helpers
  - `param()` / `str_param()` / `float_param()` — typed request body readers
  - `generate_uuid()` — wraps `wp_generate_uuid4()`
  - `generate_identity_token()` — 32-byte cryptographic random hex
  - `hash_token()` — SHA-256 keyed with WP `secure_auth` salt
  - `validate_token()` — DB lookup by `visitor_uuid` + token hash
  - `get_client_ip()` — checks CF, X-Forwarded-For, X-Real-IP, REMOTE_ADDR in order
  - `hash_ip()` — null / raw / SHA-256 hash per `ip_storage_mode` setting
  - `get_user_agent()` / `hash_user_agent()` — respects `store_user_agent` setting
  - `detect_device()` — basic mobile / tablet / desktop detection from UA string
  - `extract_utm()` — pulls and sanitizes 5 UTM params from request body
  - `check_rate_limit()` — transient-based rate limiter (type + identifier + limit + window)
- `VLT_Logger` — static logger writing to `vlt_logs` table: `debug()`, `info()`, `warning()`, `error()`
- `VLT_Logger` added to autoloader map

### Changed
- Plugin version bumped to `0.7.0`

---

## [0.6.0] - 2026-05-12

### Added
- `assets/js/vlt-admin.js` — reusable tab engine: `initTabGroup()` wires any `.vlt-tab-nav` nav to its `.vlt-tab-panel` panels using `display` toggle; active tab persisted in `localStorage` via `data-storage-key`
- `assets/css/vlt-admin.css` — `<button>` reset so WP `nav-tab` classes render identically to `<a>`-based tabs
- `VLT_Admin::enqueue_assets()` — loads admin JS/CSS on all `vlt-*` pages only

### Changed
- Settings tabs now switch instantly with `display: none / block` — no page reload
- Tab buttons changed from `<a href="?tab=...">` to `<button type="button" data-tab="panel-id">` 
- All tab panels rendered in the DOM at once; the form submits every setting regardless of which tab is visible
- `VLT_Settings::register()` simplified to `register_setting()` only — fields rendered manually, no `add_settings_section/field`
- Plugin version bumped to `0.6.0`

---

## [0.5.0] - 2026-05-12

### Added
- `mobileHash` field added to `vltState` (populated from API response in Phase 8)
- `mobile_hash` and `created_at` fields added to browser identity payload
- `created_at` is set once on first save and preserved across all subsequent saves
- Cross-restore logic in `loadIdentity()`:
  - localStorage present but cookie missing → cookie rebuilt from localStorage
  - Cookie present but localStorage missing → localStorage rebuilt from cookie
- `clearIdentity()` now also clears `vltState.mobileHash`
- `saveIdentity()` syncs `mobile_hash` into live `vltState` when provided

### Changed
- Plugin version bumped to `0.5.0`
- No jQuery — confirmed pure vanilla JS throughout

---

## [0.4.0] - 2026-05-12

### Added
- `VLT_Frontend`: full shortcode `[vlt_video_lead_gate]` implementation with `video_key`, `src`, `poster`, `title` attributes
- PHP HTML renderer outputs loading spinner, lead form, and hidden video player as distinct containers
- `assets/css/vlt-frontend.css`: responsive, RTL-aware styles for form, spinner, video, error messages
- `assets/js/vlt-frontend.js`: complete browser-side state machine
  - Identity load from `localStorage` with cookie fallback
  - `saveIdentity()` / `clearIdentity()` — stores `visitor_uuid`, `lead_id`, `identity_token`
  - `initSession()` — calls `POST /vlt/v1/session/init`; shows form or video based on response
  - Form submit handler — calls `POST /vlt/v1/lead/submit`; saves identity, reveals video
  - Dispatches `vlt:videoReady` custom event so Phase 11 video tracker can hook in
  - Exposes `window.vltState`, `window.vltSaveIdentity`, `window.vltApiFetch` for later modules
- `wp_localize_script` passes `vltConfig` (REST base, nonce, video key, settings, i18n strings)
- Scripts/styles registered globally but enqueued only when shortcode is present on the page

### Changed
- Plugin version bumped to `0.4.0`

---

## [0.3.0] - 2026-05-12

### Added
- Full Settings system via WordPress Settings API (`register_setting`, `add_settings_section`, `add_settings_field`)
- Tabbed settings UI in admin: **General**, **Lead Form**, **Tracking**, **OTP**, **Export**
- 27 configurable settings fields with sanitization per field type (checkbox, text, number, url, select, textarea, password, page_select)
- `VLT_Settings::render_page()` — tabbed settings page with WP `nav-tab` UI and inline save confirmation
- `VLT_Settings::sanitize()` — config-driven sanitizer, validates select options against allowed values
- OTP settings fields added to defaults (provider, api_key, sender, template, expiry, cooldown, max_attempts)

### Changed
- `VLT_Admin` settings submenu now routes to `VLT_Settings::render_page()` instead of placeholder
- Plugin version bumped to `0.3.0`

---

## [0.2.0] - 2026-05-12

### Added
- Full database schema: 11 core tables created via `dbDelta()` on activation
  - `vlt_leads` — unique leads by normalized mobile
  - `vlt_lead_names` — full name submission history per lead
  - `vlt_visitors` — browser/device-level anonymous identity with token hash
  - `vlt_sessions` — per-visit sessions with UTM, referrer, device info, IP hash
  - `vlt_page_visits` — page-level events (page_view, page_hidden, heartbeat, etc.)
  - `vlt_videos` — video registry by `video_key`
  - `vlt_video_events` — raw video player events (play, pause, seek, ended, …)
  - `vlt_video_ranges` — committed watched timeline segments per visitor/lead
  - `vlt_video_user_summary` — aggregated per-lead/per-video stats with JSON range fields
  - `vlt_video_heatmap` — per-second aggregate view counts with unique lead/visitor counts
  - `vlt_logs` — plugin debug/system log entries
- Two optional tables created on demand: `vlt_otp_codes`, `vlt_video_heatmap_uniques`
- Auto-upgrade check in `VLT_Plugin::init()` — runs `create_tables()` when `vlt_db_version` is behind `VLT_DB_VERSION`

### Changed
- Plugin version bumped to `0.2.0`
- `VLT_DB` stub replaced with full schema implementation

---

## [0.1.0] - 2026-05-12

### Added
- Plugin bootstrap file with header, constants (`VLT_VERSION`, `VLT_PLUGIN_FILE`, `VLT_PLUGIN_DIR`, `VLT_PLUGIN_URL`, `VLT_DB_VERSION`) and PSR-style autoloader
- `VLT_Plugin` — singleton bootstrap, wires all components on `plugins_loaded`
- `VLT_Activator` — runs on activation: calls `VLT_DB::create_tables()`, saves DB version, flushes rewrite rules
- `VLT_Deactivator` — flushes rewrite rules on deactivation (no data deleted)
- `VLT_DB` — stub with `create_tables()`, `get_version()`, `needs_upgrade()` (tables created in Phase 2)
- `VLT_Settings` — settings store with defaults, `get()`, `all()`, `update()` helpers
- `VLT_Admin` — admin menu with 7 submenu pages (Overview, Leads, Video Analytics, Heatmap, Exports, Settings, Logs); access restricted to `manage_options`
- `VLT_REST_Controller` — namespace `vlt/v1` registered; route stubs for Phase 6
- `VLT_Frontend` — shortcode `[vlt_video_lead_gate]` registered; full render in Phase 4
- `VLT_Tracker`, `VLT_Aggregator` (with `merge_ranges()`), `VLT_Exporter`, `VLT_OTP_Service` — class stubs
- `uninstall.php` — drops all plugin tables and options only when `delete_on_uninstall` setting is enabled
