/* ── Psy Quiz Admin JS ─────────────────────────────────────────────────── */
(function ($) {
    'use strict';

    // باگ G: psyCopy باید قبل از document.ready تعریف شود تا اگر کاربر روی
    // دکمه‌های onclick="psyCopy(...)" کلیک کند قبل از اجرای ready، تابع موجود باشد.
    window.psyCopy = function (inputId, btn) {
        var input = document.getElementById(inputId);
        if (!input) return;
        copyText(input.value, $(btn));
    };

    $(document).ready(function () {

        // کپی لینک
        $(document).on('click', '.psy-copy-btn', function () {
            var id    = $(this).data('id');
            var input = document.getElementById('lnk-' + id);
            if (!input) return;
            copyText(input.value, $(this));
        });

        // جستجوی زنده در لیست آزمون‌ها (فرم «مراجع جدید»): با تایپ در کادر
        // جستجو، گزینه‌های غیرمطابق مخفی می‌شوند و optgroup هایی که هیچ
        // گزینه‌ی قابل‌نمایشی ندارند هم مخفی می‌شوند تا فهرست شلوغ نماند.
        $('#quiz_id_search').on('input', function () {
            var term = $(this).val().toString().trim().toLowerCase();
            var $select  = $('#quiz_id');
            var $options = $select.find('option[value!=""]');
            var anyVisible = false;

            $options.each(function () {
                var $opt = $(this);
                var haystack = $opt.attr('data-search') || $opt.text().toLowerCase();
                var match = term === '' || String(haystack).indexOf(term) !== -1;
                $opt.prop('hidden', !match);
                if (match) anyVisible = true;
            });

            $select.find('optgroup').each(function () {
                var $group = $(this);
                var visibleCount = $group.find('option:not([hidden])').length;
                $group.prop('hidden', visibleCount === 0);
            });

            $('#quiz_id_no_results').prop('hidden', anyVisible || term === '');
        });

        // انتخاب سریع آزمون پرکاربرد — toggle: کلیک روی یک دکمه آن آزمون را
        // به انتخاب‌های فعلی select چندگانه اضافه می‌کند، کلیک دوباره حذفش
        // می‌کند؛ سایر آزمون‌های از قبل انتخاب‌شده (چه با دکمه، چه با
        // Ctrl+کلیک در لیست) دست‌نخورده می‌مانند.
        $(document).on('click', '.psy-quick-pick', function () {
            var val = String( $(this).data('value') );
            var $select = $('#quiz_id');
            var current = $select.val() || [];
            var idx = current.indexOf(val);

            if ( idx === -1 ) {
                current.push(val);
            } else {
                current.splice(idx, 1);
            }

            $select.val(current).trigger('change');
        });

        // وقتی select تغییر کرد، هایلایت همه‌ی دکمه‌هایی که آزمونشان الان
        // در لیست انتخاب شده است (نه فقط یکی، چون select چندگانه است)
        // #OE1 fix: به جای selector رشته‌ای (خطر injection)، از .filter() با مقایسه مستقیم استفاده می‌کنیم
        $('#quiz_id').on('change', function () {
            var selected = $(this).val() || [];
            $('.psy-quick-pick').css({ 'background': '', 'border-color': '', 'color': '', 'font-weight': '' });
            $('.psy-quick-pick').filter(function () {
                return selected.indexOf( String( $(this).data('value') ) ) !== -1;
            }).css({
                'background': '#2b6cb0', 'border-color': '#2b6cb0', 'color': '#fff', 'font-weight': 'bold'
            });
        });

        // ── جست‌وجوی خودکار نام مراجع بر اساس شماره موبایل ──────────────
        // اگر همین شماره قبلاً در پنل مراجعان یا پرونده مراجعین ثبت شده،
        // نام آن پیشنهاد می‌شود — فقط وقتی فیلد «نام مراجع» هنوز خالی است
        // (هیچ‌وقت چیزی که خودِ درمانگر تایپ کرده را بازنویسی نمی‌کند).
        var $clientPhone = $('#client_phone');
        var $clientName  = $('#client_name');
        var psyLookupTimer = null;

        if ( $clientPhone.length && $clientName.length && typeof PsyClientLookup !== 'undefined' ) {
            $clientPhone.on('input', function () {
                var phone = $(this).val().trim();
                clearTimeout(psyLookupTimer);

                if ( ! /^09[0-9]{9}$/.test(phone) || $clientName.val().trim() !== '' ) {
                    return;
                }

                psyLookupTimer = setTimeout(function () {
                    $.post(ajaxurl, {
                        action: 'pas_core_lookup_client_name',
                        nonce: PsyClientLookup.nonce,
                        phone: phone
                    }).done(function (res) {
                        if ( res && res.success && res.data.name && $clientName.val().trim() === '' ) {
                            $clientName.val(res.data.name).css('background', '#fffbe6');
                            setTimeout(function () { $clientName.css('background', ''); }, 2000);
                        }
                    });
                }, 500);
            });
        }

    });

    function copyText(text, $btn) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () {
                flash($btn, '✅ کپی شد!');
            });
        } else {
            // fallback
            var tmp = document.createElement('textarea');
            tmp.value = text;
            tmp.style.position = 'fixed';
            tmp.style.opacity  = '0';
            document.body.appendChild(tmp);
            tmp.select();
            document.execCommand('copy');
            document.body.removeChild(tmp);
            flash($btn, '✅ کپی شد!');
        }
    }

    function flash($btn, msg) {
        var orig = $btn.text();
        $btn.text(msg).prop('disabled', true);
        setTimeout(function () { $btn.text(orig).prop('disabled', false); }, 2000);
    }

})(jQuery);
