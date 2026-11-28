/* ── Pas Case-File Admin JS ──────────────────────────────────────────────
 * فعلاً فقط یک قابلیت دارد: موقع تایپ شماره موبایل در فرم «پرونده‌ی
 * جدید»، اگر همین شماره قبلاً در پنل مراجعان یا آزمون‌ها ثبت شده باشد،
 * نام آن (از طریق نقطه‌ی AJAX مشترک هسته — pas_core_lookup_client_name)
 * به‌عنوان پیشنهاد در فیلد «نام و نام خانوادگی» پر می‌شود — فقط وقتی آن
 * فیلد هنوز خالی است، هیچ‌وقت چیزی که خودِ درمانگر تایپ کرده را بازنویسی
 * نمی‌کند.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var phoneEl = document.getElementById('pas_cf_phone');
        var nameEl  = document.getElementById('pas_cf_full_name');

        if (!phoneEl || !nameEl || typeof PasCfClientLookup === 'undefined') {
            return;
        }

        // فقط برای فرم «پرونده‌ی جدید» معنا دارد؛ در فرم ویرایش، فیلد موبایل
        // readonly است (نگاه کن به class-admin.php) پس این رویداد اصلاً فرصت
        // اجرا شدن پیدا نمی‌کند.
        if (phoneEl.readOnly) {
            return;
        }

        var timer = null;

        phoneEl.addEventListener('input', function () {
            var phone = phoneEl.value.trim();
            clearTimeout(timer);

            if (!/^09[0-9]{9}$/.test(phone) || nameEl.value.trim() !== '') {
                return;
            }

            timer = setTimeout(function () {
                var body = new URLSearchParams();
                body.append('action', 'pas_core_lookup_client_name');
                body.append('nonce', PasCfClientLookup.nonce);
                body.append('phone', phone);

                var ajaxEndpoint = typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php';
                fetch(ajaxEndpoint, { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.success && res.data.name && nameEl.value.trim() === '') {
                            nameEl.value = res.data.name;
                            nameEl.style.background = '#fffbe6';
                            setTimeout(function () { nameEl.style.background = ''; }, 2000);
                        }
                    })
                    .catch(function () { /* بی‌صدا نادیده گرفته می‌شود — فرم دستی همیشه کار می‌کند */ });
            }, 500);
        });
    });
})();

/* ── هشدار نرم «تاریخ آینده» روی فیلدهای تاریخ جلسه ───────────────────────
 * فقط هشدار بصری است، فرم را بلاک نمی‌کند — گاهی ثبت جلسه‌ی از‌قبل‌برنامه‌ریزی‌شده
 * (نوبت آینده) هم کاربرد دارد، پس نباید جلوی ارسال را گرفت، فقط دیده شود.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof PasCfToday === 'undefined' || !PasCfToday.jalali) {
            return;
        }

        var persianDigits = '۰۱۲۳۴۵۶۷۸۹';
        var arabicDigits  = '٠١٢٣٤٥٦٧٨٩';
        function toEnglishDigits(value) {
            return value.replace(/[۰-۹٠-٩]/g, function (ch) {
                var i = persianDigits.indexOf(ch);
                if (i === -1) { i = arabicDigits.indexOf(ch); }
                return i === -1 ? ch : String(i);
            });
        }

        document.querySelectorAll('.pas-cf-jalali-input').forEach(function (input) {
            var warning = input.closest('p');
            warning = warning ? warning.querySelector('.pas-cf-future-date-warning') : null;
            if (!warning) {
                return;
            }

            var check = function () {
                // فرمت شمسی همیشه Y/m/d با صفر ابتدایی است، پس مقایسه‌ی
                // رشته‌ای همان مقایسه‌ی زمانی را می‌دهد — نیازی به تبدیل
                // به عدد ژولین یا هر محاسبه‌ی دیگری در جاوااسکریپت نیست.
                var value = toEnglishDigits(input.value.trim());
                warning.hidden = !(value && value > PasCfToday.jalali);
            };

            input.addEventListener('input', check);
            check();
        });
    });
})();
