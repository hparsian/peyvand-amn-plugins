(function () {
  'use strict';

  function toast(message, type) {
    if (window.PUI && typeof window.PUI.toast === 'function' && type !== 'error') {
      window.PUI.toast(message);
      return;
    }
    var host = document.querySelector('.pas-main');
    if (!host) return;
    var box = document.createElement('div');
    box.className = 'pas-notice pas-notice-' + (type || 'info');
    box.setAttribute('role', 'status');
    var p = document.createElement('p');
    p.textContent = message;
    box.appendChild(p);
    host.insertBefore(box, host.firstChild);
    window.setTimeout(function () {
      box.remove();
    }, 5000);
  }

  /* ── پرش سریع ماژول‌ها (Ctrl+K / /) ──────────────────────── */
  var cmdDialog = document.getElementById('pvn-cmd-dialog');
  var cmdInput = document.getElementById('pvn-cmd-input');
  var cmdList = document.getElementById('pvn-cmd-list');
  var cmdItems = [];

  function initCommandPalette() {
    if (!cmdDialog || !cmdInput || !cmdList) return;
    var links = Array.prototype.slice.call(document.querySelectorAll('.pvn-nav__link'));
    cmdItems = links.map(function (a) {
      var group = a.closest('.pvn-nav__group');
      var groupLabel = group ? (group.querySelector('.pvn-nav__label') || {}).textContent || '' : '';
      return {
        label: (a.querySelector('span') || a).textContent.trim(),
        group: groupLabel.trim(),
        href: a.getAttribute('href'),
        active: a.getAttribute('aria-current') === 'page',
      };
    });
    renderCmdItems('');
  }

  function renderCmdItems(query) {
    if (!cmdList) return;
    var norm = window.PUI && window.PUI.normalizeSearch
      ? window.PUI.normalizeSearch(query)
      : String(query || '').trim().toLowerCase();

    var filtered = cmdItems.filter(function (item) {
      if (!norm) return true;
      var hay = (item.label + ' ' + item.group).toLowerCase();
      var hayNorm = window.PUI && window.PUI.normalizeSearch ? window.PUI.normalizeSearch(hay) : hay;
      return hayNorm.indexOf(norm) !== -1;
    });

    cmdList.innerHTML = '';
    if (!filtered.length) {
      var empty = document.createElement('li');
      empty.className = 'pui-muted';
      empty.style.padding = '12px 14px';
      empty.textContent = 'بخشی با این نام یافت نشد.';
      cmdList.appendChild(empty);
      return;
    }

    filtered.forEach(function (item, idx) {
      var li = document.createElement('li');
      var a = document.createElement('a');
      a.className = 'pui-cmd__link' + (idx === 0 ? ' is-active' : '');
      a.href = item.href;

      var title = document.createElement('span');
      title.textContent = item.label;

      var hint = document.createElement('span');
      hint.className = 'pui-cmd__hint';
      hint.textContent = item.active ? 'بخش فعلی' : item.group;

      a.appendChild(title);
      a.appendChild(hint);
      li.appendChild(a);
      cmdList.appendChild(li);
    });
  }

  function openCmdPalette() {
    if (!cmdDialog) return;
    if (!cmdItems.length) initCommandPalette();
    cmdInput.value = '';
    renderCmdItems('');
    if (typeof cmdDialog.showModal === 'function' && !cmdDialog.open) {
      cmdDialog.showModal();
    }
    window.setTimeout(function () { cmdInput.focus(); }, 20);
  }

  if (cmdInput) {
    cmdInput.addEventListener('input', function () {
      renderCmdItems(cmdInput.value);
    });
    cmdInput.addEventListener('keydown', function (event) {
      if (!cmdList) return;
      var links = Array.prototype.slice.call(cmdList.querySelectorAll('.pui-cmd__link'));
      if (!links.length) return;
      var curIndex = links.findIndex(function (l) { return l.classList.contains('is-active'); });
      if (curIndex < 0) curIndex = 0;

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        links[curIndex].classList.remove('is-active');
        var next = links[(curIndex + 1) % links.length];
        next.classList.add('is-active');
        next.scrollIntoView({ block: 'nearest' });
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        links[curIndex].classList.remove('is-active');
        var prev = links[(curIndex - 1 + links.length) % links.length];
        prev.classList.add('is-active');
        prev.scrollIntoView({ block: 'nearest' });
      } else if (event.key === 'Enter') {
        event.preventDefault();
        window.location.href = links[curIndex].href;
      }
    });
  }

  /* ── منوی موبایل ────────────────────────────────────────── */
  function closeNav() {
    var sidebar = document.getElementById('pas-sidebar');
    if (sidebar) sidebar.classList.remove('is-open');
    document.querySelectorAll('.pas-nav-toggle').forEach(function (btn) {
      btn.setAttribute('aria-expanded', 'false');
    });
    document.body.classList.remove('pvn-nav-open');
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-pvn-cmd-open]')) {
      openCmdPalette();
      return;
    }

    var toggle = event.target.closest('.pas-nav-toggle');
    if (toggle) {
      var sidebar = document.getElementById('pas-sidebar');
      if (sidebar) {
        var open = sidebar.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('pvn-nav-open', open);
      }
      return;
    }

    if (event.target.closest('[data-pvn-close-nav]')) {
      closeNav();
      return;
    }

    var copyBtn = event.target.closest('[data-pas-copy]');
    if (copyBtn) {
      var text = copyBtn.getAttribute('data-pas-copy') || '';
      if (!text) return;
      var orig = copyBtn.textContent;
      var done = function () {
        copyBtn.textContent = 'کپی شد';
        copyBtn.setAttribute('data-copied', 'true');
        toast('در کلیپ‌بورد کپی شد.', 'success');
        window.setTimeout(function () {
          copyBtn.textContent = orig;
          copyBtn.removeAttribute('data-copied');
        }, 1800);
      };
      if (window.PUI && window.PUI.copyText) {
        window.PUI.copyText(text, copyBtn);
        copyBtn.textContent = 'کپی شد';
        window.setTimeout(function () { copyBtn.textContent = orig; }, 1800);
      } else if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(function () {});
      }
      return;
    }

    var confirmBtn = event.target.closest('[data-pas-confirm]');
    if (confirmBtn) {
      var msg = confirmBtn.getAttribute('data-pas-confirm') || 'آیا مطمئن هستید؟';
      if (!window.confirm(msg)) {
        event.preventDefault();
      }
      return;
    }

    var tabBtn = event.target.closest('.pvn-case-tab');
    if (tabBtn) {
      activateCaseTab(String(tabBtn.getAttribute('data-pvn-case-tab')), false);
      return;
    }

    var editBtn = event.target.closest('[data-pas-edit-note]');
    if (editBtn) {
      var form = document.getElementById('pas-note-form');
      if (!form) return;
      form.querySelector('input[name="note_id"]').value = editBtn.getAttribute('data-note-id') || '0';
      form.querySelector('#pas-session-date').value = editBtn.getAttribute('data-session-date') || '';
      form.querySelector('#pas-session-number').value = editBtn.getAttribute('data-session-number') || '0';
      var article = editBtn.closest('.pas-note');
      var bodyEl = article ? article.querySelector('.pas-note-body') : null;
      var contentEl = form.querySelector('#pas-note-content');
      contentEl.value = bodyEl ? bodyEl.textContent.trim() : '';
      contentEl.dispatchEvent(new Event('input', { bubbles: true }));
      form.classList.add('is-editing');
      var titleEl = form.querySelector('.pas-note-form-title');
      if (titleEl) titleEl.textContent = 'ویرایش یادداشت';
      var submitEl = form.querySelector('button[type="submit"]');
      if (submitEl) submitEl.textContent = 'ذخیرهٔ تغییرات';
      var cancelBtn = form.querySelector('[data-pas-cancel-edit]');
      if (cancelBtn) cancelBtn.removeAttribute('hidden');
      if (form.scrollIntoView) form.scrollIntoView({ behavior: 'smooth', block: 'center' });
      contentEl.focus();
      return;
    }

    if (event.target.closest('[data-pas-cancel-edit]')) {
      event.preventDefault();
      resetNoteForm();
      return;
    }

    var deleteBtn = event.target.closest('[data-pas-delete-note]');
    if (deleteBtn) {
      if (!window.confirm('این یادداشت حذف شود؟')) return;
      deleteBtn.disabled = true;
      fetch('/api/notes/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          case_id: deleteBtn.getAttribute('data-case-id'),
          note_id: deleteBtn.getAttribute('data-note-id'),
        }),
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res && res.success) {
            toast(res.data.message, 'success');
            window.setTimeout(function () { window.location.reload(); }, 450);
          } else {
            toast((res && res.data && res.data.message) || 'خطا در حذف یادداشت', 'error');
            deleteBtn.disabled = false;
          }
        })
        .catch(function () {
          toast('خطا در ارتباط با سرور', 'error');
          deleteBtn.disabled = false;
        });
      return;
    }

    var restoreBtn = event.target.closest('[data-pas-restore-note]');
    if (restoreBtn) {
      if (!window.confirm('این نسخه بازگردانی شود؟')) return;
      fetch('/api/notes/restore', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          case_id: restoreBtn.getAttribute('data-case-id'),
          history_id: restoreBtn.getAttribute('data-history-id'),
        }),
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res && res.success) {
            toast(res.data.message, 'success');
            window.setTimeout(function () { window.location.reload(); }, 450);
          } else {
            toast((res && res.data && res.data.message) || 'خطا در بازگردانی', 'error');
          }
        });
      return;
    }

    var readNotifBtn = event.target.closest('[data-pas-read-notification]');
    if (readNotifBtn) {
      fetch('/api/notifications/read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          notification_id: readNotifBtn.getAttribute('data-pas-read-notification'),
        }),
      }).then(function () {
        window.location.reload();
      });
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeNav();
      return;
    }
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      openCmdPalette();
      return;
    }
    if (
      event.key === '/' &&
      !event.ctrlKey && !event.metaKey && !event.altKey &&
      document.activeElement &&
      !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)
    ) {
      var filterInput = document.querySelector('[data-pui-filter-target]');
      if (filterInput) {
        event.preventDefault();
        filterInput.focus();
      } else {
        event.preventDefault();
        openCmdPalette();
      }
    }
  });

  /* ناوبری کیبورد تب‌های پرونده */
  document.addEventListener('keydown', function (event) {
    var tab = event.target.closest('.pvn-case-tab');
    if (!tab) return;
    if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(event.key) === -1) return;
    event.preventDefault();
    var tabs = Array.prototype.slice.call(document.querySelectorAll('.pvn-case-tab'));
    var idx = tabs.indexOf(tab);
    var next = idx;
    if (event.key === 'ArrowLeft') next = (idx + 1) % tabs.length;
    if (event.key === 'ArrowRight') next = (idx - 1 + tabs.length) % tabs.length;
    if (event.key === 'Home') next = 0;
    if (event.key === 'End') next = tabs.length - 1;
    activateCaseTab(String(tabs[next].getAttribute('data-pvn-case-tab')), true);
  });

  /* شمارندهٔ زنده و Ctrl+Enter برای یادداشت بالینی */
  var noteContent = document.getElementById('pas-note-content');
  var noteCounter = document.getElementById('pas-note-counter');
  if (noteContent) {
    var updateCounter = function () {
      if (!noteCounter) return;
      var text = noteContent.value.trim();
      var chars = noteContent.value.length;
      var words = text ? text.split(/\s+/).length : 0;
      var toFa = function (n) {
        var d = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return String(n).replace(/\d/g, function (x) { return d[Number(x)]; });
      };
      noteCounter.textContent = toFa(words) + ' کلمه · ' + toFa(chars) + ' نویسه';
    };
    noteContent.addEventListener('input', updateCounter);
    noteContent.addEventListener('keydown', function (event) {
      if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        var form = document.getElementById('pas-note-form');
        if (form) form.requestSubmit();
      }
    });
    updateCounter();
  }

  function activateCaseTab(name, focus) {
    document.querySelectorAll('.pvn-case-tab').forEach(function (tab) {
      var on = String(tab.getAttribute('data-pvn-case-tab')) === name;
      tab.classList.toggle('is-active', on);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
      tab.setAttribute('tabindex', on ? '0' : '-1');
    });
    document.querySelectorAll('[data-pvn-case-panel]').forEach(function (panel) {
      if (String(panel.getAttribute('data-pvn-case-panel')) === name) {
        panel.removeAttribute('hidden');
      } else {
        panel.setAttribute('hidden', 'hidden');
      }
    });
    if (focus) {
      var target = document.getElementById('pvn-ctab-' + name);
      if (target) target.focus();
    }
    if (window.history && window.history.replaceState) {
      window.history.replaceState(null, '', '#' + name);
    }
  }

  (function initCaseTabHash() {
    var hash = String(window.location.hash || '').replace('#', '').replace(/[^a-z_]/g, '');
    if (hash && document.querySelector('.pvn-case-tab[data-pvn-case-tab="' + hash + '"]')) {
      activateCaseTab(hash, false);
    }
  })();

  function resetNoteForm() {
    var form = document.getElementById('pas-note-form');
    if (!form) return;
    form.querySelector('input[name="note_id"]').value = '0';
    var dateInput = form.querySelector('#pas-session-date');
    dateInput.value = dateInput.getAttribute('data-default') || '';
    form.querySelector('#pas-session-number').value = '0';
    var contentEl = form.querySelector('#pas-note-content');
    contentEl.value = '';
    contentEl.dispatchEvent(new Event('input', { bubbles: true }));
    form.classList.remove('is-editing');
    var titleEl = form.querySelector('.pas-note-form-title');
    if (titleEl) titleEl.textContent = 'افزودن یادداشت جدید';
    var submitEl = form.querySelector('button[type="submit"]');
    if (submitEl) submitEl.textContent = 'ثبت یادداشت';
    var cancelBtn = form.querySelector('[data-pas-cancel-edit]');
    if (cancelBtn) cancelBtn.setAttribute('hidden', 'hidden');
  }

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('#pas-note-form');
    if (!form) return;
    event.preventDefault();
    var submit = form.querySelector('button[type="submit"]');
    if (submit) submit.disabled = true;

    var formData = new FormData(form);
    var payload = {};
    formData.forEach(function (value, key) {
      payload[key] = value;
    });

    fetch('/api/notes/save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.success) {
          toast(res.data.message, 'success');
          window.setTimeout(function () {
            window.location.hash = 'notes';
            window.location.reload();
          }, 450);
        } else {
          toast((res && res.data && res.data.message) || 'خطا در ثبت یادداشت', 'error');
          if (submit) submit.disabled = false;
        }
      })
      .catch(function () {
        toast('خطا در ارتباط با سرور', 'error');
        if (submit) submit.disabled = false;
      });
  });

  initCommandPalette();
})();
