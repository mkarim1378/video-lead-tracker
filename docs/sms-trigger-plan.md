# Phase 25 — ارسال پیامک در نقطه‌ی زمانی خاص از ویدیو (SMS Triggers)

## هدف

ادمین برای **هر ویدیو به‌صورت مستقل** تعیین کند که وقتی لید به ثانیه‌ی مشخصی از ویدیو رسید، یک پیامک متنی برایش ارسال شود — **فقط یک بار برای هر لید**.

## تصمیم‌های قطعی

| موضوع | تصمیم |
|---|---|
| نوع پیامک | فقط **متن آزاد** (بدون پترن). |
| دقت زمانی | تأخیر تا یک heartbeat (≈۱۰ ثانیه) قابل قبول است → تشخیص کاملاً سمت سرور، بدون تغییر JS پلیر. |
| OTP | **اختیاری.** اگر OTP مؤثر (ویدیو + سراسری) خاموش باشد، قابلیت قابل فعال‌سازی است ولی کنار آن یک هشدار کوچک نمایش داده می‌شود. اگر OTP مؤثر روشن باشد، پیامک فقط به لید `is_verified = 1` می‌رود؛ اگر خاموش باشد، به شماره‌ی واردشده (تأییدنشده) ارسال می‌شود. |
| صوت / پادکست | خارج از محدوده. |
| شرط رسیدن | حالت ثابت `watched`: بازه‌ای از پخش واقعی از ثانیه‌ی تریگر عبور کرده باشد (`from_second < t <= to_second`). پرش با seek از روی نقطه پیامک نمی‌فرستد. |

---

## وضعیت فعلی (یافته‌های بررسی کد)

- ارسال پیامک فقط داخل `VLT_OTP_Service::dispatch()` (private) است؛ در UI تنظیمات فقط Payamito قابل انتخاب است (Kavenegar و SMS.ir در کد هستند ولی در dropdown نیستند).
- OTP مؤثر = `vlt_videos.enable_otp` **و** تنظیم سراسری `enable_otp` (`class-vlt-frontend.php:170`).
- تنظیمات هر ویدیو (از جمله چک‌باکس OTP) در `includes/Admin/Views/pages/videos-form.php`، پنل **Options** ذخیره می‌شود (`VLT_Videos_Admin::collect_and_sanitize`).
- `vlt-video-tracker.js` بازه‌های پخش را به `track/video-range` می‌فرستد؛ `lead_id` از کلاینت می‌آید و سرور آن را **اعتبارسنجی نمی‌کند**.

---

## طراحی UI/UX — فرم ویدیو

محل: پنل **Options** در `videos-form.php`، **درست زیر** چک‌باکس «Require SMS OTP…» (چون به آن وابسته است و کنار هم بودن، این وابستگی را بدون توضیح اضافه نشان می‌دهد).

```
[x] Require SMS OTP before showing this video
[x] ارسال پیامک حین پخش ویدیو
    ┌─────────────────────────────────────────────────────────┐
    │ زمان ارسال   [ 05:30 ]  (mm:ss — حداکثر 12:40)          │
    │ متن پیامک    [ سلام {name}، کد تخفیف شما ...         ]  │
    │              ۴۸ کاراکتر · ۱ پیامک   متغیرها: {name}     │
    │              {video_title} {site_name}                  │
    │                                         [ حذف ]        │
    │ [ + افزودن نقطه‌ی دیگر ]                                │
    └─────────────────────────────────────────────────────────┘
```

رفتار:
1. **افشای تدریجی:** با تیک چک‌باکس، بخش تنظیمات باز می‌شود (با یک ردیف پیش‌فرض). با برداشتن تیک بسته می‌شود ولی **مقادیر پاک نمی‌شوند** تا روشن‌کردن دوباره چیزی را از دست ندهد.
2. **هشدار OTP (زنده در فرم):** اگر تیک پیامک زده شده و OTP مؤثر خاموش است (تیک OTP همین فرم برداشته شده یا OTP سراسری خاموش است)، داخل بخش بازشده یک هشدار کوچک زرد (`vlt-notice--warning`، یک خط) نمایش داده می‌شود:
   «تأیید OTP برای این ویدیو فعال نیست؛ شماره‌ها تأیید نمی‌شوند و ممکن است پیامک به شماره‌ی شخص دیگری ارسال شود.»
   با زدن تیک OTP، هشدار بلافاصله (با JS) پنهان می‌شود. اگر علت، خاموش بودن OTP سراسری است، هشدار لینکی به تب OTP تنظیمات دارد. چک‌باکس پیامک هیچ‌وقت غیرفعال نمی‌شود.
