/* ── Psy Quiz Frontend JS — v3.3.6 ────────────────────────────────────── */
(function ($) {
    'use strict';

    var wrap        = null;
    var token       = '';
    var isAnimating = false; // FIX: قفل انیمیشن — جلوگیری از کلیک سریع Next/Prev
    var totalQ      = 0;
    var currentQ    = 1;
    var answers     = {};
    var maxReached  = 1;
    var submitted   = false;
    var direction   = 1; // +1 = forward, -1 = backward

    // ── Auto-save ─────────────────────────────────────────────────────────
    var saveTimer   = null;
    var SAVE_DELAY  = 2000; // ذخیره ۲ ثانیه پس از آخرین تغییر

    function scheduleSave() {
        clearTimeout( saveTimer );
        saveTimer = setTimeout( saveDraft, SAVE_DELAY );
    }

    function saveDraft() {
        if ( submitted ) return; // بعد از submit دیگر ذخیره نکن
        if ( Object.keys( answers ).length === 0 ) return; // هنوز چیزی انتخاب نشده

        $.ajax({
            url: PsyQuiz.rest_url + 'draft',
            method: 'POST',
            contentType: 'application/json',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', PsyQuiz.nonce);
            },
            data: JSON.stringify({ token: token, answers: answers, last_qnum: currentQ }),
        });
        // خطای save را سکوت می‌کنیم — برای مراجع critical نیست
    }

    function loadDraft( onFound, onNotFound ) {
        $.ajax({
            url: PsyQuiz.rest_url + 'draft',
            method: 'GET',
            data: { token: token },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', PsyQuiz.nonce);
            },
            success: function (res) {
                if ( res.found && res.answers && Object.keys(res.answers).length > 0 ) {
                    onFound( res );
                } else {
                    onNotFound();
                }
            },
            error: function () { onNotFound(); }
        });
    }

    // ── Init ──────────────────────────────────────────────────────────────
    $(document).ready(function () {
        wrap   = $('#psy-exam-wrap');
        if (!wrap.length) return;

        token  = wrap.data('token');
        totalQ = parseInt(wrap.data('total'), 10);

        // بررسی وجود draft ذخیره‌شده
        loadDraft(
            function (draft) {
                // draft پیدا شد — نمایش دکمه ادامه روی صفحه خوشامد
                var answeredCount = Object.keys(draft.answers).length;
                var pct = Math.round( answeredCount / totalQ * 100 );
                var $resumeBtn = $(
                    '<button id="psy-btn-resume" class="psy-btn psy-btn-resume">' +
                    '↩ ادامه آزمون (' + pct + '٪ تکمیل‌شده)' +
                    '</button>'
                );
                $('#psy-btn-start').after( $resumeBtn );

                $resumeBtn.on('click', function () {
                    // بارگذاری پاسخ‌های قبلی
                    answers     = {};
                    var rawAns  = draft.answers;
                    $.each( rawAns, function(k, v) {
                        answers[ parseInt(k, 10) ] = parseInt(v, 10);
                    });
                    maxReached  = draft.last_qnum;

                    $('#psy-exam-header').removeClass('psy-header-hidden').addClass('psy-header-visible');
                    // آپدیت progress bar
                    var p = ( maxReached / totalQ ) * 100;
                    $('#psy-progress-bar').css('width', p + '%');
                    $('#psy-q-current').text( draft.last_qnum );

                    showScreen('quiz');
                    showQuestion( draft.last_qnum );
                });

                // دکمه شروع از نو هم باقی می‌ماند
                $('#psy-btn-start').on('click', function () {
                    answers = {}; maxReached = 1; // reset
                    $('#psy-exam-header').removeClass('psy-header-hidden').addClass('psy-header-visible');
                    showScreen('quiz');
                    showQuestion(1);
                });
            },
            function () {
                // draft نبود — رفتار معمولی
                $('#psy-btn-start').on('click', function () {
                    $('#psy-exam-header').removeClass('psy-header-hidden').addClass('psy-header-visible');
                    showScreen('quiz');
                    showQuestion(1);
                });
            }
        );

        wrap.on('change', 'input[type=radio]', function () {
            var qnum = parseInt($(this).closest('.psy-question-card').data('qnum'), 10);
            answers[qnum] = parseInt($(this).val(), 10);
            $(this).closest('.psy-question-card').find('.psy-btn-next, .psy-btn-submit').prop('disabled', false);
            scheduleSave(); // auto-save با تأخیر
        });

        wrap.on('click', '.psy-btn-next', function () {
            if (isAnimating) return; // FIX: قفل انیمیشن
            direction = 1;
            showQuestion(parseInt($(this).data('qnum'), 10) + 1);
        });

        wrap.on('click', '.psy-btn-prev', function () {
            if (isAnimating) return; // FIX: قفل انیمیشن
            direction = -1;
            showQuestion(parseInt($(this).data('qnum'), 10) - 1);
        });

        // BUG-12: disabled فوری برای جلوگیری از double-tap موبایل
        wrap.on('click', '.psy-btn-submit', function () {
            $(this).prop('disabled', true);
            submitQuiz();
        });
    });

    // ── نمایش سوال با انیمیشن جهت‌دار ────────────────────────────────────
    function showQuestion(num) {
        if (isAnimating) return; // FIX: دوبار صدا زده نشود
        isAnimating = true;

        var prev = currentQ;
        currentQ = num;

        var prevCard = $('.psy-question-card[data-qnum="' + prev + '"]');
        var nextCard = $('.psy-question-card[data-qnum="' + num  + '"]');

        var exitClass  = direction > 0 ? 'psy-exit-left'  : 'psy-exit-right';
        var enterClass = direction > 0 ? 'psy-enter-right': 'psy-enter-left';

        // BUG-FE1 fix: اگر prev === num (اولین فراخوانی) exit animation اجرا نشود
        // قبلاً prevCard و nextCard یکی بودند → exit animation سوال را پنهان می‌کرد
        if ( prev !== num ) {
            // خروج کارت فعلی
            prevCard.addClass(exitClass);
            setTimeout(function () {
                prevCard.hide().removeClass(exitClass);
            }, 220);
        } else {
            // اولین بار: هیچ کارتی برای exit وجود ندارد
            isAnimating = false; // قفل را زودتر آزاد کن تا enter بدون تاخیر کار کند
        }

        // ورود کارت جدید
        nextCard.addClass(enterClass).show();
        setTimeout(function () {
            nextCard.addClass('psy-enter-active');
        }, 10);
        setTimeout(function () {
            nextCard.removeClass(enterClass + ' psy-enter-active');
            isAnimating = false; // FIX: آزاد کردن قفل پس از اتمام انیمیشن
        }, 260);

        // بازیابی پاسخ قبلی — BUG-10: مقایسه عددی برای مقدار ۰
        if (answers[num] !== undefined) {
            nextCard.find('input[type="radio"]').filter(function () {
                return parseFloat($(this).val()) === parseFloat(answers[num]);
            }).prop('checked', true);
            nextCard.find('.psy-btn-next, .psy-btn-submit').prop('disabled', false);
        } else {
            nextCard.find('input[type="radio"]').prop('checked', false);
            nextCard.find('.psy-btn-next, .psy-btn-submit').prop('disabled', true);
        }

        // پیشرفت یک‌طرفه
        if (num > maxReached) maxReached = num;
        var pct = (maxReached / totalQ) * 100;
        $('#psy-progress-bar').css('width', pct + '%');
        $('#psy-q-current').text(num);
    }

    // ── ثبت آزمون ─────────────────────────────────────────────────────────
    function submitQuiz() {
        if (submitted) return;

        var answeredKeys = Object.keys(answers).map(Number).sort(function(a,b){return a-b;});
        var allKeys      = [];
        wrap.find('.psy-question-card').each(function(){
            allKeys.push( parseInt($(this).data('qnum'), 10) );
        });
        allKeys.sort(function(a,b){return a-b;});

        var unanswered = allKeys.filter(function(k){ return answers[k] === undefined; });
        if (unanswered.length > 0) {
            showToast('سوال‌های بدون پاسخ: ' + unanswered.join('، '));
            direction = -1;
            showQuestion(unanswered[0]);
            wrap.find('.psy-btn-submit').prop('disabled', false);
            return;
        }

        submitted = true;
        clearTimeout( saveTimer ); // دیگر auto-save نکن
        showScreen('loading');

        $.ajax({
            url: PsyQuiz.rest_url + 'submit',
            method: 'POST',
            contentType: 'application/json',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', PsyQuiz.nonce);
            },
            data: JSON.stringify({ token: token, answers: answers }),
            success: function (res) {
                if (res.success) {
                    $('#psy-progress-bar').css('width', '100%');
                    showScreen('result');
                    $('html, body').animate({ scrollTop: wrap.offset().top - 20 }, 300);
                } else {
                    submitted = false;
                    showError(res.error || 'خطا در ثبت پاسخ‌ها.');
                }
            },
            error: function (xhr) {
                submitted = false;
                var msg = (xhr.responseJSON && xhr.responseJSON.error)
                    ? xhr.responseJSON.error
                    : 'خطای اتصال به سرور. لطفاً دوباره امتحان کنید.';
                showError(msg);
            }
        });
    }

    // ── Toast برای خطاهای غیربحرانی (جایگزین alert) ─────────────────────
    function showToast(msg) {
        var toast = $('#psy-toast');
        if (!toast.length) {
            toast = $('<div id="psy-toast"></div>').appendTo(wrap);
        }
        toast.text(msg).addClass('psy-toast-show');
        setTimeout(function () { toast.removeClass('psy-toast-show'); }, 3200);
    }

    // ── کمکی‌ها ───────────────────────────────────────────────────────────
    function showScreen(name) {
        $('.psy-screen').removeClass('active').hide();
        $('#psy-screen-' + name).addClass('active').show();
    }

    function showError(msg) {
        $('#psy-error-msg').text(msg);
        showScreen('error');
    }

})(jQuery);
