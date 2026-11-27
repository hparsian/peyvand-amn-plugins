/* ACO Content Writer - Admin JS | v1.9.8 | WP 7.0 Compatible */

// WP 7.0 COMPAT: جلوگیری از block شدن ACO توسط خطاهای promise خارجی
// WP 7.0 scriptهای زیر را روی همه صفحات ادمین لود می‌کند:
//   - editor-mcp / wp-editor-mcp  → خطای "Angie is not available"
//   - wp-ai-client / wp-connectors → خطای "waitForReady"
//   - wp-abilities                 → خطای provider initialization
// این handler خارج از IIFE است تا قبل از همه چیز اجرا شود.
window.addEventListener('unhandledrejection', function(e) {
    if ( e && e.reason && typeof e.reason.message === 'string' ) {
        var msg = e.reason.message;
        if (
            msg.indexOf('Angie') !== -1 ||
            msg.indexOf('waitForReady') !== -1 ||
            msg.indexOf('editor-mcp') !== -1 ||
            msg.indexOf('wp-ai-client') !== -1 ||
            msg.indexOf('AI provider') !== -1 ||
            msg.indexOf('Connector') !== -1 ||
            msg.indexOf('Abilities') !== -1
        ) {
            e.preventDefault();
        }
    }
});

(function($) {
    'use strict';

    // ─── HTML escape helper ────────────────────────────────────────
    function escapeHtml( str ) {
        if ( typeof str !== 'string' ) return String( str );
        return str
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#039;' );
    }

    // ─── هشدار تکراری در نتیجه تحقیق ──────────────────────────────
    function showDuplicateWarning( duplicate ) {
        if ( ! duplicate ) return;
        var msg = duplicate.exact
            ? '⚠️ مقاله‌ای با همین عنوان وجود دارد: '
            : '⚠️ مقاله مشابهی یافت شد: ';
        var editUrl = ACO.admin_url + '?post=' + duplicate.id + '&action=edit';
        appendLogHtml( escapeHtml(msg) + '<a href="' + escapeHtml(editUrl) + '" target="_blank">' + escapeHtml(duplicate.title) + ' (#' + parseInt(duplicate.id, 10) + ')</a>' );
    }

    // ─── Toggle بخش‌های provider ───────────────────────────────────
    function toggleProviderSections() {
        var provider = $('#aco-provider').val();
        $('#aco-section-openrouter').toggle(provider === 'openrouter');
        $('#aco-section-deepseek').toggle(provider === 'deepseek');
        $('#aco-section-openai').toggle(provider === 'openai');
        $('#aco-section-gemini').toggle(provider === 'gemini');
        $('#aco-section-anthropic').toggle(provider === 'anthropic');
    }

    // ─── Toggle «حالت فقط رایگان» ────────────────────────────────────
    // این فقط UI را هماهنگ می‌کند — enforcement واقعی سمت سرور (PHP) است،
    // پس حتی اگر کاربر با ابزارهای مرورگر این غیرفعال‌سازی را دور بزند،
    // درخواست پولی هیچ‌وقت ارسال نمی‌شود.
    function toggleFreeOnlyMode() {
        var isFree = $('#aco-free-only-mode').is(':checked');
        var $wrap  = $('#aco-paid-providers-wrap');

        if (isFree) {
            $wrap.css({ opacity: 0.45, pointerEvents: 'none' });
            $wrap.find('select, input').prop('disabled', true);
        } else {
            $wrap.css({ opacity: 1, pointerEvents: 'auto' });
            $wrap.find('select, input').prop('disabled', false);
            toggleProviderSections();
        }
    }

    // ─── اضافه کردن خط به لاگ ───────────────────────────────────────
    // FIX BUG-2: از $('<div>').text() استفاده می‌کنیم تا XSS از keyword/topic/server errors جلوگیری شود.
    // برای HTML امن (لینک duplicate که با escapeHtml ساخته شده) از appendLogHtml استفاده کنید.
    function appendLog(msg) {
        var $log = $('#aco-log-output');
        $log.append($('<div>').text(String(msg)));
        $log.scrollTop($log[0].scrollHeight);
    }

    function appendLogHtml(html) {
        var $log = $('#aco-log-output');
        $log.append($('<div>').html(html));
        $log.scrollTop($log[0].scrollHeight);
    }

    // ─── نمایش نتیجه نهایی ─────────────────────────────────────────
    function showResult(data) {
        var $card = $('#aco-result-card');
        var $content = $('#aco-result-content');

        var headings = (data.headings || []).map(function(h) {
            return '<li>' + escapeHtml(h) + '</li>';
        }).join('');

        var html = '<div class="aco-result-meta">' +
            '📌 شناسه پست: <span>#' + parseInt(data.post_id, 10) + '</span><br>' +
            '🔖 بخش‌های مقاله: <span>' + (data.headings || []).length + ' بخش</span><br>' +
            '📏 تعداد کلمات: <span>' + escapeHtml(data.word_count || '–') + ' کلمه</span><br>' +
            '🔤 توکن مصرف‌شده: <span>' + escapeHtml(data.total_tokens || '–') + '</span>' +
            '</div>';

        // ─── امتیاز سئو ───
        if (typeof data.seo_score !== 'undefined') {
            var scoreClass = data.seo_score >= 80 ? 'good' : (data.seo_score >= 50 ? 'mid' : 'low');
            html += '<div class="aco-seo-score aco-seo-score--' + scoreClass + '">' +
                '<span class="aco-seo-score-num">' + escapeHtml(data.seo_score) + '</span>' +
                '<span class="aco-seo-score-label">امتیاز سئو از ۱۰۰ &nbsp;·&nbsp; تراکم کلمه کلیدی: ' + escapeHtml(data.keyword_density || 0) + '٪</span>' +
                '</div>';
        }

        // ─── چک‌لیست سئو ───
        if (data.seo_checks && data.seo_checks.length) {
            var checksHtml = data.seo_checks.map(function(c) {
                var icon, cls;
                if ( c.pass === null || c.pass === undefined ) {
                    icon = '⬜'; cls = 'skip';
                } else if ( c.pass ) {
                    icon = '✅'; cls = 'pass';
                } else {
                    icon = '⚠️'; cls = 'fail';
                }
                var detail = c.detail ? ' <span class="aco-seo-check-detail">(' + escapeHtml(c.detail) + ')</span>' : '';
                return '<li class="aco-seo-check ' + cls + '">' + icon + ' ' + escapeHtml(c.label) + detail + '</li>';
            }).join('');
            html += '<p class="aco-result-subtitle">چک‌لیست سئو:</p>' +
                    '<ul class="aco-seo-checklist">' + checksHtml + '</ul>';
        }

        // ─── کلمات کلیدی فرعی ───
        if (data.secondary_keywords && data.secondary_keywords.length) {
            var kwHtml = data.secondary_keywords.map(function(k) {
                return '<span class="aco-keyword-chip">' + escapeHtml(k) + '</span>';
            }).join('');
            html += '<p class="aco-result-subtitle">کلمات کلیدی فرعی استفاده‌شده:</p>' +
                    '<div class="aco-keyword-chips">' + kwHtml + '</div>';
        }

        if (headings) {
            html += '<p class="aco-result-subtitle">بخش‌های نوشته شده:</p>' +
                    '<ul style="font-size:13px;color:#334155;padding-right:20px;margin-bottom:16px">' + headings + '</ul>';
        }

        html += '<div class="aco-result-links">' +
            '<a href="' + escapeHtml(data.edit_url) + '" class="btn-edit" target="_blank">✏️ ویرایش مقاله</a>' +
            '<a href="' + escapeHtml(data.view_url) + '" class="btn-view" target="_blank">👁 مشاهده</a>' +
            '</div>';

        // دسته‌بندی و تگ‌های اعمال‌شده
        if ( data.taxonomy && ( data.taxonomy.category || ( data.taxonomy.tags && data.taxonomy.tags.length ) ) ) {
            html += '<div class="aco-taxonomy-result">';
            if ( data.taxonomy.category ) html += '<span>📁 ' + escapeHtml( data.taxonomy.category ) + '</span>';
            if ( data.taxonomy.tags && data.taxonomy.tags.length ) html += ' <span>🔖 ' + data.taxonomy.tags.map( escapeHtml ).join( ' · ' ) + '</span>';
            html += '</div>';
        }

        // alt text tip
        if ( data.alt_text_tip ) {
            html += '<div class="aco-alt-tip">';
            html += '<strong>🖼️ alt text پیشنهادی برای تصویر شاخص:</strong><br>';
            html += '<code>' + escapeHtml( data.alt_text_tip ) + '</code>';
            html += '<p class="aco-alt-tip-note">این متن را هنگام آپلود تصویر شاخص در فیلد «متن جایگزی» وارد کنید.</p>';
            html += '</div>';
        }

        // پیشنهاد لینک داخلی
        if ( data.internal_links && data.internal_links.length ) {
            html += '<div class="aco-internal-links"><strong>🔗 پیشنهاد لینک داخلی:</strong><ul>';
            $.each( data.internal_links, function( i, link ) {
                html += '<li><a href="' + escapeHtml( link.url ) + '" target="_blank">' + escapeHtml( link.title ) + '</a>';
                if ( link.focus_kw ) html += ' <small>(' + escapeHtml( link.focus_kw ) + ')</small>';
                html += '</li>';
            } );
            html += '</ul></div>';
        }

        // لینک‌های داخلی که مدل از لیست settings قرار داده
        if ( data.placed_links && data.placed_links.length ) {
            html += '<div class="aco-internal-links" style="border-color:#10b981"><strong>✅ لینک‌های داخلی قرارگرفته از لیست شما:</strong><ul>';
            $.each( data.placed_links, function( i, link ) {
                html += '<li><a href="' + escapeHtml( link.url ) + '" target="_blank">' + escapeHtml( link.title ) + '</a></li>';
            } );
            html += '</ul></div>';
        }

        $content.html(html);
        $card.slideDown(300);
    }

    // ─── تولید محتوا (دو مرحله جدا برای جلوگیری از قطع‌شدن توسط هاست) ──
    $(document).on('click', '#aco-btn-generate', function() {
        var keyword = $('#aco-keyword').val().trim();
        var topic   = $('#aco-topic').val().trim();
        var status  = $('#aco-status').val();

        if (!keyword || !topic) {
            alert('لطفاً کلمه کلیدی و عنوان مقاله را وارد کنید.');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);
        $btn.find('.btn-text').hide();
        $btn.find('.btn-loading').show();

        // نمایش لاگ
        $('#aco-log-card').slideDown(200);
        $('#aco-result-card').hide();
        $('#aco-log-output').text('');
        $('#aco-result-content').html('');

        appendLog('🚀 شروع فرآیند تولید محتوا...');
        appendLog('🔑 کلمه کلیدی: ' + keyword);
        appendLog('📝 عنوان: ' + topic);
        appendLog('');

        function resetButton() {
            $btn.prop('disabled', false);
            $btn.find('.btn-text').show();
            $btn.find('.btn-loading').hide();
        }

        // نگهداری آخرین context مرحله ۲ برای بازیابی پاسخ در صورت خطای کاذب هاست
        var _lastStage2Context = null;

        function handleAjaxError(xhr, status, error) {
            // FIX: بعضی هاست‌ها بعد از پاسخ موفق JSON، یک warning/notice اضافه می‌کنند.
            // این output اضافی باعث می‌شود jQuery نتواند JSON را parse کند و error callback
            // صدا زده شود — در حالی که مقاله با موفقیت ذخیره شده است.
            // راه‌حل: responseText را برای JSON موفق جستجو می‌کنیم.
            if (status !== 'timeout' && xhr && xhr.responseText) {
                try {
                    var jsonStart = xhr.responseText.indexOf('{"success":true');
                    if (jsonStart === -1) jsonStart = xhr.responseText.indexOf('{"success": true');
                    if (jsonStart !== -1) {
                        // یک JSON موفق در response پیدا شد — آن را parse کن
                        var jsonStr = xhr.responseText.substring(jsonStart);
                        // پیدا کردن انتهای JSON با شمارش براکت‌ها
                        var depth = 0, endIdx = -1;
                        for (var i = 0; i < jsonStr.length; i++) {
                            if (jsonStr[i] === '{') depth++;
                            else if (jsonStr[i] === '}') { depth--; if (depth === 0) { endIdx = i; break; } }
                        }
                        if (endIdx !== -1) {
                            var parsed = JSON.parse(jsonStr.substring(0, endIdx + 1));
                            if (parsed && parsed.success && parsed.data) {
                                // مرحله ۲ موفق بود — نتیجه را نمایش بده
                                if (_lastStage2Context === 'write_save') {
                                    var logs2 = parsed.data.log || [];
                                    logs2.forEach(function(line) { appendLog(line); });
                                    appendLog('');
                                    appendLog('🎉 عملیات با موفقیت انجام شد!');
                                    showResult(parsed.data);
                                } else {
                                    // مرحله ۱ موفق بود اما context از دست رفت — به کاربر اطلاع بده
                                    appendLog('⚠️ هاست یک هشدار غیرضروری اضافه کرد اما عملیات موفق بود.');
                                }
                                resetButton();
                                return;
                            }
                        }
                    }
                } catch(e) { /* JSON parse شکست خورد — به مسیر خطای اصلی ادامه بده */ }
            }

            if (status === 'timeout') {
                appendLog('❌ خطا: زمان انتظار تمام شد. API پاسخ نداد.');
            } else {
                appendLog('❌ خطای شبکه: ' + (error || 'اتصال به سرور قطع شد (ممکن است هاست درخواست را به دلیل محدودیت زمان اجرا متوقف کرده باشد).'));
            }
            resetButton();
        }

        // ─── مرحله ۱: تحقیق و آوتلاین ───
        appendLog('⏳ مرحله ۱ از ۲ — تحقیق و ساخت آوتلاین...');

        $.ajax({
            url: ACO.ajax_url,
            method: 'POST',
            timeout: 295000,
            data: {
                action:       'aco_research',
                nonce:        ACO.nonce,
                keyword:      keyword,
                topic:        topic,
                status:       status,
                scheduled_at: $('#aco-scheduled-at').val() || '',
            },
            success: function(response) {
                if (!response.success) {
                    var logs1 = (response.data && response.data.log) || [];
                    logs1.forEach(function(line) { appendLog(line); });
                    appendLog('❌ خطا: ' + escapeHtml((response.data && (response.data.error || response.data.message)) || 'خطای ناشناخته'));
                    resetButton();
                    return;
                }

                var logs = response.data.log || [];
                logs.forEach(function(line) { appendLog(line); });

                // FIX (سویچ + صف): مدل‌های رایگان شلوغ بودند — به‌جای خطا، مقاله خودکار
                // به صف پردازش سپرده شد. اینجا متوقف می‌شویم، مرحله ۲ اجرا نمی‌شود.
                if (response.data.queued) {
                    appendLog('');
                    appendLog('✅ ' + response.data.message);
                    resetButton();
                    return;
                }

                // هشدار تکراری
                if ( response.data.duplicate ) {
                    showDuplicateWarning( response.data.duplicate );
                }

                // ذخیره taxonomy برای ارسال در مرحله بعد
                var taxonomy = response.data.taxonomy || {};

                // ─── مرحله ۲: نوشتن + سئو + ذخیره ───
                appendLog('⏳ مرحله ۲ از ۲ — در حال نوشتن مقاله کامل...');
                _lastStage2Context = 'write_save'; // FIX: context برای بازیابی خطای کاذب هاست

                $.ajax({
                    url: ACO.ajax_url,
                    method: 'POST',
                    timeout: 295000,
                    data: {
                        action:              'aco_write_save',
                        nonce:               ACO.nonce,
                        keyword:             keyword,
                        topic:               topic,
                        status:              status,
                        scheduled_at:        $('#aco-scheduled-at').val() || '',
                        meta_description:    response.data.meta_description || '',
                        research_tokens:     response.data.tokens || 0,
                        headings:            response.data.headings || [],
                        entities:            response.data.entities || [],
                        secondary_keywords:  response.data.secondary_keywords || [],
                        tax_category:        taxonomy.category || '',
                        tax_tags:            taxonomy.tags || [],
                    },
                    success: function(response2) {
                        if (response2.success) {
                            var logs2 = response2.data.log || [];
                            logs2.forEach(function(line) { appendLog(line); });

                            // FIX (سویچ + صف): مدل‌های رایگان در مرحله نوشتن هم شلوغ بودند —
                            // مقاله به‌جای خطا خودکار به صف پردازش سپرده شد.
                            if (response2.data.queued) {
                                appendLog('');
                                appendLog('✅ ' + response2.data.message);
                                resetButton();
                                return;
                            }

                            // FIX BUG-A1: نمایش هشدار future→draft fallback
                            if (response2.data.status_fallback_reason) {
                                appendLog('⚠️ ' + response2.data.status_fallback_reason);
                            }
                            appendLog('');
                            appendLog('🎉 عملیات با موفقیت انجام شد!');
                            showResult(response2.data);
                        } else {
                            var logs2f = (response2.data && response2.data.log) || [];
                            logs2f.forEach(function(line) { appendLog(line); });
                            appendLog('❌ خطا: ' + escapeHtml((response2.data && (response2.data.error || response2.data.message)) || 'خطای ناشناخته'));
                        }
                        // FIX: resetButton را اینجا فراخوانی می‌کنیم، نه در complete
                        // complete در nested ajax پس از success/error اجرا می‌شود
                        // اما اگر handleAjaxError فراخوانی شود، complete هم اجرا می‌شود
                        // و دوبار resetButton صدا زده می‌شود
                        resetButton();
                    },
                    error: handleAjaxError
                    // توجه: complete حذف شد — resetButton در success و handleAjaxError فراخوانی می‌شود
                });
            },
            error: handleAjaxError
        });
    });

    // ─── زمان‌بندی انتشار: نمایش فیلد تاریخ ────────────────────────
    $(document).on('change', '#aco-status', function() {
        if ( $(this).val() === 'future' ) {
            $('#aco-scheduled-at-field').slideDown(200);
        } else {
            $('#aco-scheduled-at-field').slideUp(200);
        }
    });

    // ─── تست اتصال Search Console ───
    $(document).on('click', '#aco-btn-test-gsc', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $msg = $('#aco-gsc-msg');

        $btn.prop('disabled', true).text('⏳ در حال تست...');

        $.ajax({
            url: ACO.ajax_url,
            method: 'POST',
            data: { action: 'aco_test_gsc_connection', nonce: ACO.nonce },
            success: function(res) {
                if (res.success) {
                    $msg.text(res.data.message).css('color', '#10b981').show();
                } else {
                    $msg.text(res.data.message || 'اتصال ناموفق بود').css('color', '#ef4444').show();
                }
            },
            error: function() {
                $msg.text('خطای شبکه').css('color', '#ef4444').show();
            },
            complete: function() {
                $btn.prop('disabled', false).text('🔎 تست اتصال');
            }
        });
    });

    // ─── ذخیره تنظیمات ─────────────────────────────────────────────
    $(document).on('click', '#aco-btn-save-settings', function() {
        var provider        = $('#aco-provider').val();
        var freeOnlyMode    = $('#aco-free-only-mode').is(':checked') ? 1 : 0;
        var fallbackProvider = $('#aco-fallback-provider').val();
        var openrouterKey   = $('#aco-openrouter-key').val().trim();
        var deepseekKey     = $('#aco-deepseek-key').val().trim();
        var openaiKey       = $('#aco-openai-key').val().trim();
        var geminiKey       = $('#aco-gemini-key').val().trim();
        var anthropicKey    = $('#aco-anthropic-key').val().trim();
        var openrouterModel = $('#aco-openrouter-model').val().trim();
        var deepseekModel   = $('#aco-deepseek-model').val().trim();
        var openaiModel     = $('#aco-openai-model').val().trim();
        var openaiImageModel = $('#aco-openai-image-model').val().trim();
        var geminiModel     = $('#aco-gemini-model').val().trim();
        var anthropicModel  = $('#aco-anthropic-model').val().trim();
        var gutenbergBlocksEnabled = $('#aco-gutenberg-blocks-enabled').is(':checked') ? 1 : 0;
        var defaultStatus   = $('#aco-default-status').val();
        var defaultAuthor   = $('#aco-default-author').val();
        var niche           = $('#aco-niche').val().trim();
        var tone            = $('#aco-tone').val().trim();
        var minWords        = $('#aco-min-words').val();
        var monthlyTokenBudget = $('#aco-monthly-token-budget').val();
        var gscSiteUrl = $('#aco-gsc-site-url').val().trim();
        var gscServiceAccountJson = $('#aco-gsc-service-account').val().trim();
        var featuredImageEnabled = $('#aco-featured-image-enabled').is(':checked') ? 1 : 0;
        var protectedImageKeywords = $('#aco-protected-image-keywords').val();

        // جمع‌آوری صفحات لینک داخلی
        var linkPages = [];
        $('#aco-link-pages-list .aco-link-page-row').each(function() {
            var $row   = $(this);
            var selVal = $row.find('.aco-link-page-select').val();
            var url    = selVal || $row.find('.aco-link-page-url').val().trim();
            var title  = $row.find('.aco-link-page-title').val().trim();
            var desc   = $row.find('.aco-link-page-desc').val().trim();
            if ( url ) linkPages.push({ title: title, url: url, description: desc });
        });

        var $msg = $('#aco-settings-msg');
        $msg.hide().removeClass('success error');

        // اگر ارائه‌دهنده جایگزین همان ارائه‌دهنده اصلی باشد، fallback عملاً بی‌اثر است —
        // فقط هشدار می‌دهیم، ذخیره را متوقف نمی‌کنیم (سرور هم این حالت را نادیده می‌گیرد)
        if (!freeOnlyMode && fallbackProvider && fallbackProvider === provider) {
            $msg.addClass('error').text('⚠️ ارائه‌دهنده جایگزین نمی‌تواند همان ارائه‌دهنده اصلی باشد — یک ارائه‌دهنده دیگر انتخاب کنید یا «هیچکدام» را بگذارید.').fadeIn();
            return;
        }

        $(this).prop('disabled', true).text('در حال ذخیره...');

        $.ajax({
            url: ACO.ajax_url,
            method: 'POST',
            data: {
                action:               'aco_save_settings',
                nonce:                ACO.nonce,
                provider:             provider,
                free_only_mode:       freeOnlyMode,
                fallback_provider:    fallbackProvider,
                openrouter_key:       openrouterKey,
                deepseek_key:         deepseekKey,
                openai_key:           openaiKey,
                gemini_key:           geminiKey,
                anthropic_key:        anthropicKey,
                openrouter_model:     openrouterModel,
                deepseek_model:       deepseekModel,
                openai_model:         openaiModel,
                openai_image_model:   openaiImageModel,
                gemini_model:         geminiModel,
                anthropic_model:      anthropicModel,
                gutenberg_blocks_enabled: gutenbergBlocksEnabled,
                default_status:       defaultStatus,
                default_author:       defaultAuthor,
                niche:                niche,
                tone:                 tone,
                min_words:            minWords,
                monthly_token_budget: monthlyTokenBudget,
                gsc_site_url:         gscSiteUrl,
                gsc_service_account_json: gscServiceAccountJson,
                featured_image_enabled: featuredImageEnabled,
                protected_image_keywords: protectedImageKeywords,
                internal_link_pages:  linkPages,
            },
            success: function(response) {
                if (response.success) {
                    $msg.addClass('success').text(response.data.message).fadeIn();
                } else {
                    $msg.addClass('error').text(response.data.message).fadeIn();
                }
            },
            error: function() {
                $msg.addClass('error').text('خطای شبکه در ذخیره تنظیمات.').fadeIn();
            },
            complete: function() {
                $('#aco-btn-save-settings').prop('disabled', false).text('💾 ذخیره تنظیمات');
            }
        });
    });

    // ─── مدیریت ردیف‌های صفحات لینک داخلی ────────────────────────────
    $(document).on('click', '#aco-btn-add-link-page', function() {
        var tpl = document.getElementById('aco-link-page-row-tpl');
        if ( ! tpl ) return;
        var clone = tpl.content.cloneNode(true);
        $('#aco-link-pages-list').append(clone);
    });

    $(document).on('click', '.aco-link-page-remove', function() {
        $(this).closest('.aco-link-page-row').remove();
    });

    // وقتی از dropdown انتخاب می‌شود، URL را پر کن
    $(document).on('change', '.aco-link-page-select', function() {
        var $row = $(this).closest('.aco-link-page-row');
        var val  = $(this).val();
        if ( val ) {
            $row.find('.aco-link-page-url').val(val);
            // عنوان را از text گزینه انتخابی بردار
            var titleText = $(this).find('option:selected').text();
            if ( ! $row.find('.aco-link-page-title').val() ) {
                $row.find('.aco-link-page-title').val(titleText);
            }
        }
    });

    // ─── تست API ───────────────────────────────────────────────────
    $(document).on('click', '#aco-btn-test-api', function() {
        var $btn = $(this);
        var $msg = $('#aco-settings-msg');
        $msg.hide().removeClass('success error');
        $btn.prop('disabled', true).text('در حال تست...');

        $.ajax({
            url: ACO.ajax_url,
            method: 'POST',
            timeout: 295000,
            data: {
                action:          'aco_test_api',
                nonce:           ACO.nonce,
                provider:        $('#aco-provider').val(),
                openrouter_key:  $('#aco-openrouter-key').val().trim(),
                deepseek_key:    $('#aco-deepseek-key').val().trim(),
                openai_key:      $('#aco-openai-key').val().trim(),
                gemini_key:      $('#aco-gemini-key').val().trim(),
                anthropic_key:   $('#aco-anthropic-key').val().trim(),
            },
            success: function(response) {
                var d;
                var info;
                if (response.success) {
                    d = response.data;
                    info = '<strong>' + escapeHtml(d.message) + '</strong><br>' +
                        '<small>' +
                        '🤖 سرویس: ' + escapeHtml(d.provider) + ' &nbsp;|&nbsp; ' +
                        '📦 مدل: ' + escapeHtml(d.model) + '<br>' +
                        '💬 پاسخ: ' + escapeHtml(d.response) + '<br>' +
                        '🔢 توکن: ' + escapeHtml(d.tokens) +
                        (d.retries > 0 ? ' &nbsp;|&nbsp; 🔁 تلاش مجدد: ' + escapeHtml(d.retries) : '') +
                        '</small>';
                    $msg.addClass('success').html(info).fadeIn();
                } else {
                    d = response.data;
                    info = escapeHtml(d.message) +
                        '<br><small>سرویس: ' + escapeHtml(d.provider || '–') + ' | مدل: ' + escapeHtml(d.model || '–') + '</small>';
                    $msg.addClass('error').html(info).fadeIn();
                }
            },
            error: function(xhr, status) {
                var msg = status === 'timeout'
                    ? '❌ زمان تست تمام شد. API پاسخ نداد.'
                    : '❌ خطای شبکه در تست اتصال.';
                $msg.addClass('error').text(msg).fadeIn();
            },
            complete: function() {
                $btn.prop('disabled', false).text('🔌 تست اتصال');
            }
        });
    });

    // ─── Init ───────────────────────────────────────────────────────
    $(function() {
        if ($('#aco-provider').length) {
            toggleProviderSections();
            $('#aco-provider').on('change', toggleProviderSections);
        }
        if ($('#aco-free-only-mode').length) {
            toggleFreeOnlyMode();
            $('#aco-free-only-mode').on('change', toggleFreeOnlyMode);
        }
    });

    // ─── Rewrite Page (v1.9.0 — bulk table UI) ──────────────────────────
    // BUG-JS-1 FIX: داخل $(function()) قرار می‌گیرد تا DOM حتماً آماده باشد
    $(function() {
    if ( $( '#aco-rw-table' ).length ) {

        var rwModalPid   = 0;
        var rwInProgress = false;

        // ── باز کردن modal با کلیک دکمه فوری ──────────────────────
        $( document ).on( 'click', '.aco-btn-instant-rewrite', function(e) {
            e.stopPropagation();
            var $btn = $( this );
            rwModalPid = parseInt( $btn.data( 'id' ), 10 );
            var title  = $btn.data( 'title' ) || '';

            // reset modal
            $( '#aco-rw-modal-title' ).text( title );
            $( '#aco-rw-modal-idle' ).show();
            $( '#aco-rw-modal-log, #aco-rw-modal-result, #aco-rw-modal-error' ).hide();
            $( '#aco-rw-modal-log-inner' ).html( '' );
            $( '#aco-rw-modal-edit-link, #aco-rw-modal-view-link' ).hide();
            $( '#aco-rw-modal-start' ).prop( 'disabled', false )
                .find( '.btn-text' ).show().end()
                .find( '.btn-loading' ).hide();
            rwInProgress = false;

            // نمایش overlay با flex
            $( '#aco-rw-modal-overlay' ).css( 'display', 'flex' );
            $( 'body' ).css( 'overflow', 'hidden' );
        } );

        // ── بستن modal ──────────────────────────────────────────────
        function closeRwModal() {
            if ( rwInProgress ) return; // در حال پردازش — اجازه بستن نداریم
            $( '#aco-rw-modal-overlay' ).css( 'display', 'none' );
            $( 'body' ).css( 'overflow', '' );
            rwModalPid = 0;
        }
        $( '#aco-rw-modal-close, #aco-rw-modal-close2' ).on( 'click', closeRwModal );
        $( '#aco-rw-modal-overlay' ).on( 'click', function(e) {
            if ( $( e.target ).is( '#aco-rw-modal-overlay' ) ) closeRwModal();
        } );
        $( document ).on( 'keydown', function(e) {
            if ( e.key === 'Escape' ) closeRwModal();
        } );

        // ── helper: اضافه کردن خط به log terminal ──────────────────
        function rwLog( msg, type ) {
            var color = type === 'success' ? '#4ade80' : type === 'error' ? '#f87171' : '#94a3b8';
            var $inner = $( '#aco-rw-modal-log-inner' );
            $inner.append( $( '<div>' ).css( 'color', color ).text( msg ) );
            // اسکرول به پایین
            var el = $inner[0];
            el.scrollTop = el.scrollHeight;
        }

        // ── شروع بهبود فوری ──────────────────────────────────────────
        $( '#aco-rw-modal-start' ).on( 'click', function() {
            if ( ! rwModalPid || rwInProgress ) return;
            rwInProgress = true;

            var $btn = $( this );
            $btn.prop( 'disabled', true )
                .find( '.btn-text' ).hide().end()
                .find( '.btn-loading' ).show();

            $( '#aco-rw-modal-idle' ).hide();
            $( '#aco-rw-modal-result, #aco-rw-modal-error' ).hide();
            $( '#aco-rw-modal-log' ).show();
            $( '#aco-rw-modal-log-inner' ).html( '' );
            $( '#aco-rw-modal-close, #aco-rw-modal-close2' ).prop( 'disabled', true );

            rwLog( '▶ شروع بهبود مقاله #' + rwModalPid + ' ...' );

            // مراحل انیمیشن‌دار
            setTimeout( function() { rwLog( '⏳ مرحله ۱/۳ — خواندن و فشرده‌سازی ساختار مقاله...', '' ); }, 400 );
            var t2 = setTimeout( function() { rwLog( '⏳ مرحله ۲/۳ — ارسال به هوش مصنوعی و بازنویسی...', '' ); }, 2200 );
            var t3 = setTimeout( function() { rwLog( '⏳ مرحله ۳/۳ — بررسی سئو و ذخیره‌سازی...', '' ); }, 20000 );

            $.ajax( {
                url:     ACO.ajax_url,
                method:  'POST',
                timeout: 315000,
                data: {
                    action:  'aco_rewrite',
                    nonce:   ACO.nonce,
                    post_id: rwModalPid,
                },
                success: function( res ) {
                    clearTimeout( t2 ); clearTimeout( t3 );

                    if ( res.success ) {
                        var d = res.data;
                        rwLog( '✔ بازنویسی کامل شد.', 'success' );
                        rwLog( '✔ بررسی سئو انجام شد.', 'success' );
                        rwLog( '✔ مقاله ذخیره شد.', 'success' );

                        // نمایش نتیجه
                        $( '#aco-rw-modal-wordcount' ).text( '📝 ' + ( d.word_count || '—' ) + ' کلمه' );

                        // بج امتیاز سئو
                        if ( ! d.missing_keyword && d.seo_score !== undefined ) {
                            var sc = parseInt( d.seo_score, 10 );
                            var scColor = sc >= 70 ? '#16a34a' : sc >= 40 ? '#d97706' : '#dc2626';
                            var scHtml = '<div style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:10px;border:1px solid ' + scColor + '55;background:' + scColor + '11">' +
                                '<span style="font-size:22px;font-weight:800;color:' + scColor + '">' + sc + '</span>' +
                                '<span style="font-size:12px;color:' + scColor + '">امتیاز سئو از ۱۰۰</span></div>';
                            $( '#aco-rw-modal-score-wrap' ).html( scHtml );
                        } else if ( d.missing_keyword ) {
                            $( '#aco-rw-modal-score-wrap' ).html( '<div style="padding:8px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;font-size:12px;color:#92400e">⚠️ کلمه کلیدی تنظیم نشده — امتیاز سئو محاسبه نشد.</div>' );
                        }

                        // لینک‌های داخلی
                        if ( d.internal_links && d.internal_links.length ) {
                            var linksHtml = '<div style="font-size:12px;font-weight:600;color:#475569;margin-bottom:6px">🔗 پیشنهاد لینک داخلی:</div><ul style="margin:0;padding-right:18px">';
                            $.each( d.internal_links, function( i, lnk ) {
                                linksHtml += '<li style="font-size:12px;margin-bottom:3px"><a href="' + escapeHtml( lnk.url ) + '" target="_blank">' + escapeHtml( lnk.title ) + '</a></li>';
                            } );
                            linksHtml += '</ul>';
                            $( '#aco-rw-modal-links' ).html( linksHtml );
                        }

                        if ( d.placed_links && d.placed_links.length ) {
                            var placedHtml = '<div style="font-size:12px;font-weight:600;color:#10b981;margin-top:10px;margin-bottom:6px">✅ لینک‌های داخلی از لیست شما:</div><ul style="margin:0;padding-right:18px">';
                            $.each( d.placed_links, function( i, lnk ) {
                                placedHtml += '<li style="font-size:12px;margin-bottom:3px"><a href="' + escapeHtml( lnk.url ) + '" target="_blank">' + escapeHtml( lnk.title ) + '</a></li>';
                            } );
                            placedHtml += '</ul>';
                            $( '#aco-rw-modal-links' ).append( placedHtml );
                        }

                        $( '#aco-rw-modal-result' ).show();

                        // دکمه‌های لینک
                        $( '#aco-rw-modal-edit-link' ).attr( 'href', d.edit_url || '#' ).show();
                        $( '#aco-rw-modal-view-link' ).attr( 'href', d.view_url || '#' ).show();

                        // ── checklist سئو ──────────────────────────
                        if ( d.seo_checks && d.seo_checks.length ) {
                            var clHtml = '<div style="font-size:12px;font-weight:600;color:#475569;margin-bottom:6px">چک‌لیست سئو:</div>' +
                                '<ul style="margin:0;padding:0;list-style:none">';
                            $.each( d.seo_checks, function( i, c ) {
                                var icon = c.pass === null || c.pass === undefined ? '⬜' : ( c.pass ? '✅' : '⚠️' );
                                var detail = c.detail ? ' <span style="color:#94a3b8">(' + escapeHtml( c.detail ) + ')</span>' : '';
                                clHtml += '<li style="font-size:12px;padding:3px 0;color:#334155">' + icon + ' ' + escapeHtml( c.label ) + detail + '</li>';
                            } );
                            clHtml += '</ul>';
                            $( '#aco-rw-modal-score-wrap' ).append( '<div style="margin-top:12px">' + clHtml + '</div>' );
                        }

                        // ── taxonomy ───────────────────────────────
                        if ( d.taxonomy && ( d.taxonomy.category || ( d.taxonomy.tags && d.taxonomy.tags.length ) ) ) {
                            var taxHtml = '<div style="display:flex;flex-wrap:wrap;gap:6px;font-size:12px">';
                            if ( d.taxonomy.category ) taxHtml += '<span style="background:#eff6ff;color:#2563eb;border-radius:12px;padding:2px 10px">📁 ' + escapeHtml( d.taxonomy.category ) + '</span>';
                            if ( d.taxonomy.tags && d.taxonomy.tags.length ) {
                                $.each( d.taxonomy.tags, function( i, t ) {
                                    taxHtml += '<span style="background:#f0fdf4;color:#16a34a;border-radius:12px;padding:2px 10px">🔖 ' + escapeHtml( t ) + '</span>';
                                } );
                            }
                            taxHtml += '</div>';
                            $( '#aco-rw-modal-links' ).prepend( taxHtml );
                        }

                        // ── آپدیت جدول — امتیاز + disable دکمه فوری ──
                        var $row = $( '.aco-rw-row[data-id="' + rwModalPid + '"]' );
                        if ( d.seo_score !== undefined ) {
                            var sc2 = parseInt( d.seo_score, 10 );
                            var c2  = sc2 >= 70 ? '#16a34a' : sc2 >= 40 ? '#d97706' : '#dc2626';
                            $row.find( 'td:nth-child(4) span' )
                                .css( { background: c2 + '1a', color: c2, borderColor: c2 + '55' } )
                                .text( sc2 + '/۱۰۰' );
                        }
                        // دکمه فوری رو در جدول disable کن (بهبود انجام شده)
                        $row.find( '.aco-btn-instant-rewrite' )
                            .prop( 'disabled', true )
                            .text( '✔ انجام شد' )
                            .css( 'color', '#16a34a' );

                    } else {
                        var errMsg = ( res.data && res.data.message ) ? res.data.message : 'خطای ناشناخته';
                        rwLog( '✘ ' + errMsg, 'error' );
                        $( '#aco-rw-modal-error' ).text( '❌ ' + errMsg ).show();
                    }
                },
                error: function( xhr ) {
                    clearTimeout( t2 ); clearTimeout( t3 );
                    var msg = xhr.status === 0 ? 'اتصال قطع شد یا زمان انتظار تمام شد.' : 'خطای سرور: ' + xhr.status;
                    rwLog( '✘ ' + msg, 'error' );
                    $( '#aco-rw-modal-error' ).text( '❌ ' + msg ).show();
                },
                complete: function() {
                    rwInProgress = false;
                    $btn.prop( 'disabled', true ) // دیگه نباید دوباره کلیک بشه
                        .find( '.btn-text' ).text( '✔ انجام شد' ).show().end()
                        .find( '.btn-loading' ).hide();
                    $( '#aco-rw-modal-close, #aco-rw-modal-close2' ).prop( 'disabled', false );
                }
            } );
        } );

        // ── انتخاب همه checkbox‌ها ─────────────────────────────────
        $( '#aco-rw-check-all' ).on( 'change', function() {
            var checked = $( this ).prop( 'checked' );
            $( '.aco-rw-cb:not(:disabled)' ).prop( 'checked', checked );
            updateBulkBar();
        } );

        // هر checkbox تکی
        $( document ).on( 'change', '.aco-rw-cb', function() {
            var total   = $( '.aco-rw-cb:not(:disabled)' ).length;
            var checked = $( '.aco-rw-cb:not(:disabled):checked' ).length;
            $( '#aco-rw-check-all' ).prop( 'indeterminate', checked > 0 && checked < total );
            $( '#aco-rw-check-all' ).prop( 'checked', checked === total && total > 0 );
            updateBulkBar();
        } );

        function updateBulkBar() {
            var count = $( '.aco-rw-cb:not(:disabled):checked' ).length;
            var $bar  = $( '#aco-rw-bulk-bar' );
            if ( count > 0 ) {
                $bar.css( 'display', 'flex' );
                $( '#aco-rw-selected-count' ).text( persianNum( count ) + ' مقاله انتخاب‌شده' );
            } else {
                $bar.css( 'display', 'none' );
            }
            $( '#aco-bulk-msg' ).hide().text( '' );
        }

        // تبدیل اعداد به فارسی
        function persianNum( n ) {
            return String( n ).replace( /\d/g, function( d ) {
                return '۰۱۲۳۴۵۶۷۸۹'[ parseInt( d, 10 ) ];
            } );
        }

        // ارسال به صف
        $( '#aco-btn-bulk-queue' ).on( 'click', function() {
            var ids = $( '.aco-rw-cb:not(:disabled):checked' ).map( function() {
                return $( this ).val();
            } ).get();

            if ( ! ids.length ) return;

            var $btn = $( this ).prop( 'disabled', true ).text( '⏳ در حال ارسال...' );
            $( '#aco-bulk-msg' ).hide().text( '' );

            var postData = {
                action: 'aco_queue_bulk_rewrite',
                nonce:  ACO.nonce,
            };
            // ارسال آرایه post_ids[]
            $.each( ids, function( i, id ) {
                postData[ 'post_ids[' + i + ']' ] = id;
            } );

            $.ajax( {
                url:    ACO.ajax_url,
                method: 'POST',
                data:   postData,
                success: function( res ) {
                    var msg = res.data ? res.data.message : ( res.success ? 'انجام شد.' : 'خطا.' );
                    $( '#aco-bulk-msg' ).text( msg ).css( 'color', res.success ? '#16a34a' : '#dc2626' ).show();
                    if ( res.success ) {
                        // checkbox‌های ارسال‌شده را disable کن و نشان بده در صف است
                        // BUG-J1 FIX: ستون «در صف» ستون ۷ (nth-child(7)) است نه td:last-child (ستون ۸ = عملیات)
                        $( '.aco-rw-cb:not(:disabled):checked' ).each( function() {
                            var $cb  = $( this );
                            var $row = $cb.closest( 'tr' );
                            $cb.prop( 'disabled', true ).prop( 'checked', false );
                            $row.find( 'td:nth-child(7)' ).html( '<span style="font-size:12px;background:#fef3c7;color:#d97706;border-radius:12px;padding:2px 10px;font-weight:600">⏳ در صف</span>' );
                            $row.find( '.aco-btn-instant-rewrite' ).prop( 'disabled', true ).attr( 'title', 'در صف است' );
                        } );
                        updateBulkBar();
                        $( '#aco-rw-check-all' ).prop( 'checked', false ).prop( 'indeterminate', false );
                    }
                },
                error: function() {
                    $( '#aco-bulk-msg' ).text( 'خطای شبکه — دوباره تلاش کنید.' ).css( 'color', '#dc2626' ).show();
                },
                complete: function() {
                    $btn.prop( 'disabled', false ).text( '📋 ارسال انتخاب‌شده‌ها به صف بهبود' );
                }
            } );
        } );

        // هایلایت ردیف با کلیک — ستون آخر (عملیات) استثنا است
        $( document ).on( 'click', '.aco-rw-row td:not(:first-child):not(:last-child)', function() {
            var $cb = $( this ).closest( 'tr' ).find( '.aco-rw-cb' );
            if ( ! $cb.prop( 'disabled' ) ) {
                $cb.prop( 'checked', ! $cb.prop( 'checked' ) ).trigger( 'change' );
            }
        } );
    }
    }); // end $(function) — Rewrite Page v1.9.0

    // ─── Queue Page ──────────────────────────────────────────────────
    if ( $( '#aco-btn-queue-add' ).length ) {

        // IMPROVE #2: تب‌بندی تکی / دسته‌جمعی
        $( '#aco-q-tab-single, #aco-q-tab-bulk' ).on( 'click', function() {
            var isBulk = $( this ).attr( 'id' ) === 'aco-q-tab-bulk';
            $( '#aco-q-tab-single' ).css( { background: isBulk ? '#fff' : '#f1f5f9', fontWeight: isBulk ? '400' : '600', color: isBulk ? '#64748b' : '#1e293b' } );
            $( '#aco-q-tab-bulk'   ).css( { background: isBulk ? '#f1f5f9' : '#fff', fontWeight: isBulk ? '600' : '400', color: isBulk ? '#1e293b' : '#64748b' } );
            $( '#aco-q-panel-single' ).toggle( ! isBulk );
            $( '#aco-q-panel-bulk'   ).toggle( isBulk );
            $( '#aco-queue-msg' ).hide();
        } );

        $( '#aco-q-status' ).on( 'change', function() {
            if ( $(this).val() === 'future' ) {
                $( '#aco-q-schedule-wrap' ).slideDown( 200 );
            } else {
                $( '#aco-q-schedule-wrap' ).slideUp( 200 );
            }
        } );

        $( '#aco-btn-queue-add' ).on( 'click', function() {
            var keyword     = $( '#aco-q-keyword' ).val().trim();
            var topic       = $( '#aco-q-topic' ).val().trim();
            var postStatus  = $( '#aco-q-status' ).val();
            var scheduledAt = $( '#aco-q-scheduled-at' ).val();
            var $msg = $( '#aco-queue-msg' );

            if ( ! keyword || ! topic ) {
                $msg.removeClass('success').addClass('error').text('کلمه کلیدی و عنوان الزامی هستند.').show();
                return;
            }

            var $btn = $( this ).prop( 'disabled', true ).text( '⏳ در حال افزودن...' );
            $msg.hide();

            $.ajax( {
                url: ACO.ajax_url,
                method: 'POST',
                data: {
                    action:       'aco_queue_add',
                    nonce:        ACO.nonce,
                    keyword:      keyword,
                    topic:        topic,
                    post_status:  postStatus,
                    scheduled_at: scheduledAt,
                },
                success: function( res ) {
                    if ( res.success ) {
                        $msg.removeClass('error').addClass('success').text( res.data.message ).show();
                        $( '#aco-q-keyword, #aco-q-topic' ).val( '' );
                        setTimeout( function() { location.reload(); }, 1500 );
                    } else {
                        $msg.removeClass('success').addClass('error').text( res.data.message || 'خطا' ).show();
                    }
                },
                error: function() {
                    $msg.removeClass('success').addClass('error').text( 'خطای شبکه.' ).show();
                },
                complete: function() {
                    $btn.prop( 'disabled', false ).text( '📋 افزودن به صف' );
                }
            } );
        } );

        // IMPROVE #2: bulk add — یک به یک به صف اضافه می‌کند با نوار پیشرفت
        $( '#aco-btn-queue-bulk' ).on( 'click', function() {
            var rawLines   = $( '#aco-q-bulk-lines' ).val().trim();
            var postStatus = $( '#aco-q-bulk-status' ).val();
            var $msg       = $( '#aco-queue-msg' );
            var $btn       = $( this );

            if ( ! rawLines ) {
                $msg.removeClass('success').addClass('error').text('لیست خالی است.').show();
                return;
            }

            var lines = rawLines.split( '\n' ).map( function( l ) { return l.trim(); } ).filter( Boolean );
            if ( ! lines.length ) {
                $msg.removeClass('success').addClass('error').text('هیچ خط معتبری یافت نشد.').show();
                return;
            }

            var items = lines.map( function( line ) {
                var parts   = line.split( '|' );
                var keyword = parts[0].trim();
                var topic   = parts[1] ? parts[1].trim() : keyword;
                return { keyword: keyword, topic: topic };
            } ).filter( function( it ) { return it.keyword; } );

            if ( ! items.length ) {
                $msg.removeClass('success').addClass('error').text('هیچ کلمه کلیدی‌ای یافت نشد.').show();
                return;
            }

            $btn.prop( 'disabled', true ).text( '⏳ در حال افزودن...' );
            $msg.hide();
            $( '#aco-bulk-progress' ).show();
            $( '#aco-bulk-bar' ).css( 'width', '0%' );

            var total  = items.length;
            var done   = 0;
            var failed = 0;

            function addNext( idx ) {
                if ( idx >= total ) {
                    var txt = '✅ ' + (total - failed) + ' آیتم اضافه شد';
                    if ( failed ) txt += ' | ❌ ' + failed + ' خطا';
                    $( '#aco-bulk-status-text' ).text( txt );
                    $btn.prop( 'disabled', false ).text( '📋 افزودن همه به صف' );
                    setTimeout( function() { location.reload(); }, 1800 );
                    return;
                }
                var item = items[ idx ];
                $( '#aco-bulk-status-text' ).text( '(' + (idx + 1) + '/' + total + ') در حال افزودن: ' + item.keyword );
                $.ajax( {
                    url: ACO.ajax_url,
                    method: 'POST',
                    data: { action: 'aco_queue_bulk_add', nonce: ACO.nonce, keyword: item.keyword, topic: item.topic, post_status: postStatus },
                    success: function( res ) { if ( ! res.success ) failed++; },
                    error: function() { failed++; },
                    complete: function() {
                        done++;
                        $( '#aco-bulk-bar' ).css( 'width', Math.round( (done / total) * 100 ) + '%' );
                        addNext( idx + 1 );
                    }
                } );
            }

            addNext( 0 );
        } );

        $( document ).on( 'click', '.aco-q-retry, .aco-q-delete', function() {
            var $btn    = $( this );
            var id      = $btn.data( 'id' );
            var action  = $btn.hasClass( 'aco-q-retry' ) ? 'retry' : 'delete';
            var confirm_msg = action === 'delete' ? 'آیتم #' + id + ' حذف شود؟' : '';
            if ( confirm_msg && ! confirm( confirm_msg ) ) return;

            $btn.prop( 'disabled', true );
            $.ajax( {
                url: ACO.ajax_url,
                method: 'POST',
                data: { action: 'aco_queue_action', nonce: ACO.nonce, queue_action: action, item_id: id },
                success: function( res ) {
                    if ( res.success ) {
                        location.reload();
                    } else {
                        alert( res.data.message || 'خطا' );
                        $btn.prop( 'disabled', false );
                    }
                }
            } );
        } );
    }

    // ─── Logs Page ───────────────────────────────────────────────────
    $( document ).on( 'click', '#aco-btn-logs-clear', function() {
        if ( ! confirm( 'همه لاگ‌ها پاک شوند؟' ) ) return;
        var $btn = $( this ).prop( 'disabled', true ).text( '⏳ ...' );
        $.ajax( {
            url: ACO.ajax_url,
            method: 'POST',
            data: { action: 'aco_logs_clear', nonce: ACO.nonce },
            success: function( res ) {
                if ( res.success ) { location.reload(); }
            },
            complete: function() { $btn.prop( 'disabled', false ).text( '🗑 پاک‌سازی همه' ); }
        } );
    } );

    // ─── Crawler Settings Page ────────────────────────────────────────
    // BUG-CRAWLER-UI FIX: قبلاً این هندلرها با $(document).on('click', '#id', ...)
    // (delegated، bubble phase) بایند می‌شدند. در WP 7.0، اسکریپت‌های جدید Command
    // Palette / AI Client روی document یک capture-phase listener دارند که قبل از
    // رسیدن click به سطح document، propagation را متوقف می‌کنند — در نتیجه نه چک‌باکس
    // تیک می‌خورد و نه دکمه‌ها واکنش نشان می‌دادند.
    // راه‌حل: بایند مستقیم روی خودِ المنت (نه delegate روی document) داخل $(function)
    // تا به bubbling تا document نیازی نباشد، + یک listener با capture:true به‌عنوان
    // لایه‌ی دوم اطمینان که زودتر از هر interceptor دیگری اجرا می‌شود.
    $(function() {
        var $crawlerCheckbox = $( '#aco-crawler-enabled' );

        if ( $crawlerCheckbox.length ) {

            // ── نشانگر بصری مستقل از رندر native مرورگر/استایل هسته وردپرس ──
            // به‌جای تکیه بر رندر :checked که در WP 7.0 با CSS/JS هسته تداخل پیدا
            // می‌کرد، یک کلاس را خودمان مستقیماً کنترل می‌کنیم تا همیشه تیک قابل‌مشاهده باشد.
            function syncCrawlerCheckboxVisual() {
                $crawlerCheckbox.closest( 'label' ).toggleClass( 'aco-checked', $crawlerCheckbox.is( ':checked' ) );
            }
            syncCrawlerCheckboxVisual();

            // بایند مستقیم روی خودِ checkbox (نه delegate)
            $crawlerCheckbox.on( 'click change', function() {
                syncCrawlerCheckboxVisual();
            } );

            // لایه‌ی دوم: native listener با capture:true روی خودِ checkbox
            // تا حتی اگر چیزی روی document جلوی bubbling را بگیرد، باز هم اجرا شود
            var crawlerCbEl = $crawlerCheckbox.get( 0 );
            if ( crawlerCbEl && crawlerCbEl.addEventListener ) {
                crawlerCbEl.addEventListener( 'click', syncCrawlerCheckboxVisual, true );
            }

            // اگر label هم کلیک شد ولی native toggle انجام نشد (مثلاً به‌خاطر
            // یک interceptor خارجی)، خودمان به‌صورت دستی toggle می‌کنیم
            $crawlerCheckbox.closest( 'label' ).get( 0 ).addEventListener( 'click', function( e ) {
                if ( e.target === crawlerCbEl ) return; // کلیک مستقیم روی خود checkbox - نیازی به دخالت نیست
                // اگر کلیک روی متن label بود و چیزی جلوی toggle پیش‌فرض را گرفت، خودمان toggle می‌کنیم
                setTimeout( syncCrawlerCheckboxVisual, 0 );
            }, true );
        }

        function aco_saveCrawlerSettings( $btn ) {
            $btn.prop( 'disabled', true ).text( '⏳ در حال ذخیره...' );
            var $msg = $( '#aco-crawler-msg' );

            $.ajax( {
                url: ACO.ajax_url,
                method: 'POST',
                data: {
                    action:           'aco_save_crawler_settings',
                    nonce:            ACO.nonce,
                    enabled:          $( '#aco-crawler-enabled' ).is( ':checked' ) ? '1' : 'false',
                    interval_days:    $( '#aco-crawler-interval-days' ).val(),
                    run_hour:         $( '#aco-crawler-run-hour' ).val(),
                    seo_threshold:    $( '#aco-crawler-seo-threshold' ).val(),
                    min_links:        $( '#aco-crawler-min-links' ).val(),
                    stale_months:     $( '#aco-crawler-stale-months' ).val(),
                    posts_per_night:  $( '#aco-crawler-posts-per-night' ).val(),
                    review_mode:      $( '#aco-crawler-review-mode' ).is( ':checked' ) ? '1' : 'false',
                },
                success: function( res ) {
                    if ( res.success ) {
                        var msg = res.data.message || 'ذخیره شد.';
                        if ( res.data.next_run ) {
                            msg += ' | اجرای بعدی: ' + res.data.next_run;
                        }
                        $msg.text( msg ).css( 'color', '#10b981' ).show();
                    } else {
                        $msg.text( res.data.message || 'خطا در ذخیره' ).css( 'color', '#ef4444' ).show();
                    }
                },
                error: function() {
                    $msg.text( 'خطای شبکه' ).css( 'color', '#ef4444' ).show();
                },
                complete: function() {
                    $btn.prop( 'disabled', false ).text( '💾 ذخیره تنظیمات کرم' );
                }
            } );
        }

        var $saveCrawlerBtn = $( '#aco-btn-save-crawler' );
        if ( $saveCrawlerBtn.length ) {
            $saveCrawlerBtn.on( 'click', function( e ) {
                e.preventDefault();
                aco_saveCrawlerSettings( $( this ) );
            } );
            var saveCrawlerEl = $saveCrawlerBtn.get( 0 );
            if ( saveCrawlerEl && saveCrawlerEl.addEventListener ) {
                saveCrawlerEl.addEventListener( 'click', function( e ) {
                    if ( $( this ).prop( 'disabled' ) ) return;
                    e.preventDefault();
                }, true );
            }
        }

        // ─── Crawler: اجرای دستی (تست بدون نیاز به صبر کردن تا ساعت برنامه‌ریزی‌شده) ───
        function aco_runCrawlerNow( $btn ) {
            if ( ! confirm( 'کرم محتوا همین الان اجرا شود؟ این کار ممکن است چند دقیقه طول بکشد و از API استفاده کند.' ) ) return;

            $btn.prop( 'disabled', true ).text( '⏳ در حال اجرا...' );
            var $msg = $( '#aco-crawler-msg' );

            $.ajax( {
                url: ACO.ajax_url,
                method: 'POST',
                data: { action: 'aco_crawler_run_now', nonce: ACO.nonce },
                success: function( res ) {
                    if ( res.success ) {
                        $msg.text( res.data.message || 'اجرا شد.' ).css( 'color', '#10b981' ).show();
                    } else {
                        $msg.text( res.data.message || 'خطا در اجرا' ).css( 'color', '#ef4444' ).show();
                    }
                },
                error: function() {
                    $msg.text( 'خطای شبکه' ).css( 'color', '#ef4444' ).show();
                },
                complete: function() {
                    $btn.prop( 'disabled', false ).text( '▶️ اجرای دستی الان (تست)' );
                }
            } );
        }

        var $runCrawlerBtn = $( '#aco-btn-run-crawler-now' );
        if ( $runCrawlerBtn.length ) {
            $runCrawlerBtn.on( 'click', function( e ) {
                e.preventDefault();
                aco_runCrawlerNow( $( this ) );
            } );
            var runCrawlerEl = $runCrawlerBtn.get( 0 );
            if ( runCrawlerEl && runCrawlerEl.addEventListener ) {
                runCrawlerEl.addEventListener( 'click', function( e ) {
                    if ( $( this ).prop( 'disabled' ) ) return;
                    e.preventDefault();
                }, true );
            }
        }

        // ─── بانک کلمات کلیدی: ذخیره تنظیمات ───
        function aco_saveKeywordBankSettings( $btn ) {
            $btn.prop( 'disabled', true ).text( '⏳ در حال ذخیره...' );
            var $msg = $( '#aco-kb-msg' );

            $.ajax( {
                url: ACO.ajax_url,
                method: 'POST',
                data: {
                    action:         'aco_save_keyword_bank_settings',
                    nonce:          ACO.nonce,
                    enabled:        $( '#aco-kb-enabled' ).is( ':checked' ) ? '1' : 'false',
                    daily_count:    $( '#aco-kb-daily-count' ).val(),
                    refresh_count:  $( '#aco-kb-refresh-count' ).val(),
                    run_hour:       $( '#aco-kb-run-hour' ).val(),
                    post_status:    $( '#aco-kb-post-status' ).val(),
                },
                success: function( res ) {
                    if ( res.success ) {
                        var msg = res.data.message || 'ذخیره شد.';
                        if ( res.data.next_run ) {
                            msg += ' | اجرای بعدی: ' + res.data.next_run;
                        }
                        $msg.text( msg ).css( 'color', '#10b981' ).show();
                    } else {
                        $msg.text( res.data.message || 'خطا در ذخیره' ).css( 'color', '#ef4444' ).show();
                    }
                },
                error: function() {
                    $msg.text( 'خطای شبکه' ).css( 'color', '#ef4444' ).show();
                },
                complete: function() {
                    $btn.prop( 'disabled', false ).text( '💾 ذخیره تنظیمات' );
                }
            } );
        }

        var $saveKbBtn = $( '#aco-btn-save-kb' );
        if ( $saveKbBtn.length ) {
            $saveKbBtn.on( 'click', function( e ) {
                e.preventDefault();
                aco_saveKeywordBankSettings( $( this ) );
            } );
        }

        // ─── بانک کلمات کلیدی: اجرای دستی ───
        function aco_runKeywordBankNow( $btn ) {
            if ( ! confirm( 'بانک کلمات کلیدی همین الان اجرا شود؟ این کار ممکن است چند دقیقه طول بکشد و از API استفاده کند.' ) ) return;

            $btn.prop( 'disabled', true ).text( '⏳ در حال اجرا...' );
            var $msg = $( '#aco-kb-msg' );

            $.ajax( {
                url: ACO.ajax_url,
                method: 'POST',
                data: { action: 'aco_keyword_bank_run_now', nonce: ACO.nonce },
                success: function( res ) {
                    if ( res.success ) {
                        $msg.text( res.data.message || 'اجرا شد.' ).css( 'color', '#10b981' ).show();
                    } else {
                        $msg.text( res.data.message || 'خطا در اجرا' ).css( 'color', '#ef4444' ).show();
                    }
                },
                error: function() {
                    $msg.text( 'خطای شبکه' ).css( 'color', '#ef4444' ).show();
                },
                complete: function() {
                    $btn.prop( 'disabled', false ).text( '▶️ اجرای دستی الان (بروزرسانی + تولید)' );
                }
            } );
        }

        var $runKbBtn = $( '#aco-btn-run-kb-now' );
        if ( $runKbBtn.length ) {
            $runKbBtn.on( 'click', function( e ) {
                e.preventDefault();
                aco_runKeywordBankNow( $( this ) );
            } );
        }

        // ─── بانک کلمات کلیدی: حذف یک ردیف ───
        $( document ).on( 'click', '.aco-kb-delete-row', function( e ) {
            e.preventDefault();
            var $link = $( this );
            var id = $link.data( 'id' );
            if ( ! confirm( 'این کلمه کلیدی از مخزن حذف شود؟' ) ) return;

            $.ajax( {
                url: ACO.ajax_url,
                method: 'POST',
                data: { action: 'aco_keyword_bank_delete', nonce: ACO.nonce, id: id },
                success: function( res ) {
                    if ( res.success ) {
                        $link.closest( 'tr' ).fadeOut( 200, function() { $( this ).remove(); } );
                    } else {
                        alert( res.data.message || 'حذف نشد.' );
                    }
                },
                error: function() {
                    alert( 'خطای شبکه' );
                }
            } );
        } );

        // ── بازبینی پیشنهادهای بازنویسیِ کرم (تأیید/رد) ──────────────
        $( '.aco-btn-toggle-preview' ).on( 'click', function( e ) {
            e.preventDefault();
            var $item    = $( this ).closest( '.aco-pending-review-item' );
            var $preview = $item.find( '.aco-pending-preview' );
            var isOpen   = $preview.is( ':visible' );
            $preview.slideToggle( 150 );
            $( this ).text( isOpen ? '✏️ نمایش و ویرایش پیش‌نویس' : '🙈 بستن ویرایش' );
        } );

        $( '.aco-btn-approve-rewrite' ).on( 'click', function( e ) {
            e.preventDefault();
            var $btn  = $( this );
            var $item = $btn.closest( '.aco-pending-review-item' );
            var $msg  = $item.find( '.aco-pending-review-msg' );
            var postId = $item.data( 'post-id' );
            // متن textarea را می‌فرستیم — اگر ادمین ویرایش کرده باشد همان نسخه منتشر می‌شود،
            // اگر دست نزده باشد همان محتوای پیشنهادی اصلی است (بدون تغییر)
            var editedContent = $item.find( '.aco-pending-edit-textarea' ).val();

            if ( ! window.confirm( 'این بازنویسی روی سایت منتشر شود؟' ) ) return;

            $item.find( 'button' ).prop( 'disabled', true );
            $btn.text( '⏳ در حال انتشار...' );

            $.ajax( {
                url: ACO.ajax_url,
                method: 'POST',
                data: { action: 'aco_review_approve', nonce: ACO.nonce, post_id: postId, edited_content: editedContent },
                success: function( res ) {
                    if ( res.success ) {
                        $msg.text( '✅ ' + ( res.data.message || 'منتشر شد.' ) ).css( 'color', '#10b981' ).show();
                        setTimeout( function() { location.reload(); }, 900 );
                    } else {
                        $msg.text( '❌ ' + ( res.data.message || 'خطا' ) ).css( 'color', '#ef4444' ).show();
                        $item.find( 'button' ).prop( 'disabled', false );
                        $btn.text( '✅ تأیید و انتشار' );
                    }
                },
                error: function() {
                    $msg.text( '❌ خطای شبکه' ).css( 'color', '#ef4444' ).show();
                    $item.find( 'button' ).prop( 'disabled', false );
                    $btn.text( '✅ تأیید و انتشار' );
                }
            } );
        } );

        $( '.aco-btn-reject-rewrite' ).on( 'click', function( e ) {
            e.preventDefault();
            var $btn  = $( this );
            var $item = $btn.closest( '.aco-pending-review-item' );
            var $msg  = $item.find( '.aco-pending-review-msg' );
            var postId = $item.data( 'post-id' );

            if ( ! window.confirm( 'این پیشنهاد بازنویسی رد شود؟ پست بدون تغییر می‌ماند.' ) ) return;

            $item.find( 'button' ).prop( 'disabled', true );
            $btn.text( '⏳ در حال رد کردن...' );

            $.ajax( {
                url: ACO.ajax_url,
                method: 'POST',
                data: { action: 'aco_review_reject', nonce: ACO.nonce, post_id: postId },
                success: function( res ) {
                    $msg.text( res.success ? '🗑️ رد شد.' : ( '❌ ' + ( res.data.message || 'خطا' ) ) )
                        .css( 'color', res.success ? '#64748b' : '#ef4444' ).show();
                    if ( res.success ) {
                        $item.fadeOut( 300, function() { $( this ).remove(); } );
                    } else {
                        $item.find( 'button' ).prop( 'disabled', false );
                        $btn.text( '❌ رد کردن' );
                    }
                },
                error: function() {
                    $msg.text( '❌ خطای شبکه' ).css( 'color', '#ef4444' ).show();
                    $item.find( 'button' ).prop( 'disabled', false );
                    $btn.text( '❌ رد کردن' );
                }
            } );
        } );
    });

})(jQuery);