3. **درگاه تنظیم‌نشده:** اگر `VLT_SMS_Gateway::is_configured()` false است، یک هشدار جداگانه با لینک به تنظیمات درگاه نشان داده می‌شود (ذخیره مجاز است؛ ارسال‌ها در لاگ `failed` می‌شوند).
4. **چند نقطه:** پیش‌فرض یک ردیف؛ دکمه‌ی «افزودن نقطه‌ی دیگر» برای کمپین‌های چندمرحله‌ای. اگر فقط یک نقطه می‌خواهید، این دکمه حذف می‌شود و مدل داده تغییری نمی‌کند.
5. **اعتبارسنجی:** زمان با فرمت `mm:ss` یا `hh:mm:ss`؛ اگر `duration_seconds` ویدیو معلوم است نباید از آن بیشتر باشد؛ متن خالی مجاز نیست؛ دو ردیف با زمان یکسان مجاز نیست. شمارنده‌ی کاراکتر/تعداد پارت (فارسی: ۷۰ کاراکتر پارت اول، ۶۷ بعدی).
6. **فهرست ویدیوها** (`videos-list.php`): کنار بج OTP یک بج «SMS» برای ویدیوهایی که این قابلیت را دارند.

---

## مدل داده (DB_VERSION → 1.5)

### ستون جدید در `vlt_videos`
```
enable_sms_triggers tinyint(1) NOT NULL DEFAULT 0
```

### `vlt_sms_triggers`
```
id, video_id, trigger_second decimal(10,3), message_template text,
is_active tinyint(1) DEFAULT 1, created_at, updated_at
KEY video_active (video_id, is_active)
```

### `vlt_sms_log` (صف + تاریخچه + جلوگیری از تکرار)
```
id, trigger_id, video_id, lead_id, normalized_mobile, session_uuid,
message text, status varchar(20)   -- queued | sent | failed | skipped
skip_reason varchar(50) NULL, error text NULL,
attempts int DEFAULT 0, queued_at, sent_at NULL
UNIQUE KEY trigger_lead (trigger_id, lead_id)
KEY status (status), KEY video_id (video_id)
```
`INSERT IGNORE` روی کلید یکتا جلوی ارسال تکراری را حتی با چند تب/سشن هم‌زمان می‌گیرد.

---

## گام‌های پیاده‌سازی

### 1. درگاه پیامک مشترک — `includes/Services/class-vlt-sms-gateway.php`
- انتقال `dispatch()` و سه `send_*` از `VLT_OTP_Service` به `VLT_SMS_Gateway::send( $mobile, $message, $context )` → `true|WP_Error`.
- `VLT_OTP_Service` از gateway استفاده کند (رفتار OTP بدون تغییر).
- `VLT_SMS_Gateway::is_configured()` برای چک UI.
- dropdown درگاه در تنظیمات: افزودن Kavenegar، SMS.ir و گزینه‌ی «بدون درگاه (فقط ثبت در لاگ — برای تست)».
- کلیدهای `otp_*` حفظ می‌شوند؛ عنوان بخش به «درگاه پیامک و OTP» تغییر می‌کند.
- ثبت کلاس در class map فایل `video-lead-tracker.php`.

### 2. اسکیما — `VLT_DB`
- ستون `enable_sms_triggers` در `vlt_videos` و دو جدول جدید در `schema()`؛ `VLT_DB_VERSION` → `1.5`.
- متدها: `get_triggers_for_video()`, `save_triggers_for_video()` (جایگزینی کامل ردیف‌ها در یک تراکنش، با حفظ `id` ردیف‌های موجود تا لاگ‌ها یتیم نشوند)، `queue_sms_log()`, `update_sms_log()`, `get_sms_log()`.
- `uninstall.php`: حذف جدول‌های جدید.

### 3. فرم ویدیو — `videos-form.php` + `VLT_Videos_Admin`
- UI بالا (HTML + JS سبک داخل همان فایل، مطابق الگوی فعلی؛ استایل در `vlt-admin.css`).
- `collect_and_sanitize()`: خواندن `enable_sms_triggers` و آرایه‌ی `sms_triggers[i][time|message]`، تبدیل `mm:ss` به ثانیه، `sanitize_textarea_field`.
- وضعیت OTP روی ذخیره اثری ندارد (فقط هشدار در UI).
- `render_form()`: پاس‌دادن تریگرها، `duration_seconds`، وضعیت OTP سراسری و `is_configured()` به view.

