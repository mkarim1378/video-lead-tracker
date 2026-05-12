# Changelog

All notable changes to **Video Lead Tracker** are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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
