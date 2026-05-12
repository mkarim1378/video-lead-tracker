# Video Lead Tracker

> Gate your video content behind a lead form — then know exactly who watched, how much, and where they dropped off.

Video Lead Tracker is a self-hosted WordPress plugin that turns any HTML5 video into a lead-capture and analytics engine. Visitors submit their name and mobile number before the video unlocks. Every second of playback is recorded, aggregated, and surfaced in a built-in admin dashboard — with no third-party analytics services and no data leaving your server.

---

## Why Video Lead Tracker

Most lead-capture tools stop at the form. You collect a name and number, but you have no idea whether the person who submitted actually watched your content — or for how long. Video Lead Tracker closes that gap.

- A lead who watched 80% of your product demo behaves differently from one who watched 5 seconds and left. Now you can tell them apart.
- All data lives in your WordPress database. No external SaaS, no tracking pixels, no GDPR headaches from third parties.
- Built for the Iranian market: native support for all local mobile number formats and direct integration with Kavenegar and SMS.ir for OTP verification.

---

## Features

### Lead Capture & Identity

- Overlay form collects **name + mobile number** before the video plays
- Mobile number is normalized from any Iranian format (`09xxxxxxxxx`, `+98xxxxxxxxxx`, etc.) to a canonical `98xxxxxxxxxx` form
- Returning visitors are recognized automatically via a secure identity token stored in `localStorage` and a cookie — they skip the form on repeat visits
- Anonymous viewing data (ranges, events, page visits) is **retroactively linked** to the lead record the moment the form is submitted
- Optional **OTP verification** via SMS confirms mobile ownership before video access

### OTP Verification

- 6-digit cryptographically random code, SHA-256 hashed before storage
- Configurable code expiry and resend cooldown
- Built-in integrations: **Kavenegar** and **SMS.ir**
- Brute-force safe: attempt counter increments before code comparison
- Clean, in-page OTP UI with masked mobile display and resend countdown

### Video Analytics

**Event tracking** — every meaningful player action is recorded:

| Event | Description |
|---|---|
| `video_loaded` | Metadata loaded; video duration stored |
| `play` / `pause` | Playback started or stopped |
| `seek_start` / `seek_end` | User scrubbed the timeline |
| `heartbeat` | Periodic progress ping (default every 10 s) |
| `ended` | Video played to the end |
| `rate_change` | Playback speed changed |
| `error` | Player error with error code |

**Watch range tracking** — continuous play segments are captured as `[from_second, to_second]` pairs:

- Ranges are committed on pause, seek, heartbeat, and page close
- Page-close commits use `navigator.sendBeacon` with a `fetch keepalive` fallback so no data is lost when the tab closes
- Seeking mid-play correctly commits the pre-seek segment and starts a fresh one at the new position

**Per-viewer summary** — after each range commit, aggregated stats are updated in real time:

| Metric | How it's calculated |
|---|---|
| `total_watch_seconds` | Sum of all raw range durations |
| `unique_watch_seconds` | Sum of merged (deduplicated) ranges |
| `unique_watch_percent` | `unique / duration × 100`, capped at 100% |
| `max_video_time_seconds` | Furthest point reached in the video |
| `reached_end` | `ended` event fired, OR max position ≥ duration − 2 s |
| `sessions_count` | Distinct sessions in which the video was watched |
| `first_play_at` | Timestamp of the viewer's first play event |

Range deduplication uses a 0.25 s tolerance so scrubbing back a tiny amount doesn't inflate unique watch time.

**Per-second heatmap** — for each video, a heatmap table tracks how many viewers (total, unique leads, unique visitors) watched each second. Useful for spotting the exact moment engagement drops.

### Page Visit Tracking

- `page_view`, `page_visible`, `page_hidden`, `page_unload`, and `heartbeat` events
- Visibility API for accurate focus/blur detection
- `sendBeacon` on tab close so unload events are never lost

### Admin Dashboard

- **Overview** — plugin-wide totals: leads, sessions, videos, watch hours
- **Leads Report** — sortable table of all leads with watch stats and verification status; click any row for the full lead detail view
- **Video Analytics** — per-video funnel: viewers → starters → completers; average unique watch percent; timeline of activity
- **Heatmap UI** — color-coded, per-second bar chart showing where viewers engage and where they drop
- **Export** — download any report as CSV or XLSX

### Privacy & Security

