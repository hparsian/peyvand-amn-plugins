# فاز ۱۳ — مرحلهٔ ۳: ممیزی یکپارچگی داده

تاریخ: ۱۴۰۵/۰۸/۲۲ · وضعیت: **PASS** (۱ یافتهٔ Medium، ۱ آزمون پایه‌دار BLOCKED) · هیچ داده‌ای حذف نشد

## ۱. روابط داده (کوئری‌های واقعی روی Staging)

| بررسی | نتیجه |
| --- | --- |
| یادداشت بدون پرونده (orphan) | ۰ |
| نوبت بدون کاربر | ۰ |
| پاسخ آزمون بدون دعوت‌نامه | ۰ |
| شناسهٔ تکراری مراجع (`identifier_hash`) | ۰ |
| نوبت تکراری در یک اسلات | ۰ |
| توکن دعوت تکراری | ۰ |

حجم داده در زمان آزمون: `pas_cf_cases=45`, `pas_cf_notes=42`, `pas_cf_audit_log=129`,
`pas_clients=38`, `pas_client_identifiers=46`, `zdp_bookings=3`, `psy_invitations=3`,
`psy_attempts=3`, `pas_sms_log=20`, `zsm_mail_queue=3`.

## ۲. جلوگیری از تکرار

- `UNIQUE KEY identifier_hash` روی رجیستری هویت، `UNIQUE KEY conflict_key`، `UNIQUE KEY map_key/prov_key`
  روی نگاشت فیلد، `UNIQUE KEY access_token` روی دعوت‌نامه‌ها، `UNIQUE KEY user_id` روی حساب‌های پنل.
- قید اسلات نوبت عمداً از UNIQUE دیتابیسی به بررسی سطح کد منتقل شده تا پرداخت ناموفق اسلات را قفل نکند
  (`client-portal/includes/class-booking.php:608` و توضیح ضدتکرار در `:17`).
- آزمون `ecosystem-scenario` (فاز ۱۲) و `idor-roles` تکرار رکورد و رگرسیون `add_note` را پوشش می‌دهند.

## ۳. تراکنش و عملیات نیمه‌تمام

```
grep -rn "START TRANSACTION|begin_transaction|COMMIT" plugins/ → ۰ مورد
```

| شناسه | یافته | شدت | مکان | راهکار |
| --- | --- | --- | --- | --- |
| DATA-1 | نوشتن‌های چندمرحله‌ای (نوبت+پرداخت، یادداشت+تاریخچه، پرونده+ثبت هویت) بدون تراکنش دیتابیسی انجام می‌شود؛ در صورت خطای میانی، رکورد نیمه‌تمام ممکن است | **Medium** | `case-file/includes/class-case-file.php` (add_note + note_history)، `client-portal/includes/class-booking.php` | پوشش `START TRANSACTION … COMMIT/ROLLBACK` در `$wpdb` برای همان جفت نوشتن، یا ثبت جبرانی |

شواهد کاهش ریسک: (۱) هیچ orphan در DB یافت نشد؛ (۲) عملیات‌های مالی idempotent طراحی شده‌اند
(`booking_id + authority` یکتا، به‌روزرسانی وضعیت با `WHERE id AND authority`).

## ۴. رفتار هنگام خطای SMS و ایمیل

| سناریو | رفتار | حکم |
| --- | --- | --- |
| SMS ناموفق (`pas_send_sms` = false) | `Pas_Log::record(..., false, reason)` + بازگشت `false`؛ جریان اصلی متوقف نمی‌شود | ✅ |
| SMTP ناموفق | fallback زنجیره‌ای + صف `zsm_mail_queue` با `attempts` و `next_retry_at` | ✅ |
| سقف تلاش صف ایمیل | `MAX_ATTEMPTS = 5` (`smtp-mailer/pivand-amn-smtp-mailer.php:757, 936`) — حلقهٔ بی‌پایان ندارد | ✅ |
| وضعیت فعلی صف در Staging | ۳ ایمیل `pending` با attempts ۲–۳ (طبیعی: SMTP در Staging تنظیم نشده) | ✅ |
| لاگ پیامک | فقط ۲۰۰ رکورد آخر نگه داشته می‌شود (`sms-bridge/includes/class-pas-log.php:65-71`) | ✅ |

## ۵. سازگاری اسکیما با نسخه‌های قبلی (آزمون واقعی مهاجرت)

برای اجتناب از دست‌زدن به دیتابیس Staging، یک **کپی ایزوله** ساخته شد:

1. `wp_p13_mig` از دامپ پشتیبان مرحلهٔ ۰ بازسازی شد.
2. اسکیما به شکل قدیمی برگردانده شد: حذف ستون‌های `status, duration_minutes, is_joint, session_number`
   از `wp_pas_cf_notes` و تنظیم `pas_cf_db_version = 1.0.0`.
3. یک درخواست HTTP واقعی (`wp-login.php`) اجرا شد تا روتین ارتقا فعال شود.
4. نتیجه:

| بررسی | قبل | بعد |
| --- | --- | --- |
| ستون‌های `pas_cf_notes` | `id, case_id, session_date, content_encrypted, author_id, next_session_date, created_at` | + `session_number, duration_minutes, status, is_joint` |
| `pas_cf_db_version` | `1.0.0` | `1.16.0` |
| تعداد یادداشت‌ها | ۳۹ | ۳۹ (بدون تکرار، بدون حذف) |
| تعداد پرونده‌ها / لاگ دسترسی | ۴۲ / ۱۲۰ | ۴۲ / ۱۲۰ |
| سایر جدول‌ها (مراجع، شناسه، نوبت، دعوت، آزمون، پیامک، صف ایمیل) | — | بدون تغییر بیت‌به‌بیت در تعداد |
| جدول‌ها | ۳۷ | ۳۷ |

سپس `wp-config.php` به `wp_phase11` بازگردانده شد و Staging با `200` تأیید شد.
**هیچ مهاجرتی روی دیتابیس اصلی Staging اجرا نشد.**

### آزمون پایه‌دار BLOCKED

`tests/phase11/migration-verify.php` انتظار فیکسچرهای ساختگی فاز ۱۱ (`cases=3`, `notes=4`،
یادداشت با متن مشخص) را دارد که در دیتابیس فعلی وجود ندارند؛ در نتیجه ۷ بررسی وابسته به همان
فیکسچر FAIL می‌شوند، در حالی که ۸ بررسی مستقل (وجود ستون‌ها/جدول‌ها/تعداد جدول‌ها/لاگ دسترسی) PASS است.
این FAIL **رگرسیون نیست**؛ جای آن با آزمون مهاجرت ایزولهٔ بالا (بخش ۵) پوشش داده شد.
برای اجرای مجدد آن اسکریپت، باید فیکسچر فاز ۱۱ از نو ساخته شود.

## ۶. حکم مرحله

**PASS با یافتهٔ Medium** — روابط داده سالم، بدون تکرار و بدون orphan است و مسیر ارتقا
داده‌ها را حفظ می‌کند. تنها شکاف واقعی، نبود تراکنش در نوشتن‌های چندمرحله‌ای است (DATA-1)
که در گزارش نهایی به‌عنوان بدهی فنی ثبت شد.
