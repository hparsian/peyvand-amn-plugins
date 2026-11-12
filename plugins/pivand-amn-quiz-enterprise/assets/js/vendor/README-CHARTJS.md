# Chart.js — سلف‌هاست اختیاری

من (Claude) به اینترنت دسترسی نداشتم که فایل باینری Chart.js رو دانلود کنم.
الان کد طوری نوشته شده که:

- اگه فایل `chart.umd.min.js` همین‌جا (کنار همین README) باشه → از همین
  نسخه‌ی local لود می‌شه.
- اگه نباشه → خودکار به همون CDN قبلی (`cdnjs.cloudflare.com`) fallback
  می‌کنه. یعنی چیزی خراب نمی‌شه، فقط لود از local غیرفعاله تا فایل رو
  اضافه کنی.

## از کجا بگیرم؟

نسخه‌ی دقیقی که همین الان استفاده می‌شه (۴.۴.۱) رو از اینجا دانلود کن:
https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js

فایل رو با همین اسم (`chart.umd.min.js`) کنار همین README بذار:
`pivand-amn-quiz-enterprise/assets/js/vendor/chart.umd.min.js`

## بعدش چی؟

هیچی — `Psy_Quiz_Assets::register_chart_js()` خودکار وجود فایل رو چک
می‌کنه (`file_exists`) و مسیر مناسب رو انتخاب می‌کنه. نیازی به تغییر کد
نیست.