- IP addresses can be stored raw, SHA-256 hashed, or not stored at all (configurable per site)
- User-agent storage is opt-in
- Identity tokens are never stored; only their SHA-256 hash (keyed with WP's `secure_auth` salt) is persisted
- All write endpoints are rate-limited per IP using WordPress transients
- No jQuery dependency — pure vanilla ES5 JavaScript

---

## Requirements

| | Minimum |
|---|---|
| WordPress | 5.8 |
| PHP | 7.4 |
| MySQL | 5.6 / MariaDB 10.1 |

---

## Installation

1. Upload the `video-lead-tracker` folder to `/wp-content/plugins/`
2. Activate the plugin from **Plugins → Installed Plugins**
3. Go to **Video Tracker → Settings** to configure OTP provider, tracking options, and privacy preferences
4. Place the shortcode on any page or post:

```
[video_lead_tracker key="my-video" url="https://example.com/video.mp4"]
```

### Shortcode Parameters

| Parameter | Default | Description |
|---|---|---|
| `key` | _(required)_ | Unique identifier for this video |
| `url` | _(required)_ | Direct URL to the `.mp4` (or any HTML5-compatible format) |
| `title` | — | Video title shown in admin reports |
| `submit_text` | `مشاهده ویدیو` | Label on the form submit button |

---

## OTP Provider Setup

### Kavenegar

1. Log in to your Kavenegar panel and copy your API key
2. Go to **Video Tracker → Settings → OTP** and select **Kavenegar**
3. Paste your API key and set the sender line number

### SMS.ir

1. Go to **Video Tracker → Settings → OTP** and select **SMS.ir**
2. Enter your API key
3. Set the template ID for the OTP message pattern

---

## Database Schema

The plugin creates 11 tables (prefixed with `wp_vlt_`):

| Table | Purpose |
|---|---|
| `vlt_leads` | One row per unique mobile number |
| `vlt_lead_names` | Full history of names submitted per lead |
| `vlt_visitors` | One row per browser (anonymous identity) |
| `vlt_sessions` | One row per page load |
| `vlt_page_visits` | Page tracking events |
| `vlt_videos` | One row per `video_key` |
| `vlt_video_events` | Player events (play, pause, seek, …) |
| `vlt_video_ranges` | Raw watch segments `[from, to]` |
| `vlt_video_user_summary` | Aggregated per-viewer stats |
| `vlt_video_heatmap` | Per-second view counts |
| `vlt_logs` | Internal plugin logs |

Optional tables created on demand:

| Table | Created when |
|---|---|
| `vlt_otp_codes` | OTP feature is enabled |
| `vlt_video_heatmap_uniques` | Heatmap deduplication is enabled |

Schema upgrades are handled automatically via WordPress's `dbDelta()` on plugin update.

---

## REST API

All endpoints live under `/wp-json/vlt/v1/`.

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/session/init` | Initialize or restore a visitor session |
| `POST` | `/lead/submit` | Submit the lead form |
| `POST` | `/otp/send` | Send an OTP to the visitor's mobile |
| `POST` | `/otp/verify` | Verify the submitted OTP code |
| `POST` | `/track/page` | Record a page visit event |
| `POST` | `/track/video-event` | Record a player event |
| `POST` | `/track/video-range` | Commit a watched range segment |

---

## Localization

The plugin ships with a full **Persian (fa_IR)** translation. All user-facing strings in both PHP and JavaScript are wrapped with WordPress's standard `__()` / `_e()` functions and the `video-lead-tracker` text domain.

To add a new language, copy `languages/video-lead-tracker.pot` and use Poedit or Loco Translate to create a `.po` file, then compile it to `.mo`.

---

## Architecture Notes

- **No jQuery.** All frontend JavaScript is plain ES5, split across two IIFE modules:
  - `vlt-frontend.js` — session init, form, OTP, page tracking, shared state (`window.vltState`, `window.vltApiFetch`)
  - `vlt-video-tracker.js` — video event and range tracking; boots on the custom `vlt:videoReady` event
- **Static classes.** All PHP classes use static methods and a singleton bootstrap (`VLT_Plugin::get_instance()`) — no instantiation required.
- **Manual autoloader.** A `spl_autoload_register` map in the main plugin file replaces Composer autoload to keep the plugin self-contained with zero dependencies.
- **Aggregation on write.** `VLT_Aggregator::aggregate()` runs synchronously after every range insert so the summary table is always up to date without background jobs.

---

## License

GPLv2 or later — the same license as WordPress itself.

---

## Author

**Mohamad Karim** — [m-karim.ir](https://m-karim.ir)
