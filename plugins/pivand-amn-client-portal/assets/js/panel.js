(function () {
    'use strict';

    // ارقام فارسی/عربی را به لاتین تبدیل می‌کند — کیبورد فارسی (رایج روی
    // موبایل‌های ایرانی) پیش‌فرض همین ارقام را تایپ می‌کند، و \d در regex
    // جاوااسکریپت فقط ارقام لاتین را می‌شناسد.
    function toEnglishDigits(str) {
        return String(str || '')
            .replace(/[۰-۹]/g, function (d) { return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)); })
            .replace(/[٠-٩]/g, function (d) { return String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)); });
    }

    // شماره‌ی ایرانی (09xxxxxxxxx) یا شماره‌ی خارجی با پیشوند صریح + یا 00
    // و کد کشور (فرمت E.164: + یا 00 سپس ۸ تا ۱۵ رقم). سمت سرور
    // (to_local_format در functions-helpers.php) هر دو پیشوند + و 00 را
    // یکسان می‌پذیرد؛ این regex باید همان رفتار را داشته باشد، وگرنه
    // کاربری که با 00 شروع می‌کند همین‌جا (قبل از رسیدن به سرور) رد
    // می‌شود. اعتبارسنجی نهایی/قطعی همیشه سمت سرور (is_valid_mobile)
    // انجام می‌شود؛ این فقط برای فیدبک فوری به کاربر است.
    function isValidMobileInput(value) {
        return /^09\d{9}$/.test(value)
            || /^\+[1-9]\d{7,14}$/.test(value)
            || /^00[1-9]\d{7,14}$/.test(value);
    }

    function post(action, data) {
        var body = new FormData();
        body.append('action', action);
        body.append('nonce', window.ZDP && window.ZDP.nonce ? window.ZDP.nonce : '');
        Object.keys(data || {}).forEach(function (key) {
            body.append(key, data[key]);
        });

        var ajaxUrl = (window.ZDP && window.ZDP.ajax_url) || '/wp-admin/admin-ajax.php';

        // نکته‌ی حیاتی: همیشه .catch() دارد. در نسخه‌ی قبلی نبودنِ .catch()
        // باعث می‌شد اگر پاسخ سرور JSON معتبر نبود، دکمه برای همیشه روی
        // «در حال بررسی...» بماند و کاربر هیچ پیام خطایی نبیند.
        return fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) {
                return res.json();
            })
            .catch(function (err) {
                console.error('ZDP request failed:', err);
                return { success: false, data: { message: t('network_error', 'خطا در ارتباط با سرور.') } };
            });
    }

    // نکته: فقط OTP (send/verify) از این تابع استفاده می‌کند؛ بقیه‌ی فرم‌ها
    // (رزرو، پیام، نظرسنجی، کوییز) همچنان از post() روی admin-ajax.php
    // استفاده می‌کنند — چیزی در آن‌ها تغییر نکرده است.
    // ساخت آدرس REST مستقل از ساختار پیوند یکتا.
    // روی سایت‌هایی که «پیوند یکتای ساده» دارند، rest_url() خروجی
    // «https://site/?rest_route=/zdp/v1/» می‌دهد؛ چسباندن مستقیم endpoint
    // یک علامت «?» دوم می‌سازد، وردپرس مسیر را نمی‌شناسد و خطای
    // «No route was found matching the URL and request method» برمی‌گردد.
    // اینجا مسیر و پارامترهای query جدا و درست ترکیب می‌شوند.
    function restUrl(endpoint) {
        var base = (window.ZDP && window.ZDP.rest_url) || '/wp-json/zdp/v1/';

        if (base.indexOf('rest_route=') !== -1) {
            var sep = base.charAt(base.length - 1) === '/' ? '' : '/';
            var qsAt = endpoint.indexOf('?');
            var path = qsAt === -1 ? endpoint : endpoint.slice(0, qsAt);
            var extra = qsAt === -1 ? '' : endpoint.slice(qsAt + 1);

            return base + sep + path + (extra ? '&' + extra : '');
        }

        return base + endpoint;
    }

    function postRest(endpoint, data) {
        var url = restUrl(endpoint);

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': (window.ZDP && window.ZDP.rest_nonce) || '',
            },
            body: JSON.stringify(data || {}),
        })
            .then(function (res) {
                return res.json().then(function (json) {
                    // پاسخ موفق/ناموفق ما همیشه شکل {success, data:{message,...}}
                    // دارد (همان شکل wp_send_json_success/error قدیمی). اگر
                    // permission_callback رد شود، وردپرس یک شکل متفاوت
                    // ({code, message, data:{status}}) برمی‌گرداند که اینجا
                    // به همان شکل استاندارد نگاشت می‌شود تا بقیه‌ی کد (که
                    // res.success و res.data.message را می‌خواند) دست‌نخورده بماند.
                    if (json && typeof json.success !== 'undefined') {
                        return json;
                    }
                    return {
                        success: false,
                        data: { message: (json && json.message) || t('network_error', 'خطا در ارتباط با سرور.') },
                    };
                });
            })
            .catch(function (err) {
                console.error('ZDP REST request failed:', err);
                return { success: false, data: { message: t('network_error', 'خطا در ارتباط با سرور.') } };
            });
    }

    // دکمه‌های اصلی (ارسال/تایید OTP) اکنون داخلشان یک اسپن اسپینر دارند
    // (طراحی جدید panel.css). قبلاً این کد مستقیم button.textContent را عوض
    // می‌کرد که هر children (از جمله اسپینر) را پاک می‌کرد. این تابع کمکی
    // اگر .zdp-btn-label داخل دکمه باشد همان را برمی‌گرداند (اسپینر دست‌نخورده
    // می‌ماند)، وگرنه خودِ دکمه را برمی‌گرداند — یعنی برای دکمه‌های قدیمی‌تر
    // بدون این ساختار هم دقیقاً همان رفتار قبلی حفظ می‌شود.
    function btnLabel(btn) {
        return (btn && btn.querySelector && btn.querySelector('.zdp-btn-label')) || btn;
    }

    function setMessage(el, text, isError) {
        if (!el) return;
        el.textContent = text || '';
        el.classList.toggle('zdp-error', !!isError);
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function t(key, fallback) {
        return (window.ZDP && window.ZDP.i18n && window.ZDP.i18n[key]) || fallback;
    }

    // درخواست POST به REST (برای panel-state؛ همان قرارداد پاسخ postRest).
    // عمداً POST است نه GET (نگاه کن به کامنت بالای register_rest_route در
    // class-client-panel.php): چون خیلی از CDN/ریورس‌پروکسی‌ها به‌طور
    // پیش‌فرض فقط GET را کش می‌کنند، این انتخاب یک لایه‌ی دفاعیِ کامل و
    // مستقل از هرگونه تنظیم درست/غلط CDN اضافه می‌کند. cache:'no-store' هم
    // برای جلوگیری از کش شدن توسط خودِ مرورگر/service worker نگه داشته شده.
    // پچ محلی: برگشت به GET — دقیقاً مثل نسخه‌ی ۱.۵.۶. سایت پشت هیچ CDN‌ای
    // نیست (تأیید شد)، پس فرض «کش پیش‌فرض CDN روی GET» اینجا اصلاً مصداق
    // نداشت؛ در عوض به نظر می‌رسد یک لایه‌ی امنیتی سطح هاست دقیقاً به
    // درخواست‌های POST به wp-json حساس‌تر برخورد می‌کند. cache:'no-store'
    // برای جلوگیری از کش شدن توسط خودِ مرورگر همچنان نگه داشته شده.
    // رفع باگ کشف‌شده (۱.۹.۸.۰): جدول رزرو در صفحه‌ی اصلی گاهی برای همیشه
    // روی اسکلتون لودینگ می‌ماند. fetch() به‌خودی‌خود هیچ timeout ندارد؛
    // اگر پاسخ سرور هرگز نرسد (نه خطا، نه موفقیت — مثلاً یک لایه‌ی امنیتی
    // سطح هاست درخواست را بی‌صدا drop کند)، پرامیس تا ابد pending می‌ماند
    // و کد قبلی هیچ راهی برای تشخیصش نداشت. اینجا با AbortController یک
    // سقف زمانی ۱۲ ثانیه‌ای اضافه شده: اگر تا آن موقع پاسخی نرسد، درخواست
    // abort می‌شود و به‌جای اسکلتونِ ابدی، همان جعبه‌ی خطا + دکمه‌ی «تلاش
    // مجدد» نمایش داده می‌شود.
    function getRest(endpoint) {
        var url = restUrl(endpoint);

        var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var timeoutId = controller ? setTimeout(function () { controller.abort(); }, 12000) : null;

        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-WP-Nonce': (window.ZDP && window.ZDP.rest_nonce) || '' },
            signal: controller ? controller.signal : undefined,
        })
            .then(function (res) {
                if (timeoutId) clearTimeout(timeoutId);
                return res.json().then(function (json) {
                    if (json && typeof json.success !== 'undefined') {
                        return json;
                    }
                    return {
                        success: false,
                        data: { message: (json && json.message) || t('network_error', 'خطا در ارتباط با سرور.') },
                    };
                });
            })
            .catch(function (err) {
                if (timeoutId) clearTimeout(timeoutId);
                console.error('ZDP panel-state request failed:', err);
                var isTimeout = err && err.name === 'AbortError';
                return {
                    success: false,
                    data: { message: isTimeout ? 'سرور به‌موقع پاسخ نداد. لطفاً دوباره تلاش کنید.' : t('network_error', 'خطا در ارتباط با سرور.') },
                };
            });
    }

    // ---------------------------------------------------------------
    // پرکردنِ قالبِ خالیِ پنل (zdp-panel-app) با محتوای واقعیِ کاربر.
    // نکته‌ی حیاتی امنیتی: HTML چاپ‌شده توسط PHP برای شورت‌کد [zdp_client_panel]
    // دیگر هرگز به لاگین‌بودن کاربر وابسته نیست (همان قالب خالی برای همه است،
    // پس کش‌شدنش توسط لایه‌ی کش (WP Rocket/LiteSpeed Cache) بی‌خطر است). فرم ورود یا محتوای واقعی پنل فقط
    // از اینجا — بعد از یک درخواست REST واقعی با کوکی‌های زنده‌ی مرورگر —
    // تزریق می‌شود.
    // ---------------------------------------------------------------
    function loadPanelApp() {
        var app = document.getElementById('zdp-panel-app');
        if (!app) return;

        getRest('panel-state').then(function (res) {
            if (!res.success) {
                app.classList.remove('zdp-panel-loading');
                app.innerHTML = '<p class="zdp-error" role="alert">' + escapeHtml(res.data.message) + '</p>'
                    + '<button type="button" id="zdp-panel-retry-btn">تلاش دوباره</button>';
                var retryBtn = document.getElementById('zdp-panel-retry-btn');
                if (retryBtn) {
                    retryBtn.addEventListener('click', function () {
                        app.classList.add('zdp-panel-loading');
                        app.innerHTML = '<p class="zdp-panel-loading-text">در حال بارگذاری...</p>';
                        loadPanelApp();
                    });
                }
                return;
            }

            app.classList.remove('zdp-panel-loading');
            app.setAttribute('data-loaded', '1');
            app.innerHTML = res.data.html;

            // چون این عناصر همین الان تزریق شدند، رویدادها باید بعد از
            // تزریق وصل شوند؛ initLoginForm/initTabs/... خودشان اگر عنصر
            // موردنظرشان در DOM نباشد بی‌اثر برمی‌گردند، پس فراخوانی هر دو
            // حالت (فرم ورود / خودِ پنل) اینجا امن است.
            initLoginForm();
            initTabs();
            initBookingForm();
            initJalaliDatePicker();
            initMessageForm();
            initSurveyForm();
            initIntakeForm();
            initReceiptUpload();

            // رفع باگ کشف‌شده: initBookingWidget() قبلاً فقط یک‌بار روی
            // DOMContentLoaded صدا زده می‌شد (پایین همین فایل) — که برای
            // شورت‌کد عمومی [zdp_booking_widget] کافی بود چون آن HTML مستقیم
            // در بارگذاری اول صفحه وجود دارد. ولی از وقتی همین جدول گرید
            // داخل تب «نوبت‌ها»ی خودِ پنل هم استفاده می‌شود (نگاه کن به
            // Client_Panel::render_appointments_tab)، آن HTML فقط بعد از
            // موفقیت همین AJAX (که app.innerHTML را همین چند خط بالاتر پر
            // می‌کند) در DOM قرار می‌گیرد — یعنی فراخوانیِ اولیه‌ی
            // initBookingWidget() روی DOMContentLoaded همیشه زودتر از این
            // لحظه اجرا می‌شد و .zdp-booking-widget را پیدا نمی‌کرد (بی‌صدا
            // خارج می‌شد)، پس جدول داخل پنل هیچ‌وقت واقعاً wire نمی‌شد. باید
            // اینجا هم دوباره صدا زده شود.
            initBookingWidget();
        });
    }

    // پچ محلی: کل مکانیزم bootstrap-nonce/refreshNonces حذف شد. این مکانیزم
    // اصلاً در نسخه‌ی ۱.۵.۶ (سالم) وجود نداشت و فقط برای یک سناریوی فرضیِ
    // «کش CDN صفحه‌ی خالی را بین کاربران مشترک می‌کند» اضافه شده بود —
    // سناریویی که روی این سایت مصداق ندارد (تأیید شد سایت پشت هیچ CDN‌ای
    // نیست). این مکانیزم یک درخواست POST اضافه‌تر به wp-json بود که فقط
    // ریسک بیشتری برای مسدود شدن توسط لایه‌ی امنیتی هاست ایجاد می‌کرد، بدون
    // فایده‌ی واقعی برای این سایت.
    document.addEventListener('DOMContentLoaded', function () {
        loadPanelApp();
        initQuizFunnel();

        // رفع باگ: initSurveyForm() قبلاً فقط داخل callback موفقِ
        // loadPanelApp() صدا زده می‌شد، یعنی فقط وقتی [zdp_client_panel]
        // در صفحه بود. در صفحه‌ی مستقل [zdp_survey_page] فرم مستقیماً در
        // HTML اولیه چاپ می‌شود و هرگز از مسیر REST پنل رد نمی‌شود، پس
        // دکمه‌ی ارسال هیچ رویدادی نداشت و کلیک روی آن کاملاً بی‌اثر بود.
        // خودِ initSurveyForm() اگر فرم را در DOM پیدا نکند بی‌خطر
        // برمی‌گردد، پس فراخوانی مستقیم اینجا برای هر دو حالت امن است.
        initSurveyForm();
        initBookingWidget();
    });

    // دکمه‌ی بنر «تکمیل فرم» (data-goto-tab) — تب مربوط را باز می‌کند؛ یک‌بار و با delegation.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-goto-tab]') : null;
        if (!btn) return;
        var tab = document.querySelector('.zdp-tab[data-tab="' + btn.getAttribute('data-goto-tab') + '"]');
        if (tab) tab.click();
    });

    // ---------------------------------------------------------------
    // ورود پیامکی
    // ---------------------------------------------------------------
    function initLoginForm() {
        var sendBtn = document.getElementById('zdp-send-otp-btn');
        var sendEmailBtn = document.getElementById('zdp-send-otp-email-btn');
        var verifyBtn = document.getElementById('zdp-verify-otp-btn');
        var resendBtn = document.getElementById('zdp-resend-otp-btn');
        var changeBtn = document.getElementById('zdp-change-mobile-btn');
        var msgEl = document.getElementById('zdp-login-message');
        if (!sendBtn || !verifyBtn) return;

        var mobileInput = document.getElementById('zdp-mobile');
        var emailInput = document.getElementById('zdp-email');
        var otpInput = document.getElementById('zdp-otp');
        var otpStep = document.getElementById('zdp-otp-step');
        var mobileStep = document.getElementById('zdp-mobile-step');
        var emailStep = document.getElementById('zdp-email-step');
        var timerEl = document.getElementById('zdp-otp-timer');
        var supportEl = document.getElementById('zdp-otp-support');
        var stepsEl = document.querySelector('.zdp-login-steps');
        var otpBoxes = Array.prototype.slice.call(document.querySelectorAll('.zdp-otp-box'));

        // باگ: کیبورد فارسی (پیش‌فرض روی اکثر گوشی‌های ایرانی) رقم فارسی/عربی
        // تایپ می‌کند، اما isValidMobileInput فقط ارقام لاتین را قبول دارد؛
        // بدون این تبدیل، چنین کاربری همیشه پیام «شماره معتبر نیست» می‌گرفت
        // حتی با شماره‌ی کاملاً درست — درحالی‌که سمت سرور (to_local_format)
        // از قبل این تبدیل را انجام می‌داد، پس ناهماهنگی فقط سمت کلاینت بود.
        if (mobileInput) {
            mobileInput.addEventListener('input', function () {
                var converted = toEnglishDigits(mobileInput.value);
                if (converted !== mobileInput.value) {
                    var pos = mobileInput.selectionStart;
                    mobileInput.value = converted;
                    if (pos !== null) mobileInput.setSelectionRange(pos, pos);
                }
            });
        }

        // ---------------------------------------------------------------
        // تب‌های کانال ورود (موبایل/ایمیل) — فقط قبل از ارسال کد قابل تعویضند؛
        // بعد از رفتن به مرحله‌ی کد، کاربر باید از «تغییر» برای برگشتن استفاده کند.
        // ---------------------------------------------------------------
        var activeChannel = 'mobile'; // 'mobile' | 'email'
        var channelMobileBtn = document.getElementById('zdp-channel-mobile-btn');
        var channelEmailBtn = document.getElementById('zdp-channel-email-btn');
        var channelTabsEl = document.querySelector('.zdp-login-channel-tabs');

        function setChannel(channel) {
            activeChannel = channel;
            if (channelMobileBtn) {
                channelMobileBtn.classList.toggle('active', channel === 'mobile');
                channelMobileBtn.setAttribute('aria-selected', channel === 'mobile' ? 'true' : 'false');
            }
            if (channelEmailBtn) {
                channelEmailBtn.classList.toggle('active', channel === 'email');
                channelEmailBtn.setAttribute('aria-selected', channel === 'email' ? 'true' : 'false');
            }
            if (mobileStep) mobileStep.style.display = channel === 'mobile' ? 'block' : 'none';
            if (emailStep) emailStep.style.display = channel === 'email' ? 'block' : 'none';
            setMessage(msgEl, '', false);
        }

        if (channelMobileBtn) {
            channelMobileBtn.addEventListener('click', function () { setChannel('mobile'); });
        }
        if (channelEmailBtn) {
            channelEmailBtn.addEventListener('click', function () { setChannel('email'); });
        }

        // ── نوبتِ در انتظار (اگر مراجع از ویجت عمومی نوبت‌دهی این‌جا
        // هدایت شده) ── طبق تغییر رفتار درخواستی: مراجعِ مهمان دیگر موبایل/
        // کد را داخل شورت‌کد وارد نمی‌کند؛ خدمت+تاریخ+ساعت+نام از querystring
        // خوانده می‌شود و همراه verify-otp همین فرم استاندارد پنل به سرور
        // می‌رود تا همان فیلتر موجود (zdp_otp_login_pending_booking) بعد از
        // ورود موفق، نوبت را خودکار ثبت کند.
        var pendingBooking = (function () {
            var params = new URLSearchParams(window.location.search);
            var serviceId = params.get('zdp_service_id');
            if (!serviceId) return null;
            return {
                service_id: serviceId,
                booking_date: params.get('zdp_booking_date') || '',
                booking_time: params.get('zdp_booking_time') || '',
                full_name: params.get('zdp_full_name') || '',
            };
        })();

        if (pendingBooking && msgEl) {
            // یک بنر راهنما بالای فرم ورود، تا مراجع گیج نشود که چرا به این
            // صفحه آمده — فقط توضیحی؛ روی منطق ثبت نوبت اثری ندارد.
            var banner = document.createElement('p');
            banner.className = 'zdp-login-pending-booking-note';
            banner.textContent = 'برای تکمیل ثبت نوبتِ انتخابی، لطفاً با موبایل یا ایمیل خود وارد شوید.';
            msgEl.parentNode.insertBefore(banner, msgEl);
        }

        // باکس‌های جدا فقط برای تجربه‌ی تایپ بهترند؛ مقدار واقعی همیشه در
        // #zdp-otp (input مخفی) جمع می‌شود تا بقیه‌ی منطق (requestOtp/verify)
        // بدون تغییر با همان یک مقدار رشته‌ای کار کند.
        function syncOtpValue() {
            otpInput.value = otpBoxes.map(function (b) { return b.value; }).join('');
        }

        function clearOtpBoxes() {
            otpBoxes.forEach(function (b) { b.value = ''; });
            syncOtpValue();
        }

        function focusFirstOtpBox() {
            if (otpBoxes[0]) otpBoxes[0].focus();
        }

        otpBoxes.forEach(function (box, idx) {
            box.addEventListener('input', function () {
                var cleaned = toEnglishDigits(box.value.replace(/[^0-9۰-۹٠-٩]/g, ''));

                // موبایل سافاری (iOS) پیشنهاد پرشدن خودکار کد پیامک را —
                // برخلاف WebOTP اندروید که بالاتر جدا مدیریت شده — با ست
                // کردن کل رشته‌ی ۵ رقمی روی همان یک فیلدِ دارای
                // autocomplete="one-time-code" (باکس اول) انجام می‌دهد، نه
                // با یک رویداد paste جدا. اگر بیش از یک رقم در این باکس
                // نشست، یعنی autofill بوده: کل رشته را بین باکس‌ها پخش کن،
                // نه فقط رقم آخر.
                if (cleaned.length > 1) {
                    otpBoxes.forEach(function (b, i) { b.value = cleaned[i] || ''; });
                    syncOtpValue();
                    var next = otpBoxes[Math.min(cleaned.length, otpBoxes.length - 1)];
                    if (next) next.focus();
                    if (otpBoxes.every(function (b) { return b.value !== ''; })) {
                        doVerify();
                    }
                    return;
                }

                // ارقام فارسی/عربی هم مثل بقیه‌ی فرم‌های این اکوسیستم قبول شوند
                // (قبلاً فقط رنج فارسی این‌جا تبدیل می‌شد؛ تایپ دستی رقم عربی
                // بی‌صدا حذف می‌شد، هرچند همان رقم موقع Paste درست کار می‌کرد —
                // نگاه کن به هندلر paste پایین‌تر که هر دو رنج را از قبل داشت).
                box.value = cleaned.slice(-1);
                syncOtpValue();
                if (box.value && otpBoxes[idx + 1]) {
                    otpBoxes[idx + 1].focus();
                }
                // درخواست کارفرما: بعد از پر شدن آخرین باکس، خودکار تایید
                // شود — مراجع دیگر نیازی به زدن دستیِ دکمه‌ی «ورود» ندارد.
                if (otpBoxes.every(function (b) { return b.value !== ''; })) {
                    doVerify();
                }
            });
            box.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !box.value && otpBoxes[idx - 1]) {
                    otpBoxes[idx - 1].focus();
                }
            });
            box.addEventListener('paste', function (e) {
                var raw = (e.clipboardData || window.clipboardData).getData('text');
                // ارقام فارسی/عربی را هم مثل هندلر تایپ دستی بالا به لاتین
                // تبدیل کن، نه اینکه با \D حذفشان کنی — وگرنه پیست‌کردن کدی
                // که پیامک آن را با ارقام فارسی فرستاده (رایج در گیت‌وی‌های
                // ایرانی) باعث خالی‌ماندن کامل باکس‌ها می‌شد.
                var converted = toEnglishDigits(raw);
                var pasted = converted.replace(/\D/g, '');
                if (!pasted) return;
                e.preventDefault();
                otpBoxes.forEach(function (b, i) { b.value = pasted[i] || ''; });
                syncOtpValue();
                var next = otpBoxes[Math.min(pasted.length, otpBoxes.length - 1)];
                if (next) next.focus();
                if (otpBoxes.every(function (b) { return b.value !== ''; })) {
                    doVerify();
                }
            });
        });

        // ---------------------------------------------------------------
        // پرشدن خودکار کد از پیامک (WebOTP API) — فقط برای کانال موبایل
        // معنا دارد (کد ایمیل از پیامک نمی‌آید). فقط در مرورگرهایی که این
        // API را دارند (عمدتاً کروم/اندروید) فعال می‌شود؛ در بقیه‌ی
        // مرورگرها کاملاً بی‌صدا نادیده گرفته می‌شود و مراجع طبق روال قبل
        // دستی تایپ می‌کند — هیچ افتی برای کسی که این API را ندارد.
        // نکته‌ی امنیتی/فنی: این API فقط زیر HTTPS کار می‌کند و پیامک باید
        // طبق قرارداد استاندارد (خط آخر پیام: «@دامنه‌ی سایت #کد») ساخته
        // شده باشد؛ اگر گیت‌وی پیامک این قالب را نفرستد، مرورگر فقط بی‌صدا
        // منتظر می‌ماند و timeout می‌شود — تایپ دستی مراجع هیچ‌وقت مسدود
        // نمی‌شود.
        var webOtpAbort = null;

        function stopWebOtp() {
            if (webOtpAbort) {
                webOtpAbort.abort();
                webOtpAbort = null;
            }
        }

        function startWebOtp() {
            if (activeChannel !== 'mobile') return;
            if (!('OTPCredential' in window) || typeof navigator.credentials === 'undefined') return;

            stopWebOtp();
            webOtpAbort = new AbortController();

            navigator.credentials.get({
                otp: { transport: ['sms'] },
                signal: webOtpAbort.signal
            }).then(function (content) {
                if (!content || !content.code) return;
                var digits = toEnglishDigits(String(content.code)).replace(/\D/g, '');
                if (!digits) return;
                otpBoxes.forEach(function (b, i) { b.value = digits[i] || ''; });
                syncOtpValue();
                if (otpBoxes.every(function (b) { return b.value !== ''; })) {
                    doVerify();
                }
            }).catch(function () {
                // لغوشده (کاربر مرحله را عوض کرد) یا timeout یا رد دسترسی —
                // در همه‌ی این حالت‌ها بی‌صدا نادیده گرفته می‌شود، تایپ دستی
                // همچنان کار می‌کند.
            });
        }

        var RESEND_SECONDS = 120;
        var countdownHandle = null;

        function stopCountdown() {
            if (countdownHandle) {
                clearInterval(countdownHandle);
                countdownHandle = null;
            }
        }

        function formatTime(sec) {
            var m = Math.floor(sec / 60);
            var s = sec % 60;
            return m + ':' + (s < 10 ? '0' : '') + s;
        }

        function startCountdown() {
            stopCountdown();
            var remaining = RESEND_SECONDS;
            resendBtn.style.display = 'none';
            timerEl.style.display = '';
            timerEl.textContent = 'ارسال مجدد کد تا ' + formatTime(remaining);
            if (supportEl) supportEl.hidden = true;

            countdownHandle = setInterval(function () {
                remaining -= 1;
                if (remaining <= 0) {
                    stopCountdown();
                    timerEl.style.display = 'none';
                    resendBtn.style.display = '';
                    if (supportEl) supportEl.hidden = false;
                    return;
                }
                timerEl.textContent = 'ارسال مجدد کد تا ' + formatTime(remaining);
            }, 1000);
        }

        function requestOtp(onDone) {
            var endpoint, payload, activeStep;

            if (activeChannel === 'email') {
                var email = (emailInput.value || '').trim();
                if (!/^\S+@\S+\.\S+$/.test(email)) {
                    setMessage(msgEl, 'ایمیل معتبر وارد کنید.', true);
                    if (onDone) onDone(false);
                    return;
                }
                endpoint = 'send-otp-email';
                payload = { email: email };
                activeStep = emailStep;
            } else {
                var mobile = toEnglishDigits((mobileInput.value || '').trim());
                if (!isValidMobileInput(mobile)) {
                    setMessage(msgEl, 'شماره موبایل معتبر وارد کنید.', true);
                    if (onDone) onDone(false);
                    return;
                }
                // شماره‌ی خارجی: پیامک پشتیبانی نمی‌شود؛ بدون درخواست به سرور،
                // مستقیم به ورود با ایمیل هدایت می‌شود.
                if (/^(\+|00)/.test(mobile) && emailStep) {
                    setChannel('email');
                    setMessage(msgEl, 'ارسال پیامک برای شماره‌ی خارج از کشور پشتیبانی نمی‌شود. لطفاً با ایمیل وارد شوید.', true);
                    if (emailInput) emailInput.focus();
                    if (onDone) onDone(false);
                    return;
                }
                endpoint = 'send-otp';
                payload = { mobile: mobile };
                activeStep = mobileStep;
            }

            postRest(endpoint, payload).then(function (res) {
                if (res.success) {
                    setMessage(msgEl, res.data.message, false);
                    activeStep.style.display = 'none';
                    otpStep.style.display = 'block';
                    if (channelTabsEl) channelTabsEl.style.display = 'none';
                    if (stepsEl) stepsEl.setAttribute('data-step', 'otp');
                    clearOtpBoxes();
                    focusFirstOtpBox();
                    startCountdown();
                    startWebOtp();
                } else {
                    if (res.data && res.data.switch_channel === 'email' && emailStep) {
                        setChannel('email');
                    }
                    setMessage(msgEl, res.data.message, true);
                }
                if (onDone) onDone(res.success);
            });
        }

        sendBtn.addEventListener('click', function () {
            sendBtn.disabled = true;
            var sendLabel = btnLabel(sendBtn);
            var originalText = sendLabel.textContent;
            sendLabel.textContent = t('sending', 'در حال ارسال...');

            requestOtp(function () {
                sendBtn.disabled = false;
                sendLabel.textContent = originalText;
            });
        });

        if (sendEmailBtn) {
            sendEmailBtn.addEventListener('click', function () {
                sendEmailBtn.disabled = true;
                var sendEmailLabel = btnLabel(sendEmailBtn);
                var originalText = sendEmailLabel.textContent;
                sendEmailLabel.textContent = t('sending', 'در حال ارسال...');

                requestOtp(function () {
                    sendEmailBtn.disabled = false;
                    sendEmailLabel.textContent = originalText;
                });
            });
        }

        if (resendBtn) {
            resendBtn.addEventListener('click', function () {
                resendBtn.disabled = true;
                var resendLabel = btnLabel(resendBtn);
                var originalText = resendLabel.textContent;
                resendLabel.textContent = t('sending', 'در حال ارسال...');

                requestOtp(function () {
                    resendBtn.disabled = false;
                    resendLabel.textContent = originalText;
                });
            });
        }

        if (changeBtn) {
            changeBtn.addEventListener('click', function () {
                stopCountdown();
                stopWebOtp();
                otpStep.style.display = 'none';
                if (channelTabsEl) channelTabsEl.style.display = '';
                if (activeChannel === 'email') {
                    emailStep.style.display = 'block';
                    if (stepsEl) stepsEl.setAttribute('data-step', 'email');
                    emailInput.focus();
                } else {
                    mobileStep.style.display = 'block';
                    if (stepsEl) stepsEl.setAttribute('data-step', 'mobile');
                    mobileInput.focus();
                }
                var changeLabelEl = document.getElementById('zdp-change-label');
                if (changeLabelEl) {
                    changeLabelEl.textContent = activeChannel === 'email' ? 'تغییر ایمیل' : 'تغییر شماره';
                }
                clearOtpBoxes();
                setMessage(msgEl, '', false);
            });
        }

        // مقدار «در حال بررسی» فقط یک‌بار زمان‌بندی می‌شود تا هم کلیک دستی
        // روی دکمه‌ی «ورود» و هم تکمیل خودکار (پر شدن هر ۵ باکس، یا
        // پرشدن خودکار از پیامک از طریق WebOTP پایین‌تر) از همین یک مسیر
        // رد شوند و رفتار/پیام‌های خطا یکسان بماند.
        var verifyInFlight = false;

        function doVerify() {
            if (verifyInFlight) return;

            var otp = (otpInput.value || '').trim();
            if (!/^\d{5}$/.test(otp)) {
                setMessage(msgEl, 'کد ۵ رقمی را کامل وارد کنید.', true);
                return;
            }

            verifyInFlight = true;
            verifyBtn.disabled = true;
            var verifyLabel = btnLabel(verifyBtn);
            var originalText = verifyLabel.textContent;
            verifyLabel.textContent = t('checking', 'در حال بررسی...');

            var endpoint, payload;
            if (activeChannel === 'email') {
                endpoint = 'verify-otp-email';
                payload = { email: (emailInput.value || '').trim(), otp: otp };
            } else {
                endpoint = 'verify-otp';
                payload = { mobile: (mobileInput.value || '').trim(), otp: otp };
            }

            if (pendingBooking) {
                payload.service_id = pendingBooking.service_id;
                payload.booking_date = pendingBooking.booking_date;
                payload.booking_time = pendingBooking.booking_time;
                payload.full_name = pendingBooking.full_name;
            }

            postRest(endpoint, payload).then(function (res) {
                if (res.success) {
                    // اگر یک نوبتِ در انتظار (از ویجت عمومی) همراه این ورود
                    // بود ولی خودِ ثبتِ نوبت شکست خورده (مثلاً همان بازه لحظه‌ای
                    // قبل توسط مراجعِ دیگری گرفته شد)، ورود را کامل انجام بده
                    // ولی به‌جای ریدایرکتِ ساکت، پیام خطا را نشان بده — کاربر
                    // که الان لاگین است می‌تواند از تب «نوبت‌ها»ی همان پنل
                    // زمان دیگری انتخاب کند.
                    var pb = res.data.pending_booking;
                    if (pb && !pb.success) {
                        stopCountdown();
                        setMessage(msgEl, (pb.data && pb.data.message) || 'ورود انجام شد ولی ثبت نوبت ناموفق بود. لطفاً از تب «نوبت‌ها» زمان دیگری انتخاب کنید.', true);
                        setTimeout(function () {
                            window.location.href = res.data.redirect || window.location.href;
                        }, 2500);
                        return;
                    }

                    setMessage(msgEl, res.data.message, false);
                    stopCountdown();
                    window.location.href = res.data.redirect || window.location.href;
                    return;
                }
                verifyInFlight = false;
                verifyBtn.disabled = false;
                verifyLabel.textContent = originalText;
                setMessage(msgEl, res.data.message, true);
                // کد اشتباه بود: باکس‌ها را خالی کن و فوکوس را برگردان به
                // باکس اول تا مراجع مجبور نباشد دستی پاکشان کند — هم برای
                // تایپ دستی و هم برای تلاش بعدیِ پرشدن خودکار از پیامک.
                clearOtpBoxes();
                focusFirstOtpBox();
            });
        }

        verifyBtn.addEventListener('click', doVerify);
    }

    // ---------------------------------------------------------------
    // تب‌های پنل
    // ---------------------------------------------------------------
    function initTabs() {
        var tabs = document.querySelectorAll('.zdp-tab');
        if (!tabs.length) return;

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');

                var shown = null;
                document.querySelectorAll('.zdp-tab-panel').forEach(function (panel) {
                    var isTarget = panel.getAttribute('data-panel') === tab.getAttribute('data-tab');
                    panel.hidden = !isTarget;
                    if (isTarget) shown = panel;
                });

                // محتوای تب انتخاب‌شده زیر کارت‌های خلاصه می‌آید و ممکن است بیرون از
                // دید باشد (مخصوصاً موبایل با نوار پایین)؛ اگر دیده نمی‌شود، به آن می‌رویم.
                if (shown && typeof shown.getBoundingClientRect === 'function') {
                    var top = shown.getBoundingClientRect().top;
                    if (top < 0 || top > window.innerHeight * 0.45) {
                        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                        try {
                            shown.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
                        } catch (e) {
                            shown.scrollIntoView(true);
                        }
                    }
                }
            });
        });
    }

    // ---------------------------------------------------------------
    // تبدیل شمسی↔میلادی — پورت دقیق همان الگوریتم PHP در class-jalali.php
    // (که خودش پورت jalaali-js است)، عمداً دوباره اینجا نوشته شده چون
    // panel.js مستقل از سرور اجرا می‌شود و نمی‌تواند کلاس PHP را import کند.
    // هر تغییری در منطق شمسی باید همزمان در هر دو فایل اعمال شود.
    // ---------------------------------------------------------------
    var ZDP_JALALI_BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

    function zdpDiv(a, b) {
        return ~~(a / b);
    }

    function zdpG2d(gy, gm, gd) {
        var d = zdpDiv((gy + zdpDiv(gm - 8, 6) + 100100) * 1461, 4)
            + zdpDiv(153 * ((gm + 9) % 12) + 2, 5)
            + gd - 34840408;
        d = d - zdpDiv(zdpDiv(gy + 100100 + zdpDiv(gm - 8, 6), 100) * 3, 4) + 752;
        return d;
    }

    function zdpD2g(jdn) {
        var j = 4 * jdn + 139361631;
        j = j + zdpDiv(zdpDiv(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        var i = zdpDiv(j % 1461, 4) * 5 + 308;
        var gd = zdpDiv(i % 153, 5) + 1;
        var gm = (zdpDiv(i, 153) % 12) + 1;
        var gy = zdpDiv(j, 1461) - 100100 + zdpDiv(8 - gm, 6);
        return [gy, gm, gd];
    }

    function zdpJalCal(jy) {
        var breaks = ZDP_JALALI_BREAKS;
        var bl = breaks.length;
        var gy = jy + 621;
        var leapJ = -14;
        var jp = breaks[0];
        var jump = 0;
        var i, jm, n;

        for (i = 1; i < bl; i++) {
            jm = breaks[i];
            jump = jm - jp;
            if (jy < jm) break;
            leapJ = leapJ + zdpDiv(jump, 33) * 8 + zdpDiv(jump % 33, 4);
            jp = jm;
        }

        n = jy - jp;
        leapJ = leapJ + zdpDiv(n, 33) * 8 + zdpDiv((n % 33) + 3, 4);
        if (jump % 33 === 4 && jump - n === 4) leapJ++;

        var leapG = zdpDiv(gy, 4) - zdpDiv((zdpDiv(gy, 100) + 1) * 3, 4) - 150;
        var march = 20 + leapJ - leapG;

        if (jump - n < 6) {
            n = n - jump + zdpDiv(jump + 4, 33) * 33;
        }
        var leap = (((n + 1) % 33) - 1) % 4;
        if (leap === -1) leap = 4;

        return { leap: leap, gy: gy, march: march };
    }

    function zdpJ2d(jy, jm, jd) {
        var r = zdpJalCal(jy);
        return zdpG2d(r.gy, 3, r.march) + (jm - 1) * 31 - zdpDiv(jm, 7) * (jm - 7) + jd - 1;
    }

    function zdpD2j(jdn) {
        var gy = zdpD2g(jdn)[0];
        var jy = gy - 621;
        var r = zdpJalCal(jy);
        var jdn1f = zdpG2d(r.gy, 3, r.march);
        var k = jdn - jdn1f;
        var jm, jd;

        if (k >= 0) {
            if (k <= 185) {
                jm = 1 + zdpDiv(k, 31);
                jd = (k % 31) + 1;
                return [jy, jm, jd];
            }
            k -= 186;
        } else {
            jy -= 1;
            k += 179;
            if (r.leap === 1) k++;
        }

        jm = 7 + zdpDiv(k, 30);
        jd = (k % 30) + 1;
        return [jy, jm, jd];
    }

    function zdpToJalali(gy, gm, gd) {
        return zdpD2j(zdpG2d(gy, gm, gd));
    }

    function zdpToGregorian(jy, jm, jd) {
        return zdpD2g(zdpJ2d(jy, jm, jd));
    }

    function zdpPad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    // ---------------------------------------------------------------
    // تقویم شمسی برای فیلد تاریخ نوبت (جایگزین input[type=date] بومی که
    // همیشه میلادی است). مقدار واقعیِ ارسالی به سرور (زوج hidden input
    // #zdp-booking-date) همچنان میلادی YYYY-MM-DD می‌ماند — فقط رابط
    // کاربری تغییر کرده، نه قرارداد داده با سرور.
    // ---------------------------------------------------------------
    var ZDP_JALALI_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    var ZDP_JALALI_WEEKDAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']; // شنبه تا جمعه

    function initJalaliDatePicker() {
        var display = document.getElementById('zdp-booking-date-display');
        var hidden = document.getElementById('zdp-booking-date');
        var calendar = document.getElementById('zdp-jalali-calendar');
        if (!display || !hidden || !calendar) return;

        // «امروز» را دیگر از new Date() مرورگر نمی‌گیریم — new Date() به ساعت
        // و منطقه‌زمانیِ خودِ دستگاه کاربر وابسته است، که روی موبایل‌های
        // مختلف اغلب نادرست/متفاوت تنظیم شده و باعث می‌شد این تقویم روی هر
        // دستگاه چیز متفاوتی نشان دهد (و با «امروز»ی که سرور در نظر می‌گیرد
        // هم‌خوانی نداشته باشد). به‌جایش از ZDP.today_jalali که سمت سرور با
        // منطقه‌زمانی سایت محاسبه شده استفاده می‌کنیم. new Date() فقط به‌عنوان
        // fallback اضطراری (مثلاً اگر اسکریپت localize نشده باشد) باقی مانده.
        var todayJalali;
        if (typeof ZDP !== 'undefined' && ZDP.today_jalali && ZDP.today_jalali.length === 3) {
            todayJalali = [ZDP.today_jalali[0], ZDP.today_jalali[1], ZDP.today_jalali[2]];
        } else {
            var now = new Date();
            todayJalali = zdpToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
        }
        var viewYear = todayJalali[0];
        var viewMonth = todayJalali[1];
        var selectedJalali = null; // [jy, jm, jd] بعد از انتخاب کاربر

        function isBeforeToday(jy, jm, jd) {
            if (jy !== todayJalali[0]) return jy < todayJalali[0];
            if (jm !== todayJalali[1]) return jm < todayJalali[1];
            return jd < todayJalali[2];
        }

        function daysInMonth(jy, jm) {
            var nextJy = jm === 12 ? jy + 1 : jy;
            var nextJm = jm === 12 ? 1 : jm + 1;
            return zdpJ2d(nextJy, nextJm, 1) - zdpJ2d(jy, jm, 1);
        }

        function render() {
            var firstDayJdn = zdpJ2d(viewYear, viewMonth, 1);
            var firstGregorian = zdpD2g(firstDayJdn);
            var jsDate = new Date(firstGregorian[0], firstGregorian[1] - 1, firstGregorian[2]);
            var offset = (jsDate.getDay() + 1) % 7; // شنبه=۰ ... جمعه=۶
            var total = daysInMonth(viewYear, viewMonth);

            var html = '<div class="zdp-jalali-cal-head">'
                + '<button type="button" class="zdp-jalali-cal-nav" data-nav="prev">‹</button>'
                + '<strong>' + ZDP_JALALI_MONTHS[viewMonth - 1] + ' ' + viewYear + '</strong>'
                + '<button type="button" class="zdp-jalali-cal-nav" data-nav="next">›</button>'
                + '</div>'
                + '<div class="zdp-jalali-cal-weekdays">'
                + ZDP_JALALI_WEEKDAYS.map(function (w) { return '<span>' + w + '</span>'; }).join('')
                + '</div>'
                + '<div class="zdp-jalali-cal-days">';

            for (var e = 0; e < offset; e++) {
                html += '<span class="zdp-jalali-cal-empty">.</span>';
            }

            for (var d = 1; d <= total; d++) {
                var disabled = isBeforeToday(viewYear, viewMonth, d);
                var isToday = viewYear === todayJalali[0] && viewMonth === todayJalali[1] && d === todayJalali[2];
                var isSelected = selectedJalali && viewYear === selectedJalali[0] && viewMonth === selectedJalali[1] && d === selectedJalali[2];
                var cls = (isToday ? ' zdp-jalali-today' : '') + (isSelected ? ' zdp-jalali-selected' : '');
                html += '<button type="button" class="' + cls.trim() + '" data-day="' + d + '"' + (disabled ? ' disabled' : '') + '>' + d + '</button>';
            }

            html += '</div>';
            calendar.innerHTML = html;

            calendar.querySelectorAll('[data-nav]').forEach(function (btn) {
                btn.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    if (btn.getAttribute('data-nav') === 'prev') {
                        viewMonth--;
                        if (viewMonth < 1) { viewMonth = 12; viewYear--; }
                    } else {
                        viewMonth++;
                        if (viewMonth > 12) { viewMonth = 1; viewYear++; }
                    }
                    render();
                });
            });

            calendar.querySelectorAll('[data-day]').forEach(function (btn) {
                btn.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    var day = parseInt(btn.getAttribute('data-day'), 10);
                    selectedJalali = [viewYear, viewMonth, day];
                    var greg = zdpToGregorian(viewYear, viewMonth, day);
                    hidden.value = greg[0] + '-' + zdpPad2(greg[1]) + '-' + zdpPad2(greg[2]);
                    display.value = viewYear + '/' + zdpPad2(viewMonth) + '/' + zdpPad2(day);
                    calendar.hidden = true;
                });
            });
        }

        display.addEventListener('click', function (ev) {
            ev.stopPropagation();
            calendar.hidden = !calendar.hidden;
            if (!calendar.hidden) render();
        });

        document.addEventListener('click', function (ev) {
            if (!calendar.hidden && ev.target !== display && !calendar.contains(ev.target)) {
                calendar.hidden = true;
            }
        });
    }

    // ---------------------------------------------------------------
    // فرم رزرو نوبت
    // ---------------------------------------------------------------
    function initBookingForm() {
        var form = document.getElementById('zdp-booking-form');
        if (!form) return;
        var resultEl = document.getElementById('zdp-booking-result');
        var cardsEl = document.getElementById('zdp-booking-cards');
        var bookBtn = document.getElementById('zdp-book-btn');
        if (!bookBtn) return;

        bookBtn.addEventListener('click', function () {
            var serviceSelect = document.getElementById('zdp-service-select');
            var dateInput = document.getElementById('zdp-booking-date');
            var timeInput = document.getElementById('zdp-booking-time');
            var fullnameInput = document.getElementById('zdp-booking-fullname');

            var selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            var serviceId = selectedOption ? selectedOption.value : '';
            var date = dateInput.value;
            var time = timeInput.value;
            var fullName = fullnameInput ? fullnameInput.value.trim() : '';

            if (!fullName) {
                setMessage(resultEl, 'لطفاً نام و نام‌خانوادگی را وارد کنید.', true);
                return;
            }

            if (!serviceId || !date || !time) {
                setMessage(resultEl, 'لطفاً خدمت، تاریخ و ساعت را انتخاب کنید.', true);
                return;
            }

            // توجه: نام/قیمت خدمت دیگر از کلاینت ارسال نمی‌شود — فقط شناسه
            // ارسال می‌شود و سرور نام و قیمت واقعی را از لیست رسمی خدمات
            // بازیابی می‌کند (جلوگیری از دستکاری قیمت توسط کاربر).
            bookBtn.disabled = true;
            post('zdp_create_booking', {
                service_id: serviceId,
                booking_date: date,
                booking_time: time,
                full_name: fullName,
            }).then(function (res) {
                bookBtn.disabled = false;

                if (!res.success) {
                    setMessage(resultEl, (res.data && res.data.message) || 'خطا در ثبت نوبت.', true);
                    return;
                }

                if (res.data.redirect) {
                    window.location.href = res.data.redirect;
                    return;
                }

                if (res.data.manual_payment) {
                    setMessage(resultEl, res.data.message, false);
                    var cards = res.data.cards || [];
                    var html = '<h4>اطلاعات واریز</h4>';
                    cards.forEach(function (c) {
                        html += '<div class="zdp-card-item"><strong>' + escapeHtml(c.bank_name) + '</strong><br>'
                            + 'شماره کارت: <span dir="ltr">' + escapeHtml(c.card_number) + '</span><br>'
                            + (c.iban ? ('شبا: <span dir="ltr">' + escapeHtml(c.iban) + '</span><br>') : '')
                            + 'به‌نام: ' + escapeHtml(c.holder_name) + '</div>';
                    });
                    cardsEl.innerHTML = html;
                    cardsEl.hidden = false;
                    return;
                }
            });
        });
    }

    // ---------------------------------------------------------------
    // پیام سریع به پشتیبانی
    // ---------------------------------------------------------------
    function initMessageForm() {
        var btn = document.getElementById('zdp-send-message-btn');
        if (!btn) return;
        var textEl = document.getElementById('zdp-message-text');
        var resultEl = document.getElementById('zdp-message-result');

        btn.addEventListener('click', function () {
            var text = (textEl.value || '').trim();
            if (text.length < 3) {
                setMessage(resultEl, 'متن پیام خیلی کوتاه است.', true);
                return;
            }

            btn.disabled = true;
            post('zdp_send_message', { message_text: text }).then(function (res) {
                btn.disabled = false;
                setMessage(resultEl, res.data.message, !res.success);
                if (res.success) {
                    textEl.value = '';
                }
            });
        });
    }

    // ---------------------------------------------------------------
    // آپلود رسید کارت‌به‌کارت (تب «نوبت‌ها»، وقتی نوبت در انتظار تایید واریز است)
    // ---------------------------------------------------------------
    function initReceiptUpload() {
        var form = document.getElementById('zdp-receipt-upload-form');
        if (!form) return;

        var fileInput = document.getElementById('zdp-receipt-file');
        var resultEl  = document.getElementById('zdp-receipt-result');
        var btn       = form.querySelector('.zdp-receipt-submit');

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            if (!fileInput.files || !fileInput.files[0]) {
                setMessage(resultEl, 'لطفاً ابتدا تصویر رسید را انتخاب کنید.', true);
                return;
            }

            var bookingIdInput = form.querySelector('input[name="booking_id"]');

            if (btn) { btn.disabled = true; }
            setMessage(resultEl, 'در حال آپلود...', false);

            post('zdp_upload_receipt', {
                booking_id: bookingIdInput ? bookingIdInput.value : '',
                receipt: fileInput.files[0],
            }).then(function (res) {
                if (btn) { btn.disabled = false; }
                setMessage(resultEl, res.data.message, !res.success);

                // بعد از آپلود موفق، وضعیت پنل دوباره از سرور خوانده می‌شود
                // تا فرم آپلود با جعبه‌ی «رسید ارسال شد، در انتظار تایید»
                // جایگزین شود — بدون رفرش کامل صفحه.
                if (res.success) {
                    loadPanelApp();
                }
            });
        });
    }

    // ---------------------------------------------------------------
    // نظرسنجی بی‌نام
    // ---------------------------------------------------------------
    function initSurveyForm() {
        var form = document.getElementById('zdp-survey-form');
        if (!form) return;

        var btn = form.querySelector('.zdp-survey-submit');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var data = new FormData(form);
            var payload = {};
            data.forEach(function (value, key) { payload[key] = value; });

            if (btn) { btn.disabled = true; }
            post('zdp_submit_survey', payload).then(function (res) {
                if (btn) { btn.disabled = false; }
                setMessage(document.getElementById('zdp-survey-result'), res.data.message, !res.success);
                if (res.success) {
                    form.reset();
                }
            });
        });
    }

    // ---------------------------------------------------------------
    // فرم ارزیابی (Intake)
    // ---------------------------------------------------------------
    function initIntakeForm() {
        var form = document.getElementById('zdp-intake-form');
        if (!form || form.dataset.bound === '1') return;
        form.dataset.bound = '1';

        var btn = form.querySelector('.zdp-intake-submit');
        var result = document.getElementById('zdp-intake-result');

        ['zdp-in-birth', 'zdp-in-children', 'zdp-in-phone'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', function () { el.value = toEnglishDigits(el.value); });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var payload = {};
            new FormData(form).forEach(function (value, key) { payload[key] = value; });

            if (btn) btn.disabled = true;
            post('zdp_submit_intake', payload).then(function (res) {
                if (btn) btn.disabled = false;
                setMessage(result, res.data && res.data.message, !res.success);
                if (res.success) {
                    var badge = document.querySelector('.zdp-tab[data-tab="intake"] .zdp-tab-badge');
                    if (badge) badge.remove();
                    var banner = document.querySelector('.zdp-intake-banner');
                    if (banner) banner.remove();
                }
            });
        });
    }

    // ---------------------------------------------------------------
    // فانل کوییز عمومی
    // ---------------------------------------------------------------
    function initQuizFunnel() {
        var wrap = document.getElementById('zdp-public-quiz');
        if (!wrap) return;

        var form = document.getElementById('zdp-quiz-form');
        var partialBox = document.getElementById('zdp-quiz-partial-result');
        var fullBox = document.getElementById('zdp-quiz-full-result');
        var source = wrap.getAttribute('data-source');
        var topic = wrap.getAttribute('data-topic');
        var tempId = null;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var data = new FormData(form);
            var payload = { source: source, topic: topic };
            data.forEach(function (value, key) { payload[key] = value; });

            post('zdp_quiz_submit_answers', payload).then(function (res) {
                if (!res.success) {
                    alert(res.data.message || 'خطایی رخ داد، دوباره تلاش کنید.');
                    return;
                }

                form.hidden = true;
                tempId = res.data.temp_id;
                partialBox.hidden = false;
                partialBox.innerHTML =
                    '<h3>' + res.data.band_label + '</h3>' +
                    '<p>برای دریافت تحلیل کامل و راهکارهای اختصاصی، شماره همراه خود را وارد کنید:</p>' +
                    '<input type="text" id="zdp-quiz-phone" placeholder="09xxxxxxxxx یا +xxxxxxxxxxx (00 هم قابل قبول است)">' +
                    '<button id="zdp-quiz-phone-submit">دریافت تحلیل کامل</button>';

                document.getElementById('zdp-quiz-phone-submit').addEventListener('click', function () {
                    var phone = document.getElementById('zdp-quiz-phone').value.trim();
                    if (!isValidMobileInput(phone)) {
                        alert('شماره موبایل معتبر وارد کنید.');
                        return;
                    }

                    post('zdp_quiz_submit_phone', { temp_id: tempId, phone: phone }).then(function (res2) {
                        if (!res2.success) {
                            alert(res2.data.message || 'خطایی رخ داد.');
                            return;
                        }
                        partialBox.hidden = true;
                        fullBox.hidden = false;
                        fullBox.innerHTML = res2.data.html;
                    });
                });
            });
        });
    }

    // ---------------------------------------------------------------
    // جدول عمومی نوبت‌دهی — شورت‌کد [zdp_booking_widget] (صفحه‌ی اصلی سایت،
    // برای مراجعِ لاگین یا لاگین‌نشده). جدول از zdp/v1/booking-grid پر
    // می‌شود (نگاه کن به Booking::rest_booking_grid) تا همیشه لحظه‌ای باشد.
    // برای مراجعِ لاگین‌نشده، بعد از انتخاب بازه نام+موبایل گرفته می‌شود،
    // کد تایید ارسال/بررسی می‌شود (همان REST send-otp/verify-otp پنل) و
    // خودِ نوبت داخل همان درخواست verify-otp ثبت می‌شود (نگاه کن به
    // Booking::create_booking_from_otp_flow) — قبل از این‌که اصلاً ریدایرکت
    // به پنل اتفاق بیفتد.
    // ---------------------------------------------------------------
    // باگ رفع‌شده (منوی خدمات/فرم رزرو در یکی از دو نمونه‌ی ویجت کار
    // نمی‌کرد): این تابع قبلاً فقط با document.querySelector('.zdp-booking-widget')
    // *اولین* نمونه‌ی ویجت در کل صفحه را برمی‌داشت و همه‌ی selectorهای
    // داخلی‌اش هم با document.getElementById (سراسریِ کل سند) بودند. وقتی
    // یک صفحه هم‌زمان شورت‌کد عمومی [zdp_booking_widget] و هم [zdp_client_panel]
    // (که تب «نوبت‌ها»یش دقیقاً همین markup را داخل خودش تزریق می‌کند) را
    // داشت، در DOM دو تا «.zdp-booking-widget» با آی‌دی‌های داخلی یکسان
    // وجود داشت: initBookingWidget() (که یک‌بار روی DOMContentLoaded و یک‌بار
    // بعد از لود AJAX پنل صدا زده می‌شود، نگاه کن به فراخوانی‌هایش) هر دو بار
    // فقط همان *اولین* نمونه را در DOM پیدا و به آن رویداد وصل می‌کرد؛ نمونه‌ی
    // دوم (چه شورت‌کد عمومی چه تب پنل، هرکدام در DOM دوم بود) هیچ‌وقت واقعاً
    // wire نمی‌شد — دکمه‌ها/منوی خدمتش ظاهراً هست ولی هیچ کلیکی اثر ندارد،
    // و برای مهمانی که وارد پنل نشده، ثبت نوبت از شورت‌کد کاملاً بی‌اثر
    // می‌ماند. حالا روی *همه‌ی* نمونه‌های ویجت در صفحه حلقه می‌زنیم و همه‌ی
    // selectorهای داخلی (getElementById سراسری) به querySelector محدود به
    // همان «widget» (ریشه‌ی DOM آن نمونه‌ی خاص) تغییر کرده‌اند — پس هر
    // نمونه، حتی با آی‌دی‌های تکراری، عناصر خودش را پیدا می‌کند نه عناصر
    // نمونه‌ی دیگر. data-zdp-bw-bound هم از bind دوباره‌ی همان نمونه (وقتی
    // initBookingWidget() برای بار دوم صدا زده می‌شود) جلوگیری می‌کند.
    function initBookingWidget() {
        var widgets = Array.prototype.slice.call(document.querySelectorAll('.zdp-booking-widget'));
        widgets.forEach(function (widget) {
            if (widget.getAttribute('data-zdp-bw-bound') === '1') return;
            widget.setAttribute('data-zdp-bw-bound', '1');
            initSingleBookingWidget(widget);
        });
    }

    function initSingleBookingWidget(widget) {
        if (!widget) return;

        // باگ رفع‌شده: قبلاً وضعیت لاگین از data-logged-in (رندرشده در PHP
        // موقع لود صفحه، پس قابل‌کش‌شدن توسط پلاگین‌های کش صفحه) خوانده
        // می‌شد. حالا این ویجت (برای شورت‌کد عمومی) همیشه با اسکلت مهمان
        // رندر می‌شود؛ وضعیت واقعی از پاسخ REST غیرقابل‌کشِ booking-grid
        // (نگاه کن به Booking::rest_booking_grid که Cache_Guard::no_store()
        // صدا می‌زند) گرفته و همین‌جا اعمال می‌شود — نه از این HTML.
        var loggedIn     = false;
        var loginKnown   = false;
        var loadingEl    = widget.querySelector('.zdp-bw-grid-loading');
        var dayViewEl    = widget.querySelector('#zdp-bw-day-view');
        var quickFiltersEl = widget.querySelector('#zdp-bw-quickfilters');
        var confirmBox   = widget.querySelector('#zdp-bw-confirm');
        var summaryEl    = widget.querySelector('#zdp-bw-selected-summary');
        var resultEl     = widget.querySelector('#zdp-bw-result');
        var serviceSelect = widget.querySelector('#zdp-bw-service');
        var fullNameInput = widget.querySelector('#zdp-bw-fullname');

        var selected = null; // { date, time, label }
        var lastGrid = null; // آخرین داده‌ی گرید دریافت‌شده از سرور (برای سوییچ بین روزها بدون درخواست دوباره)
        var currentDayIndex = 0; // اندیس روزِ در حال نمایش در نمای یک‌روزه (برای دکمه‌های ‹ › روز بعد/قبل)

        // ── فیلتر سریع «امروز/فردا»: طبق درخواست کارفرما، جدول/گرید هفتگی
        // کاملاً حذف شد؛ این دو دکمه فقط میان‌بر هستند و مستقیماً نمای
        // یک‌روزه‌ی (renderDayView) همان روز را نشان می‌دهند. برای هر روز
        // دیگری، مراجع از دکمه‌های ‹ › روز قبل/بعد داخل خودِ نمای یک‌روزه
        // استفاده می‌کند (نگاه کن به renderDayView/showDayView پایین‌تر).
        // یک‌بار بایند می‌شود چون خودِ دکمه‌ها هیچ‌وقت دوباره ساخته نمی‌شوند.
        var qfButtons = quickFiltersEl ? Array.prototype.slice.call(quickFiltersEl.querySelectorAll('.zdp-bw-qf-btn')) : [];
        if (quickFiltersEl) {
            qfButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var qf = btn.getAttribute('data-qf');

                    if (qf === 'today') {
                        showDayView(0);
                    } else if (qf === 'tomorrow') {
                        showDayView(1);
                    }

                    // بازخورد بصری: نشان‌دادن این‌که کدام فیلتر سریع فعال است.
                    qfButtons.forEach(function (b) { b.classList.remove('zdp-bw-qf-active'); });
                    btn.classList.add('zdp-bw-qf-active');
                });
            });
        }

        function deactivateQuickFilters() {
            if (!quickFiltersEl) return;
            Array.prototype.slice.call(quickFiltersEl.querySelectorAll('.zdp-bw-qf-btn')).forEach(function (b) {
                b.classList.remove('zdp-bw-qf-active');
            });
        }

        function currentServiceId() {
            return serviceSelect ? serviceSelect.value : '';
        }

        // باگ/بازطراحیِ رفع‌شده: منوی سفارشیِ قبلی (دکمه+لیستِ inline روی
        // یک select مخفی) هم چون همه‌ی نمونه‌های ویجت را با document.getElementById
        // سراسری پیدا می‌کرد کار نمی‌کرد، هم ظاهرش طبق نظر کارفرما مناسب
        // نبود. حالا #zdp-bw-service خودش یک select معمولی و قابل‌مشاهده
        // است (نگاه کن به render_grid_widget_markup در class-booking.php)
        // و رویداد change‌اش مستقیماً پایین‌تر به loadGrid وصل می‌شود؛ هیچ
        // منطق باز/بسته‌شدنِ جداگانه‌ای لازم نیست.

        function validateSelectionAndName() {
            if (!selected) {
                setMessage(resultEl, 'لطفاً یک زمان از جدول انتخاب کنید.', true);
                return false;
            }
            var name = (fullNameInput && fullNameInput.value || '').trim();
            if (name.length < 2) {
                setMessage(resultEl, 'لطفاً نام و نام‌خانوادگی را وارد کنید.', true);
                return false;
            }
            return true;
        }

        // ── وصل‌کردن کلیک به دکمه‌های «سلولِ خالی» در نمای یک‌روزه
        // (renderDayView). «قبلاً انتخاب‌شده» را روی کل ویجت (نه فقط همان
        // کانتینر) پاک می‌کند، چون ممکن است مراجع قبلاً روز دیگری را دیده و
        // سلولی را آنجا انتخاب کرده باشد — آن انتخاب قبلی باید پاک شود.
        function bindSlotButtons(container) {
            Array.prototype.slice.call(container.querySelectorAll('.zdp-bw-cell-open')).forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var prevSelected = widget.querySelector('.zdp-bw-cell-selected');
                    if (prevSelected) prevSelected.classList.remove('zdp-bw-cell-selected');
                    btn.classList.add('zdp-bw-cell-selected');

                    selected = {
                        date: btn.getAttribute('data-date'),
                        time: btn.getAttribute('data-time'),
                        label: btn.getAttribute('data-label'),
                    };
                    summaryEl.textContent = 'نوبت انتخابی: ' + selected.label + ' — ساعت ' + selected.time;
                    confirmBox.hidden = false;
                    // اگر مراجعِ لاگین‌نشده قبلاً تا مرحله‌ی OTP رفته بود (مثلاً
                    // چون نوبتِ قبلی‌اش به‌خاطر تداخل شکست خورد و جدول رفرش شد)،
                    // با انتخابِ یک سلول جدید باید دوباره از مرحله‌ی «نام» شروع
                    // شود، نه این‌که مرحله‌ی OTP قبلی (با کد/شماره‌ی قدیمی) باز
                    // بماند.
                    if (typeof otpStep !== 'undefined' && otpStep && typeof nameStep !== 'undefined' && nameStep) {
                        otpStep.hidden = true;
                        nameStep.hidden = false;
                    }
                    if (typeof stopBwCountdown === 'function') stopBwCountdown();
                    setMessage(resultEl, '', false);
                    confirmBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
            });
        }

        // ── نمای «یک‌روزه»: به‌جای ستون‌های فشرده‌ی جدول هفتگی، کارت‌های
        // بزرگ‌ترِ فقط همان یک روز، در یک گرید ریسپانسیو (نه جدول). طبق
        // درخواست صریح کارفرما برای بهتر دیده‌شدنِ اندازه‌ی سلول‌ها وقتی
        // فقط یک روز فیلتر شده است.
        function renderDayView(day) {
            var openCount = 0;
            if (day.slots) {
                Object.keys(day.slots).forEach(function (t) { if (day.slots[t] === 'open') openCount++; });
            }
            var countHtml = day.closed
                ? '<span class="zdp-bw-col-count zdp-bw-col-count-zero">در این بازه نوبتی موجود نیست</span>'
                : (openCount > 0
                    ? '<span class="zdp-bw-col-count">' + openCount + ' نوبت خالی</span>'
                    : '<span class="zdp-bw-col-count zdp-bw-col-count-zero">تکمیل</span>');

            var atStart = currentDayIndex <= 0;
            var atEnd = !lastGrid || currentDayIndex >= lastGrid.days.length - 1;
            var html = '<div class="zdp-bw-day-header' + (day.closed ? ' zdp-bw-col-closed' : '') + '">'
                + '<button type="button" class="zdp-bw-day-nav zdp-bw-day-nav-prev" id="zdp-bw-day-prev" aria-label="روز قبل"' + (atStart ? ' disabled' : '') + '>‹</button>'
                + '<div>'
                + '<span class="zdp-bw-col-weekday">' + escapeHtml(day.weekday) + '</span>'
                + '<span class="zdp-bw-col-date">' + day.jalali_day + ' ' + escapeHtml(day.jalali_month) + '</span>'
                + '</div>'
                + (day.mode_label ? '<span class="zdp-bw-col-mode">' + escapeHtml(day.mode_label) + '</span>' : '')
                + countHtml
                + '<button type="button" class="zdp-bw-day-nav zdp-bw-day-nav-next" id="zdp-bw-day-next" aria-label="روز بعد"' + (atEnd ? ' disabled' : '') + '>›</button>'
                + '</div>';

            html += '<div class="zdp-bw-day-slots">';
            var anySlotRendered = false;
            lastGrid.slot_times.forEach(function (time) {
                var state = (day.slots && day.slots[time]) || 'closed';
                var label = day.weekday + ' ' + day.jalali_day + ' ' + day.jalali_month;
                // ساعتِ بسته (خارج از ساعت کاری یا داخل بازه‌ی استراحت):
                // طبق درخواست کارفرما اصلاً در نمای یک‌روزه رسم نشود.
                if (state === 'closed') {
                    return;
                }
                anySlotRendered = true;

                if (state === 'open') {
                    html += '<button type="button" class="zdp-bw-cell zdp-bw-cell-open zdp-bw-day-slot" '
                        + 'data-date="' + day.date + '" data-time="' + time + '" data-label="' + escapeHtml(label) + '" '
                        + 'aria-label="انتخاب ' + escapeHtml(label) + ' ساعت ' + time + '">'
                        + '<span class="zdp-bw-cell-time">' + time + '</span></button>';
                } else if (state === 'taken') {
                    html += '<span class="zdp-bw-cell zdp-bw-cell-taken zdp-bw-day-slot" aria-hidden="true">'
                        + '<span class="zdp-bw-cell-time">' + time + '</span></span>';
                } else {
                    // state === 'past' (ساعتِ گذشته‌ی امروز) — همان ظاهرِ
                    // خنثیِ قبلی حفظ می‌شود؛ فقط 'closed' واقعی مخفی می‌شود.
                    html += '<span class="zdp-bw-cell zdp-bw-cell-closed zdp-bw-day-slot" aria-hidden="true">'
                        + '<span class="zdp-bw-cell-time">' + time + '</span></span>';
                }
            });
            // اگر هیچ ساعتی برای این روز رسم نشد (روز کاملاً تعطیل یا تمام
            // ساعت‌ها در بازه‌ی استراحت)، به‌جای کارتِ خالیِ گنگ، پیام صریح
            // نشان داده می‌شود؛ دکمه‌های ‹ › بالای کارت (روز قبل/بعد) همان‌جا
            // راهِ رفتن به روزهای دیگر را می‌دهند.
            if (!anySlotRendered) {
                html += '<div class="zdp-bw-empty-row">'
                    + '<span>در این بازه نوبتی موجود نیست</span>'
                    + '</div>';
            }
            html += '</div>';

            // انیمیشن ورود: کلاس fade-in حذف و در فریم بعدی دوباره اضافه
            // می‌شود تا هر بار سوییچ روز، انیمیشن از نو اجرا شود (فقط عوض‌کردن
            // innerHTML باعث ری‌استارت انیمیشن CSS نمی‌شود).
            dayViewEl.classList.remove('zdp-bw-fade-in');
            dayViewEl.innerHTML = html;
            bindSlotButtons(dayViewEl);

            // ── دکمه‌های ‹ › روزِ قبل/بعد ──
            var prevBtn = widget.querySelector('#zdp-bw-day-prev');
            var nextBtn = widget.querySelector('#zdp-bw-day-next');
            if (prevBtn) {
                prevBtn.addEventListener('click', function () {
                    deactivateQuickFilters();
                    showDayView(currentDayIndex - 1);
                });
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    deactivateQuickFilters();
                    showDayView(currentDayIndex + 1);
                });
            }
            void dayViewEl.offsetWidth; // force reflow
            dayViewEl.classList.add('zdp-bw-fade-in');
        }

        // ── نمایش نمای یک‌روزه برای اندیس روزِ داده‌شده ──
        function showDayView(dayIndex) {
            if (!lastGrid || !lastGrid.days || !lastGrid.days.length) return;
            var idx = Math.min(Math.max(dayIndex, 0), lastGrid.days.length - 1);
            currentDayIndex = idx;
            dayViewEl.hidden = false;
            renderDayView(lastGrid.days[idx]);
        }

        // اسکلتون لودینگ: به‌جای فقط متن «در حال دریافت...»، یک شبکه‌ی
        // خاکستریِ ضربان‌دار (shimmer) هم‌شکل با نمای یک‌روزه‌ی واقعی
        // (renderDayView) نشان داده می‌شود تا حس سریع‌تر/مدرن‌تر بدهد —
        // یک نوارِ هدرِ روز + چند کارتِ ساعتِ خالی، نه شکلِ جدول هفتگیِ حذف‌شده.
        // ثابتِ تعداد روزهای درخواستی از سرور برای ساخت query string.
        var GRID_DAYS_REQUEST = 14;

        function buildSkeletonHtml() {
            var slots = 8;
            var html = '<div class="zdp-bw-skel" aria-hidden="true">'
                + '<span class="zdp-bw-skel-day-header"></span>'
                + '<div class="zdp-bw-skel-day-grid">';
            for (var i = 0; i < slots; i++) {
                html += '<span class="zdp-bw-skel-cell"></span>';
            }
            html += '</div></div>';
            return html;
        }

        // بعد از تشخیص واقعیِ وضعیت لاگین (از پاسخ REST، نه از data-logged-in
        // کش‌شده)، بین دو بلوکِ همیشه‌موجود در DOM (مهمان: موبایل+OTP، یا
        // لاگین‌شده: دکمه‌ی مستقیم — نگاه کن به Booking::render_grid_widget_markup)
        // سوییچ می‌کند و برای مراجعِ واقعاً لاگین‌شده، نام را از usermeta
        // پیش‌پر می‌کند (اگر خودش چیزی تایپ نکرده باشد).
        function applyLoginState(isLoggedIn, fullName) {
            loggedIn = isLoggedIn;
            var guestFieldsEl = confirmBox.querySelector('.zdp-bw-guest-fields');
            var loggedInBtn   = widget.querySelector('#zdp-bw-book-loggedin');
            if (guestFieldsEl) guestFieldsEl.hidden = loggedIn;
            if (loggedInBtn) loggedInBtn.hidden = !loggedIn;
            if (loggedIn && fullName && fullNameInput && !fullNameInput.value.trim()) {
                fullNameInput.value = fullName;
            }
        }

        function loadGrid() {
            if (typeof stopBwCountdown === 'function') stopBwCountdown();
            loadingEl.hidden = false;
            loadingEl.innerHTML = buildSkeletonHtml();
            dayViewEl.hidden = true;
            if (quickFiltersEl) {
                quickFiltersEl.hidden = true;
                // با هر بارگذاری تازه (مثلاً عوض‌شدن خدمت)، فیلتر فعال قبلی
                // بی‌معنی می‌شود؛ نمای پیش‌فرض دوباره «امروز» است.
                qfButtons.forEach(function (b) {
                    b.classList.remove('zdp-bw-qf-active');
                });
            }
            // انتخاب قبلی (اگر بود) دیگر معتبر نیست چون جدول دوباره ساخته می‌شود
            selected = null;
            confirmBox.hidden = true;

            var qs = 'days=' + GRID_DAYS_REQUEST;
            if (currentServiceId()) {
                qs += '&service_id=' + encodeURIComponent(currentServiceId());
            }

            getRest('booking-grid?' + qs).then(function (res) {
                if (!res.success) {
                    var errMsg = (res.data && res.data.message) || 'خطا در دریافت زمان‌های خالی.';
                    loadingEl.innerHTML = '<div class="zdp-bw-grid-error">'
                        + '<span class="zdp-bw-grid-error-icon" aria-hidden="true">⚠️</span>'
                        + '<span class="zdp-bw-grid-error-text">' + escapeHtml(errMsg) + '</span>'
                        + '<button type="button" class="zdp-bw-grid-retry-btn">تلاش مجدد</button>'
                        + '</div>';
                    var retryBtn = loadingEl.querySelector('.zdp-bw-grid-retry-btn');
                    if (retryBtn) {
                        retryBtn.addEventListener('click', loadGrid);
                    }
                    return;
                }
                loadingEl.hidden = true;
                loadingEl.innerHTML = '';
                lastGrid = res.data;

                // فقط یک‌بار (اولین پاسخ) اعمال می‌شود؛ خودِ res.data.logged_in
                // از سرور و بدون کش می‌آید، پس منبع درستی برای تشخیص واقعی
                // وضعیت لاگین است (برخلاف HTML اولیه‌ی این ویجت که ممکن است
                // توسط یک صفحه‌ی کش‌شده قدیمی باشد).
                if (!loginKnown) {
                    loginKnown = true;
                    applyLoginState(!!res.data.logged_in, res.data.full_name || '');
                }

                if (quickFiltersEl) quickFiltersEl.hidden = false;
                // پیش‌فرض: نمای یک‌روزه نشان داده می‌شود، ولی نه لزوماً «امروز» —
                // رفع باگ: قبلاً همیشه day index صفر (امروز) نشان داده می‌شد،
                // حتی اگر خدمتِ انتخابی امروز اصلاً ارائه نمی‌شد (مثلاً امروز
                // فقط حضوری است ولی مراجع خدمت تلفنی را انتخاب کرده). مراجع
                // مجبور بود دستی با دکمه‌ی ‹ › روزها را یکی‌یکی جلو برود تا به
                // اولین روزِ واقعاً بازِ آن خدمت برسد. حالا اولین روزی که برای
                // خدمتِ انتخاب‌شده بسته نیست پیدا و مستقیماً نشان داده می‌شود؛
                // اگر هیچ روزِ بازی در کل بازه نبود (نادر)، همان روز اول (با
                // پیام «نوبتی موجود نیست») نشان داده می‌شود — دقیقاً رفتار قبلی.
                var firstOpenIndex = 0;
                for (var di = 0; di < lastGrid.days.length; di++) {
                    if (!lastGrid.days[di].closed) {
                        firstOpenIndex = di;
                        break;
                    }
                }
                showDayView(firstOpenIndex);
                qfButtons.forEach(function (b) { b.classList.remove('zdp-bw-qf-active'); });
                // فیلتر سریع «امروز/فردا» فقط وقتی به‌عنوان فعال نشان داده شود
                // که واقعاً همان روز نمایش داده شده باشد — وگرنه دکمه‌ی «امروز»
                // فعال به نظر می‌رسید در حالی که کارت روزِ دیگری نشان داده می‌شد.
                var qfKey = firstOpenIndex === 0 ? 'today' : (firstOpenIndex === 1 ? 'tomorrow' : null);
                var activeQfBtn = qfKey && quickFiltersEl && quickFiltersEl.querySelector('[data-qf="' + qfKey + '"]');
                if (activeQfBtn) activeQfBtn.classList.add('zdp-bw-qf-active');
            });
        }

        loadGrid();
        if (serviceSelect) {
            serviceSelect.addEventListener('change', loadGrid);
        }

        // ── مراجعِ از قبل لاگین: مستقیماً همان اندپوینت رزرو پنل صدا زده می‌شود ──
        // نکته: این دکمه همیشه در DOM وجود دارد (نگاه کن به applyLoginState)
        // ولی فقط وقتی loggedIn واقعاً true باشد نمایش داده می‌شود؛ کلیک روی
        // آن (که در حالت مخفی عملاً ممکن نیست) هم اضافه‌احتیاطاً چک می‌شود.
        var bookBtn = widget.querySelector('#zdp-bw-book-loggedin');
        if (bookBtn) {
            bookBtn.addEventListener('click', function () {
                if (!loggedIn) return;
                if (!validateSelectionAndName()) return;

                bookBtn.disabled = true;
                post('zdp_create_booking', {
                    service_id: currentServiceId(),
                    booking_date: selected.date,
                    booking_time: selected.time,
                    full_name: fullNameInput.value.trim(),
                }).then(function (res) {
                    bookBtn.disabled = false;

                    if (!res.success) {
                        setMessage(resultEl, (res.data && res.data.message) || 'خطا در ثبت نوبت.', true);
                        // طبق استاندارد جدول‌های رزرو حرفه‌ای: اگر ثبت نوبت به هر
                        // دلیلی (مثلاً همان لحظه توسط مراجعِ دیگری گرفته شد) شکست
                        // بخورد، جدول دوباره از سرور بارگذاری می‌شود تا وضعیت واقعی
                        // ساعت‌ها به‌روز شود؛ در غیر این صورت سلولِ «باز»ِ قدیمی روی
                        // صفحه می‌ماند و کاربر دوباره روی همان ساعتِ اشغال‌شده کلیک
                        // می‌کند.
                        loadGrid();
                        return;
                    }
                    // هم مسیر زرین‌پال (redirect به درگاه) و هم مسیر کارت‌به‌کارت
                    // (که خودش داخل پنل، تب «نوبت‌های من» با جعبه‌ی راهنمای
                    // پرداخت نمایش داده می‌شود — نگاه کن به Client_Panel) هر دو
                    // با هدایت به پنل کامل می‌شوند؛ صفحه‌ی اصلی خودش کارتی برای
                    // نمایش ندارد.
                    window.location.href = res.data.redirect || (window.ZDP && window.ZDP.panel_url) || '/panel/';
                });
            });
        }

        // ── مراجعِ لاگین‌نشده: بعد از انتخاب زمان + نام، به‌جای وارد کردن
        // موبایل/کد همین‌جا، مستقیم به صفحه‌ی واقعیِ ورود پنل هدایت می‌شود.
        // انتخاب خدمت/تاریخ/ساعت/نام از طریق querystring همراه او می‌رود؛
        // فرم ورود پنل (initLoginForm در همین فایل) این مقادیر را از URL
        // می‌خواند و همراه درخواست verify-otp خودش به سرور می‌فرستد، تا
        // همان فیلترِ zdp_otp_login_pending_booking (نگاه کن به
        // Booking::create_booking_from_otp_flow) بعد از ورودِ موفق، نوبت را
        // خودکار ثبت کند — بدون این‌که این ویجت خودش OTP را مدیریت کند.
        var gotoPanelBtn = widget.querySelector('#zdp-bw-goto-panel');
        if (gotoPanelBtn) {
            gotoPanelBtn.addEventListener('click', function () {
                if (loggedIn) return;
                if (!validateSelectionAndName()) return;

                var panelUrl = (typeof ZDP !== 'undefined' && ZDP.panel_url) || '/panel/';
                var url = panelUrl
                    + (panelUrl.indexOf('?') === -1 ? '?' : '&')
                    + 'zdp_service_id=' + encodeURIComponent(currentServiceId())
                    + '&zdp_booking_date=' + encodeURIComponent(selected.date)
                    + '&zdp_booking_time=' + encodeURIComponent(selected.time)
                    + '&zdp_full_name=' + encodeURIComponent(fullNameInput.value.trim());

                window.location.href = url;
            });
        }
    }

    // --- گزارش خطاهای JS به «لاگ امنیتی» پیشخوان ---------------------------
    // هدف: خطاهای واقعی مرورگرِ کاربر (نه فقط چیزی که در کنسول dev دیده
    // می‌شود) در همان audit_log سمت سرور ثبت شود تا بدون دسترسی به کنسول
    // مرورگر کاربر، بشود ریشه‌ی باگ را در پیشخوان دید.
    //
    // سقف سمت کلاینت (جدا از rate-limit سمت سرور در Client_Error_Log):
    // حداکثر ۵ گزارش در هر بارگذاری صفحه. بدون این سقف، یک خطای داخل حلقه
    // (مثلاً یک رندر بازگشتی خراب) می‌تواند صدها request در ثانیه بفرستد و
    // خودش تبدیل به یک مشکل کارایی/شبکه‌ی جدید شود، جدا از باگ اصلی.
    var errorReportCount = 0;
    var MAX_ERROR_REPORTS = 5;

    function reportClientError(fields) {
        if (errorReportCount >= MAX_ERROR_REPORTS) {
            return;
        }
        errorReportCount += 1;

        post('zdp_log_client_error', {
            message:    (fields.message || '').slice(0, 500),
            source:     (fields.source || '').slice(0, 500),
            line:       fields.line || 0,
            col:        fields.col || 0,
            stack:      (fields.stack || '').slice(0, 2000),
            page_url:   window.location.href,
            user_agent: navigator.userAgent || '',
        });
        // عمداً بدون .then/.catch اضافه: post() خودش catch دارد، و پاسخ این
        // endpoint (موفق/rate-limited) برای رفتار صفحه اهمیتی ندارد.
    }

    window.addEventListener('error', function (event) {
        // «Script error.» بدون فایل/خط مربوط به اسکریپت cross-origin (بدون
        // crossorigin attribute) است — هیچ جزئیات مفیدی ندارد و صرفاً لاگ
        // را با نویز پر می‌کند.
        if (event.message === 'Script error.' && !event.filename) {
            return;
        }

        reportClientError({
            message: event.message,
            source:  event.filename || '',
            line:    event.lineno,
            col:     event.colno,
            stack:   event.error && event.error.stack ? event.error.stack : '',
        });
    });

    window.addEventListener('unhandledrejection', function (event) {
        var reason = event.reason;
        var message = reason && reason.message ? reason.message : String(reason);
        var stack = reason && reason.stack ? reason.stack : '';

        reportClientError({ message: 'Unhandled promise rejection: ' + message, stack: stack });
    });
})();
