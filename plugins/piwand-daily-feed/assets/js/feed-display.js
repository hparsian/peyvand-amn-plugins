/* Piwand Daily Feed — feed-display.js */
(function ($) {
    'use strict';

    var cfg = window.PWDF || {};

    /* ── Init ──────────────────────────────────────────── */
    $(function () {
        var $feeds = $('.pwdf-feed');
        if (!$feeds.length) return;

        $feeds.each(function () {
            var $feed    = $(this);
            var $wrapper = $feed.closest('.pwdf-anim-wrapper');
            var mode     = $feed.data('mode') || cfg.display_mode || 'feed';

            var anim = cfg.animation || 'fade';
            $wrapper.addClass('pwdf-anim-' + anim);

            $feed.find('.pwdf-card').each(function () {
                var color = $(this).data('color');
                if (color) {
                    $(this).css('--pwdf-accent', color);
                }
            });

            if (mode === 'slider') {
                // BUG-7: تاخیر کوتاه تا layout کامل شود، سپس slider را init کن
                requestAnimationFrame(function () {
                    initSlider($feed);
                });
            }

            revealCards($feed);
        });

        // Save button
        $(document).on('click', '.pwdf-save', function () {
            var $btn    = $(this);
            var post_id = $btn.data('id');
            var session = getSession();

            $.post(cfg.ajax_url, {
                action:   'pwdf_save_card',
                nonce:    cfg.nonce,
                post_id:  post_id,
                session:  session,
                // 1.1.5: وضعیت فعلی به سرور می‌رود و سرور فقط برعکسش را برمی‌گرداند؛
                // هیچ نوشتنی در دیتابیس انجام نمی‌شود (این endpoint عمومی است).
                saved:    $btn.hasClass('pwdf-saved') ? 1 : 0
            }, function (res) {
                if (res.success) {
                    if (res.data.saved) {
                        $btn.addClass('pwdf-saved').html('🔖 ذخیره شد');
                    } else {
                        $btn.removeClass('pwdf-saved').html('🔖 ذخیره');
                    }
                }
            });
        });

        // Share button
        $(document).on('click', '.pwdf-share', function () {
            var $btn    = $(this);
            var post_id = $btn.data('id');

            $.post(cfg.ajax_url, {
                action:  'pwdf_share_card',
                nonce:   cfg.nonce,
                post_id: post_id
            }, function (res) {
                if (!res.success) return;
                var d = res.data;

                if (navigator.share) {
                    navigator.share({ title: 'پیوند امن', text: d.text, url: d.url })
                        .catch(function () {});
                } else {
                    var text = d.text + '\n' + d.url;
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(text).then(function () {
                            $btn.html('✅ کپی شد');
                            setTimeout(function () { $btn.html('📤 اشتراک‌گذاری'); }, 2000);
                        });
                    } else {
                        var ta = document.createElement('textarea');
                        ta.value = text;
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                        $btn.html('✅ کپی شد');
                        setTimeout(function () { $btn.html('📤 اشتراک‌گذاری'); }, 2000);
                    }
                }
            });
        });
    });

    /* ── Card reveal with stagger ─────────────────────── */
    function revealCards($feed) {
        var anim = cfg.animation || 'fade';
        if (anim === 'none') {
            $feed.find('.pwdf-card').addClass('pwdf-visible');
            return;
        }

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        var $card = $(entry.target);
                        var idx   = parseInt($card.attr('data-pwdf-index') || 0, 10);
                        setTimeout(function () {
                            $card.addClass('pwdf-visible');
                        }, idx * 90);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12 });

            $feed.find('.pwdf-card').each(function (i) {
                $(this).attr('data-pwdf-index', i);
                observer.observe(this);
            });
        } else {
            $feed.find('.pwdf-card').each(function (i) {
                var $c = $(this);
                setTimeout(function () { $c.addClass('pwdf-visible'); }, i * 90);
            });
        }
    }

    /* ── Slider ───────────────────────────────────────── */
    function initSlider($feed) {
        var $track  = $feed.find('.pwdf-slider-track');
        var $cards  = $track.find('.pwdf-card');
        var $dots   = $feed.find('.pwdf-dots');
        var $prev   = $feed.find('.pwdf-prev');
        var $next   = $feed.find('.pwdf-next');
        var total   = $cards.length;
        var current = 0;
        var autoplay = cfg.autoplay === 'true' || cfg.autoplay === true;
        var speed   = parseInt(cfg.autoplay_speed, 10) || 4000;
        var timer   = null;
        var isDragging = false;
        var startX  = 0;
        var dragDelta = 0;

        if (total === 0) return;

        for (var i = 0; i < total; i++) {
            $dots.append('<button class="pwdf-dot" data-idx="' + i + '" aria-label="کارت ' + (i+1) + '"></button>');
        }

        /*
         * BUG-7 FIX: cardWidth را یک‌بار بعد از requestAnimationFrame محاسبه می‌کنیم
         * و در resize دوباره محاسبه می‌کنیم. از getBoundingClientRect در حین
         * transition استفاده نمی‌کنیم.
         */
        var cardWidth = 0;

        function calcCardWidth() {
            var card0 = $cards[0];
            var card1 = $cards[1];
            if (card1) {
                var r0 = card0.getBoundingClientRect();
                var r1 = card1.getBoundingClientRect();
                var w = Math.abs(r1.left - r0.left);
                // اگر layout هنوز آماده نیست (w===0) از offsetWidth fallback کن
                cardWidth = w > 0 ? w : (card0.offsetWidth + 20);
            } else {
                cardWidth = card0.offsetWidth + 20;
            }
        }

        function goTo(idx, instant) {
            idx = ((idx % total) + total) % total;
            current = idx;

            if (cardWidth === 0) calcCardWidth();

            var isRTL = ($feed.css('direction') === 'rtl') ||
                        (document.documentElement.dir === 'rtl');
            var offset = current * cardWidth;
            var translateVal = isRTL ? offset : -offset;

            $track.css({
                transition: instant ? 'none' : 'transform 0.45s cubic-bezier(.4,0,.2,1)',
                transform:  'translateX(' + translateVal + 'px)'
            });

            $dots.find('.pwdf-dot').removeClass('active');
            $dots.find('.pwdf-dot[data-idx="' + current + '"]').addClass('active');
        }

        function startAuto() {
            if (!autoplay) return;
            clearInterval(timer);
            timer = setInterval(function () {
                goTo(current + 1);
            }, speed);
        }

        function stopAuto() { clearInterval(timer); }

        $next.on('click', function () { stopAuto(); goTo(current + 1); startAuto(); });
        $prev.on('click', function () { stopAuto(); goTo(current - 1); startAuto(); });

        $dots.on('click', '.pwdf-dot', function () {
            stopAuto();
            goTo(parseInt($(this).data('idx'), 10));
            startAuto();
        });

        // Touch / drag swipe
        var trackEl = $track[0];
        trackEl.addEventListener('pointerdown', function (e) {
            isDragging = true;
            startX = e.clientX;
            dragDelta = 0;
            stopAuto();
            trackEl.setPointerCapture(e.pointerId);
        });
        trackEl.addEventListener('pointermove', function (e) {
            if (!isDragging) return;
            dragDelta = e.clientX - startX;
        });
        trackEl.addEventListener('pointerup', function () {
            if (!isDragging) return;
            isDragging = false;
            var isRTL = ($feed.css('direction') === 'rtl') ||
                        (document.documentElement.dir === 'rtl');
            if (dragDelta < -50) goTo(isRTL ? current - 1 : current + 1);
            else if (dragDelta > 50) goTo(isRTL ? current + 1 : current - 1);
            startAuto();
        });

        // BUG-9 FIX: اگر چند اسلایدر هم‌زمان در صفحه باشن (مثلاً شورت‌کد + ویجت)،
        // هندلر keydown قبلاً روی document بسته شده بود و فقط visible بودن رو چک می‌کرد —
        // یعنی فلش کیبورد همه‌ی اسلایدرهای دیده‌شده رو هم‌زمان حرکت می‌داد.
        // حالا فقط وقتی اسلایدر هاور/فوکوس شده باشه به کیبورد واکنش نشون می‌ده.
        var isActive = false;
        $feed.on('mouseenter', function () { isActive = true; stopAuto(); })
             .on('mouseleave', function () { isActive = false; startAuto(); })
             .on('focusin', function () { isActive = true; })
             .on('focusout', function () { isActive = false; });

        $(document).on('keydown', function (e) {
            if (isActive && $feed.is(':visible')) {
                var isRTL = ($feed.css('direction') === 'rtl') ||
                            (document.documentElement.dir === 'rtl');
                if (e.key === 'ArrowLeft')  { stopAuto(); goTo(isRTL ? current + 1 : current - 1); startAuto(); }
                if (e.key === 'ArrowRight') { stopAuto(); goTo(isRTL ? current - 1 : current + 1); startAuto(); }
            }
        });

        var resizeTimer;
        $(window).on('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                calcCardWidth();
                goTo(current, true);
            }, 150);
        });

        // Init — cardWidth را قبل از اولین goTo محاسبه کن
        calcCardWidth();
        goTo(0, true);
        startAuto();
    }

    /* ── Session helper ───────────────────────────────── */
    function getSession() {
        var key = 'pwdf_session';
        try {
            var s = sessionStorage.getItem(key);
            if (!s) {
                s = 'sess_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
                sessionStorage.setItem(key, s);
            }
            return s;
        } catch (e) {
            // sessionStorage blocked (Private/Incognito با storage disabled)
            // fallback: session موقت در حافظه — در همین page load کار می‌کند
            if (!getSession._fallback) {
                getSession._fallback = 'sess_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
            }
            return getSession._fallback;
        }
    }

})(jQuery);
