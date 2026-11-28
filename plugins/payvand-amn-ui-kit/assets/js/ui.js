/**
 * پیوند امن — کیت رابط کاربری · ui.js
 * نسخه 1.2.0 · بدون وابستگی (no jQuery)
 *
 * امکانات:
 *  - انتخاب حالت رنگ (light/dark/auto) و ذخیره در localStorage
 *  - تب‌های ARIA با ناوبری کیبورد
 *  - باز/بسته کردن <dialog> (مدال و کلیک روی پس‌زمینه)
 *  - توست با aria-live
 *  - فیلتر آنی جدول و فهرست با نرمال‌سازی حروف و ارقام فارسی [data-pui-filter-target]
 *  - کپی سریع به کلیپ‌بورد [data-pui-copy]
 *  - پرکردن سریع فیلدها [data-pui-fill]
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'pui-theme';
    var root = document.documentElement;

    /* ── نرمال‌سازی متن و ارقام فارسی/عربی ────────────── */
    function normalizeSearch(value) {
        return String(value || '')
            .replace(/[\u06F0-\u06F9]/g, function (d) { return String(d.charCodeAt(0) - 0x06F0); })
            .replace(/[\u0660-\u0669]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); })
            .replace(/\u064A/g, '\u06CC')
            .replace(/\u0643/g, '\u06A9')
            .replace(/\u200C/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    function toPersianDigits(num) {
        var digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return String(num).replace(/\d/g, function (d) { return digits[Number(d)]; });
    }

    /* ── حالت رنگ ─────────────────────────────────────── */
    function systemPrefersDark() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function resolveTheme(value) {
        if (value === 'auto') {
            return systemPrefersDark() ? 'dark' : 'light';
        }
        return value === 'dark' ? 'dark' : 'light';
    }

    function applyTheme(value) {
        var resolved = resolveTheme(value);
        root.setAttribute('data-pui-theme', resolved);
        root.setAttribute('data-pui-theme-choice', value);

        document.querySelectorAll('[data-pui-theme-toggle]').forEach(function (btn) {
            var next = resolved === 'dark' ? 'light' : 'dark';
            btn.setAttribute('aria-label', next === 'dark' ? 'تغییر به حالت تاریک' : 'تغییر به حالت روشن');
            btn.setAttribute('aria-pressed', resolved === 'dark' ? 'true' : 'false');
            var labelEl = btn.querySelector('[data-pui-theme-label]');
            if (labelEl) {
                labelEl.textContent = resolved === 'dark' ? 'حالت روشن' : 'حالت تاریک';
            }
        });
    }

    function readStoredTheme() {
        try {
            return window.localStorage.getItem(STORAGE_KEY) || root.getAttribute('data-pui-theme-choice') || 'light';
        } catch (e) {
            return 'light';
        }
    }

    function setTheme(value) {
        try {
            window.localStorage.setItem(STORAGE_KEY, value);
        } catch (e) {
            /* حالت خصوصی مرورگر */
        }
        applyTheme(value);
    }

    applyTheme(readStoredTheme());

    if (window.matchMedia) {
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        var onChange = function () {
            if ((root.getAttribute('data-pui-theme-choice') || 'auto') === 'auto') {
                applyTheme('auto');
            }
        };
        if (mq.addEventListener) {
            mq.addEventListener('change', onChange);
        } else if (mq.addListener) {
            mq.addListener(onChange);
        }
    }

    /* ── کلیک‌های عمومی (تم، مدال، کپی، پرکردن سریع، توست) ── */
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-pui-theme-toggle]');
        if (toggle) {
            var current = root.getAttribute('data-pui-theme') === 'dark' ? 'dark' : 'light';
            setTheme(current === 'dark' ? 'light' : 'dark');
            return;
        }

        var opener = event.target.closest('[data-pui-open]');
        if (opener) {
            var dlg = document.getElementById(opener.getAttribute('data-pui-open'));
            if (dlg && typeof dlg.showModal === 'function') {
                dlg.showModal();
            }
            return;
        }

        var closer = event.target.closest('[data-pui-close]');
        if (closer) {
            var open = closer.closest('dialog');
            if (open) { open.close(); }
            return;
        }

        var copier = event.target.closest('[data-pui-copy]');
        if (copier) {
            var textToCopy = copier.getAttribute('data-pui-copy');
            if (!textToCopy && copier.hasAttribute('data-pui-copy-target')) {
                var src = document.querySelector(copier.getAttribute('data-pui-copy-target'));
                if (src) { textToCopy = 'value' in src ? src.value : src.textContent; }
            }
            if (textToCopy) {
                copyText(textToCopy.trim(), copier);
            }
            return;
        }

        var filler = event.target.closest('[data-pui-fill]');
        if (filler) {
            var targetSelector = filler.getAttribute('data-pui-fill');
            var input = document.querySelector(targetSelector);
            if (!input) { return; }

            var fillValue = filler.getAttribute('data-pui-value') || '';
            if (filler.getAttribute('data-pui-today') === 'iso') {
                fillValue = new Date().toISOString().slice(0, 10);
            }
            input.value = fillValue;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            input.focus();

            var group = filler.closest('.pui-slot-pills');
            if (group) {
                group.querySelectorAll('.pui-slot-pill').forEach(function (btn) {
                    var active = btn === filler;
                    btn.classList.toggle('is-active', active);
                    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            }
            return;
        }

        var trigger = event.target.closest('[data-pui-toast]');
        if (trigger) {
            toast(trigger.getAttribute('data-pui-toast') || '');
            return;
        }

        var busyBtn = event.target.closest('[data-pui-busy]');
        if (busyBtn) {
            busyBtn.setAttribute('aria-busy', 'true');
            window.setTimeout(function () { busyBtn.removeAttribute('aria-busy'); }, 8000);
        }
    });

    /* بستن <dialog> هنگام کلیک روی پس‌زمینه */
    document.addEventListener('mousedown', function (event) {
        if (event.target && event.target.tagName === 'DIALOG' && event.target.open) {
            var rect = event.target.getBoundingClientRect();
            var inside = (
                event.clientX >= rect.left &&
                event.clientX <= rect.right &&
                event.clientY >= rect.top &&
                event.clientY <= rect.bottom
            );
            if (!inside) {
                event.target.close();
            }
        }
    });

    /* ── کپی امن به کلیپ‌بورد ───────────────────────────── */
    function copyText(text, triggerEl) {
        var finish = function () {
            if (triggerEl) {
                triggerEl.setAttribute('data-copied', 'true');
                window.setTimeout(function () { triggerEl.removeAttribute('data-copied'); }, 1800);
            }
            toast('در کلیپ‌بورد کپی شد.');
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(finish, function () {
                fallbackCopy(text);
                finish();
            });
        } else {
            fallbackCopy(text);
            finish();
        }
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.insetInlineStart = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        ta.remove();
    }

    /* ── تب‌ها ────────────────────────────────────────── */
    document.querySelectorAll('.pui-tabs').forEach(function (list) {
        var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));
        if (!tabs.length) { return; }

        list.setAttribute('role', 'tablist');

        function activate(tab, focus) {
            tabs.forEach(function (t) {
                var selected = t === tab;
                t.setAttribute('aria-selected', selected ? 'true' : 'false');
                t.setAttribute('tabindex', selected ? '0' : '-1');
                var panel = document.getElementById(t.getAttribute('aria-controls') || '');
                if (panel) { panel.hidden = !selected; }
            });
            if (focus) { tab.focus(); }
        }

        tabs.forEach(function (tab, index) {
            if (!tab.hasAttribute('aria-selected')) {
                tab.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
            }
            if (!tab.hasAttribute('tabindex')) {
                tab.setAttribute('tabindex', tab.getAttribute('aria-selected') === 'true' ? '0' : '-1');
            }

            tab.addEventListener('click', function () { activate(tab, false); });

            tab.addEventListener('keydown', function (event) {
                var current = tabs.indexOf(tab);
                var next = null;
                var rtl = getComputedStyle(list).direction === 'rtl';

                if (event.key === 'ArrowLeft') { next = current + (rtl ? 1 : -1); }
                if (event.key === 'ArrowRight') { next = current + (rtl ? -1 : 1); }
                if (event.key === 'Home') { next = 0; }
                if (event.key === 'End') { next = tabs.length - 1; }

                if (next !== null) {
                    event.preventDefault();
                    var target = tabs[(next + tabs.length) % tabs.length];
                    activate(target, true);
                }
            });
        });
    });

    /* ── فیلتر آنی جدول/فهرست ────────────────────────── */
    function initFilters(scope) {
        (scope || document).querySelectorAll('[data-pui-filter-target]').forEach(function (input) {
            if (input.dataset.puiFilterBound === '1') { return; }
            input.dataset.puiFilterBound = '1';

            var targetSelector = input.getAttribute('data-pui-filter-target');
            var countEl = document.querySelector('[data-pui-filter-count="' + targetSelector + '"]');
            var emptyEl = document.querySelector('[data-pui-filter-empty="' + targetSelector + '"]');

            var runFilter = function () {
                var container = document.querySelector(targetSelector);
                if (!container) { return; }

                var query = normalizeSearch(input.value);
                var rows = container.matches('tbody')
                    ? Array.prototype.slice.call(container.querySelectorAll('tr'))
                    : Array.prototype.slice.call(container.querySelectorAll('[data-filter-item], tbody tr'));

                var total = rows.length;
                var visible = 0;

                rows.forEach(function (row) {
                    if (row.hasAttribute('data-filter-ignore')) {
                        total -= 1;
                        return;
                    }
                    var hay = normalizeSearch(row.getAttribute('data-filter-text') || row.textContent || '');
                    var match = !query || hay.indexOf(query) !== -1;
                    row.hidden = !match;
                    if (match) { visible += 1; }
                });

                if (countEl) {
                    countEl.textContent = query
                        ? (toPersianDigits(visible) + ' از ' + toPersianDigits(total) + ' مورد')
                        : (toPersianDigits(total) + ' مورد');
                }
                if (emptyEl) {
                    emptyEl.hidden = !(total > 0 && visible === 0);
                }
            };

            input.addEventListener('input', runFilter);
            runFilter();
        });
    }

    initFilters(document);

    /* ── توست ─────────────────────────────────────────── */
    function toastHost() {
        var host = document.querySelector('.pui-toasts');
        if (!host) {
            host = document.createElement('div');
            host.className = 'pui-toasts';
            host.setAttribute('aria-live', 'assertive');
            host.setAttribute('aria-atomic', 'false');
            document.body.appendChild(host);
        }
        return host;
    }

    function toast(message) {
        if (!message) { return; }
        var host = toastHost();
        var item = document.createElement('div');
        item.className = 'pui-toast';
        item.setAttribute('role', 'status');
        item.textContent = message;
        host.appendChild(item);
        window.setTimeout(function () {
            item.remove();
        }, 4500);
    }

    /* ── نوار پیشرفت: مقدار از data-pui-progress ──────── */
    function initProgress(scope) {
        (scope || document).querySelectorAll('[data-pui-progress]').forEach(function (bar) {
            var value = parseInt(bar.getAttribute('data-pui-progress'), 10);
            if (isNaN(value)) { return; }
            value = Math.max(0, Math.min(100, value));
            bar.style.inlineSize = value + '%';
        });
    }

    initProgress(document);

    window.PUI = window.PUI || {};
    window.PUI.setTheme = setTheme;
    window.PUI.initProgress = initProgress;
    window.PUI.initFilters = initFilters;
    window.PUI.normalizeSearch = normalizeSearch;
    window.PUI.copyText = copyText;
    window.PUI.theme = function () { return root.getAttribute('data-pui-theme'); };
    window.PUI.toast = toast;
})();
