# Changelog

All notable changes to **Video Lead Tracker** are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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