### 4. سرویس تریگر — `includes/Services/class-vlt-sms-trigger-service.php`
`evaluate( $video, $lead_id, $session_uuid, $from, $to )`:
1. خروج اگر: `enable_sms_triggers` ویدیو خاموش است یا `lead_id` ندارد.
2. تریگرهای فعال ویدیو که `from < trigger_second <= to` (کش کوتاه‌مدت object cache).
3. لید را بخوان؛ **فقط اگر OTP مؤثر روشن است** و `is_verified = 0` → `skipped/unverified`. با OTP خاموش، شماره‌ی تأییدنشده هم پیامک می‌گیرد.
4. سقف روزانه برای هر موبایل (تنظیم سراسری `sms_daily_cap_per_lead`، پیش‌فرض ۳) → `skipped/daily_cap`. حالا که شماره‌های تأییدنشده هم مجازند، این سقف محافظ اصلی در برابر سوءاستفاده است.
5. رندر متن: `{name}`, `{video_title}`, `{site_name}`.
6. `INSERT IGNORE` با `status=queued`؛ اگر ردیف جدید ساخته شد → `wp_schedule_single_event( time(), 'vlt_send_trigger_sms', [ $log_id ] )` + `spawn_cron()`.

هندلر `vlt_send_trigger_sms`: ارسال از طریق gateway، به‌روزرسانی `status/sent_at/error`؛ در خطا تا ۳ تلاش با فاصله‌ی ۱، ۵، ۱۵ دقیقه. (توصیه در مستندات: cron واقعی سرور چون WP-Cron وابسته به ترافیک است.)

### 5. اتصال به رهگیری — `handle_track_video_range`
- بعد از `VLT_Aggregator::aggregate(...)` → `VLT_SMS_Trigger_Service::evaluate(...)`.
- **امنیت:** برای تریگر، `lead_id` از کلاینت پذیرفته نشود؛ از `vlt_sessions` با `session_uuid` و تطبیق `visitor_uuid` خوانده شود. در غیر این صورت هر کسی با `lead_id` دلخواه می‌تواند برای شماره‌ی دیگران پیامک بفرستد.

### 6. تنظیمات سراسری — `VLT_Settings` (تب OTP / درگاه پیامک)
- `sms_daily_cap_per_lead` (پیش‌فرض ۳)
- دکمه‌ی «ارسال پیامک آزمایشی» به یک شماره (REST ادمین `admin/sms-test`).

### 7. گزارش
- صفحه‌ی «لاگ پیامک» در shell ادمین (الگوی `logs.php` + `vlt-admin-pages.js`) با فیلتر ویدیو/وضعیت؛ endpoint `admin/sms-log`.
- در فرم ویرایش ویدیو، زیر هر نقطه: تعداد ارسال‌شده / ناموفق / ردشده + لینک «مشاهده‌ی لاگ پیامک» (به‌جای Analytics Detail).

### 8. نهایی‌سازی
- رشته‌های جدید → `.pot` / `fa_IR.po` / `.mo`.
- `VLT_VERSION` → `1.5.0`، `changelog.md`.

---

## سناریوهای تست
1. OTP ویدیو خاموش + تیک پیامک → هشدار نمایش داده شود؛ تیک OTP → هشدار پنهان شود؛ ذخیره در هر دو حالت موفق باشد.
2. تریگر در ۰۰:۳۰، پخش عادی تا ۰۰:۴۵ → دقیقاً یک پیامک (حالت «بدون درگاه» → در لاگ دیده شود).
3. seek مستقیم به ۰۱:۰۰ → پیامک ارسال نشود.
4. بازپخش / دو تب / سشن دوم → بدون ارسال تکراری.
5. درخواست `track/video-range` با `lead_id` جعلی → پیامکی به آن لید نرود.
6. خطای درگاه → retry و در نهایت `failed` با متن خطا در لاگ پیامک.
7. OTP خاموش → لید تأییدنشده پیامک بگیرد؛ OTP روشن → لید تأییدنشده `skipped/unverified`.
8. دو ویدیو با تنظیمات متفاوت → هر کدام مستقل عمل کند.
