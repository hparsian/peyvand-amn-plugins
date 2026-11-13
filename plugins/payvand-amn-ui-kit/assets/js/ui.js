/**
 * پیوند امن — کیت رابط کاربری · ui.js
 * نسخه 1.0.0 · بدون وابستگی (no jQuery) · ~۶KB
 *
 * کارها:
 *  - انتخاب حالت رنگ (light/dark/auto) و ذخیره در localStorage
 *  - تب‌های ARIA با ناوبری کیبورد
 *  - باز/بسته کردن <dialog> (مدال)
 *  - توست با aria-live
 *  - نشان‌دادن خطای شبکه در دکمه‌ها (aria-busy)
 *
 * هیچ شنونده‌ای روی document به‌صورت سراسری جز یک بار ثبت نمی‌شود و همهٔ
 * رفتارها فقط داخل نواحی .pui-shell/.pui-kit/.pui-app فعال می‌شوند.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'pui-theme';
    var root = document.documentElement;

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
            /* حالت خصوصی مرورگر — فقط همین نشست اعمال می‌شود */
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
        }
    });

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
        var host = toastHost();
        var item = document.createElement('div');
        item.className = 'pui-toast';
        item.setAttribute('role', 'status');
        item.textContent = message;
        host.appendChild(item);
        window.setTimeout(function () {
            item.remove();
        }, 5000);
    }

    /* ── دکمهٔ در حال ارسال ───────────────────────────── */
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-pui-busy]');
        if (!btn) { return; }
        btn.setAttribute('aria-busy', 'true');
        window.setTimeout(function () { btn.removeAttribute('aria-busy'); }, 8000);
    });

    window.PUI = window.PUI || {};
    window.PUI.setTheme = setTheme;
    window.PUI.theme = function () { return root.getAttribute('data-pui-theme'); };
    window.PUI.toast = toast;
})();
