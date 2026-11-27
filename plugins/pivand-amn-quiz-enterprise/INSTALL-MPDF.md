# نصب mPDF برای قابلیت PDF

پلاگین از کتابخانه **mPDF** برای تولید فایل PDF استفاده می‌کند.
این کتابخانه Pure PHP است و نیاز به نصب چیزی روی سرور ندارد.

---

## روش ۱ — Composer (توصیه‌شده)

در پوشه پلاگین (`wp-content/plugins/pivand-amn-quiz-enterprise/`) اجرا کنید:

```bash
composer require mpdf/mpdf
```

اگر Composer ندارید:
```bash
curl -sS https://getcomposer.org/installer | php
php composer.phar require mpdf/mpdf
```

---

## روش ۲ — دانلود دستی (بدون Composer)

1. از آدرس زیر آخرین نسخه mPDF را دانلود کنید:
   https://github.com/mpdf/mpdf/releases/latest

2. فایل `mpdf-x.x.x.zip` را extract کنید

3. پوشه extract‌شده را به این مسیر منتقل کنید:
   ```
   wp-content/plugins/pivand-amn-quiz-enterprise/libs/mpdf/
   ```
   به گونه‌ای که این مسیر وجود داشته باشد:
   ```
   libs/mpdf/vendor/autoload.php
   ```

---

## بعد از نصب

در پنل ادمین وردپرس به مسیر زیر بروید و پلاگین را **Deactivate** و دوباره **Activate** کنید:

**افزونه‌ها → آزمون‌های روانشناسی**

یا از طریق SSH:
```bash
wp plugin deactivate pivand-amn-quiz-enterprise
wp plugin activate pivand-amn-quiz-enterprise
```

بعد از activate، خطای PDF باید ناپدید شود.

---

## نکته برای shared hosting ایرانی

اگر Composer اجرا نمی‌شود، روش دستی (روش ۲) را استفاده کنید.
مطمئن شوید پوشه `libs/` توسط وب‌سرور قابل خواندن است (`chmod 755`).
