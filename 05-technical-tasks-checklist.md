# WordPress Video Lead Tracking Plugin - Technical Tasks Checklist

## 1. Core Plugin Setup

- [ ] Create plugin bootstrap file
- [ ] Define constants
- [ ] Create class loader
- [ ] Add activation hook
- [ ] Add deactivation hook
- [ ] Add uninstall logic
- [ ] Add database version option

---

## 2. Database Layer

- [ ] Create migration class
- [ ] Create `leads` table
- [ ] Create `lead_names` table
- [ ] Create `visitors` table
- [ ] Create `sessions` table
- [ ] Create `page_visits` table
- [ ] Create `videos` table
- [ ] Create `video_events` table
- [ ] Create `video_ranges` table
- [ ] Create `video_user_summary` table
- [ ] Create `video_heatmap` table
- [ ] Create `logs` table
- [ ] Optional `otp_codes` table
- [ ] Add indexes
- [ ] Add db upgrade logic

---

## 3. Settings Layer

- [ ] Add admin menu
- [ ] Add settings page
- [ ] Add general settings section
- [ ] Add form settings section
- [ ] Add tracking settings section
- [ ] Add OTP settings section
- [ ] Add export settings section
- [ ] Save and validate settings

---

## 4. Frontend Rendering

- [ ] Register shortcode
- [ ] Render lead form
- [ ] Render HTML5 video
- [ ] Add wrapper data attributes
- [ ] Enqueue frontend CSS
- [ ] Enqueue frontend JS
- [ ] Pass REST config to frontend

---

## 5. Browser Identity

- [ ] Generate visitor UUID
- [ ] Generate session UUID
- [ ] Create identity token
- [ ] Save token hash in database
- [ ] Save identity in localStorage
- [ ] Save backup cookie
- [ ] Validate identity on init
- [ ] Clear invalid identity
- [ ] Restore known lead without showing form

---

## 6. REST API

- [ ] Register namespace `/vlt/v1`
- [ ] Create `/session/init`
- [ ] Create `/lead/submit`
- [ ] Create `/track/page`
- [ ] Create `/track/video-event`
- [ ] Create `/track/video-range`
- [ ] Optional `/otp/send`
- [ ] Optional `/otp/verify`
- [ ] Add request sanitizers
- [ ] Add response helpers
- [ ] Add auth helpers
- [ ] Add rate limiting helpers

---

## 7. Lead Logic

- [ ] Normalize mobile numbers
- [ ] Validate Iranian mobile format
- [ ] Find lead by normalized mobile
- [ ] Create lead if not exists
- [ ] Save submitted name history
- [ ] Attach visitor to lead
- [ ] Attach session to lead
- [ ] Update last_seen_at
- [ ] Decide primary_name behavior
- [ ] Merge cross-device visitors under same lead

---

## 8. Anonymous Data Attachment

- [ ] Attach anonymous page visits to lead after submit
- [ ] Attach anonymous video events to lead after submit
- [ ] Attach anonymous ranges to lead after submit
- [ ] Recompute summary after attachment

---

## 9. Page Tracking

- [ ] Send `page_view`
- [ ] Send `page_visible`
- [ ] Send `page_hidden`
- [ ] Send `page_unload`
- [ ] Send heartbeat
- [ ] Store page metadata
- [ ] Track referrer
- [ ] Track UTM params

---

## 10. Video Event Tracking

- [ ] Listen to `loadedmetadata`
- [ ] Listen to `play`
- [ ] Listen to `pause`
- [ ] Listen to `seeking`
- [ ] Listen to `seeked`
- [ ] Listen to `ended`
- [ ] Listen to `ratechange`
- [ ] Listen to `error`
- [ ] Implement heartbeat strategy
- [ ] Throttle noisy events

---

## 11. Range Tracking

- [ ] Maintain active playback state
- [ ] Start range on play
- [ ] Commit range on pause
- [ ] Commit range before seek
- [ ] Resume range after seek if still playing
- [ ] Commit range on ended
- [ ] Commit range on page hidden
- [ ] Commit range on unload
- [ ] Commit chunk on heartbeat
- [ ] Ignore invalid tiny ranges
- [ ] Save committed reason

---

## 12. Aggregation Logic

- [ ] Load all ranges per lead/video
- [ ] Merge overlapping ranges
- [ ] Merge contiguous ranges with tolerance
- [ ] Calculate total_watch_seconds
- [ ] Calculate unique_watch_seconds
- [ ] Calculate unique_watch_percent
- [ ] Calculate max_video_time_seconds
- [ ] Set started flag
- [ ] Set reached_end flag
- [ ] Save raw_ranges_json
- [ ] Save merged_ranges_json

---

## 13. Heatmap Logic

- [ ] Convert raw ranges to second indexes
- [ ] Increment total_views_count
- [ ] Plan unique lead per second strategy
- [ ] Cache per-second aggregates
- [ ] Add rebuild heatmap action
- [ ] Add bucket aggregation for display
- [ ] Support 1/5/10/30 second buckets

---

## 14. Admin Overview

- [ ] Create overview page
- [ ] Add KPI cards
- [ ] Add leads over time chart
- [ ] Add sessions over time chart
- [ ] Add source/referrer widgets
- [ ] Add video engagement summary

---

## 15. Leads Reporting

- [ ] Create leads table page
- [ ] Add search by mobile
- [ ] Add search by name
- [ ] Add date range filter
- [ ] Add video started filter
- [ ] Add reached end filter
- [ ] Add watch percent filter
- [ ] Add UTM filters
- [ ] Add pagination

---

## 16. Lead Detail Reporting

- [ ] Show lead basic info
- [ ] Show all submitted names
- [ ] Show visitors/devices
- [ ] Show sessions
- [ ] Show page visits
- [ ] Show video events
- [ ] Show raw ranges
- [ ] Show merged ranges
- [ ] Show summary metrics
- [ ] Show mini timeline/visualization

---

## 17. Heatmap UI

- [ ] Create heatmap admin page
- [ ] Show per-second or bucketed data
- [ ] Add bucket selector
- [ ] Add legend/colors
- [ ] Add tooltip details
- [ ] Add table view below chart
- [ ] Add rebuild button

---

## 18. Export

- [ ] Export leads
- [ ] Export sessions
- [ ] Export page visits
- [ ] Export video events
- [ ] Export ranges
- [ ] Export lead summaries
- [ ] Export heatmap
- [ ] CSV UTF-8 BOM fallback
- [ ] XLSX preferred implementation

---

## 19. OTP Optional

- [ ] Add provider interface
- [ ] Add SMS provider implementation
- [ ] Generate OTP
- [ ] Hash OTP
- [ ] Save expiry
- [ ] Limit resend
- [ ] Limit attempts
- [ ] Verify OTP
- [ ] Mark lead verified
- [ ] Add OTP UI step

---

## 20. Security and Reliability

- [ ] Sanitize all input
- [ ] Escape all output
- [ ] Use prepared SQL
- [ ] Validate nonces/tokens
- [ ] Restrict admin pages to admins
- [ ] Add error logging
- [ ] Add graceful frontend failure behavior
- [ ] Add manual rebuild tools
- [ ] Add performance indexes where needed

---

## 21. QA

- [ ] Test first visit flow
- [ ] Test returning same-browser flow
- [ ] Test cross-device same mobile flow
- [ ] Test multiple names same mobile
- [ ] Test play/pause ranges
- [ ] Test seek behavior
- [ ] Test replay overlap behavior
- [ ] Test pagehide/unload commits
- [ ] Test heartbeat recovery
- [ ] Test admin filters
- [ ] Test export encoding
- [ ] Test permission restrictions