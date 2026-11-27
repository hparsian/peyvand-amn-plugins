(function () {
  'use strict';

  function toast(message, type) {
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
    }, 5500);
  }

  /* Mobile navigation drawer */
  function closeNav() {
    var sidebar = document.getElementById('pas-sidebar');
    if (sidebar) sidebar.classList.remove('is-open');
    document.querySelectorAll('.pas-nav-toggle').forEach(function (btn) {
      btn.setAttribute('aria-expanded', 'false');
    });
    document.body.classList.remove('pvn-nav-open');
  }

  document.addEventListener('click', function (event) {
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

    /* Copy invite link */
    var copyBtn = event.target.closest('[data-pas-copy]');
    if (copyBtn) {
      var text = copyBtn.getAttribute('data-pas-copy') || '';
      if (!text) return;
      var orig = copyBtn.textContent;
      var done = function () {
        copyBtn.textContent = 'کپی شد';
        window.setTimeout(function () {
          copyBtn.textContent = orig;
        }, 2000);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(function () {});
      }
      return;
    }

    /* Confirm prompt */
    var confirmBtn = event.target.closest('[data-pas-confirm]');
    if (confirmBtn) {
      var msg = confirmBtn.getAttribute('data-pas-confirm') || 'آیا مطمئن هستید؟';
      if (!window.confirm(msg)) {
        event.preventDefault();
      }
      return;
    }

    /* Case tabs */
    var tabBtn = event.target.closest('.pvn-case-tab');
    if (tabBtn) {
      activateCaseTab(String(tabBtn.getAttribute('data-pvn-case-tab')), false);
      return;
    }

    /* Edit note */
    var editBtn = event.target.closest('[data-pas-edit-note]');
    if (editBtn) {
      var form = document.getElementById('pas-note-form');
      if (!form) return;
      form.querySelector('input[name="note_id"]').value = editBtn.getAttribute('data-note-id') || '0';
      form.querySelector('#pas-session-date').value = editBtn.getAttribute('data-session-date') || '';
      form.querySelector('#pas-session-number').value = editBtn.getAttribute('data-session-number') || '0';
      var article = editBtn.closest('.pas-note');
      var bodyEl = article ? article.querySelector('.pas-note-body') : null;
      form.querySelector('#pas-note-content').value = bodyEl ? bodyEl.textContent.trim() : '';
      form.classList.add('is-editing');
      var titleEl = form.querySelector('.pas-note-form-title');
      if (titleEl) titleEl.textContent = 'ویرایش یادداشت';
      var submitEl = form.querySelector('button[type="submit"]');
      if (submitEl) submitEl.textContent = 'ذخیرهٔ تغییرات';
      var cancelBtn = form.querySelector('[data-pas-cancel-edit]');
      if (cancelBtn) cancelBtn.removeAttribute('hidden');
      if (form.scrollIntoView) form.scrollIntoView({ behavior: 'smooth', block: 'center' });
      form.querySelector('#pas-note-content').focus();
      return;
    }

    /* Cancel edit note */
    if (event.target.closest('[data-pas-cancel-edit]')) {
      event.preventDefault();
      resetNoteForm();
      return;
    }

    /* Delete note */
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

    /* Restore note */
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

    /* Mark notification read */
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
    if (event.key === 'Escape') closeNav();
  });

  /* Case tabs */
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
    form.querySelector('#pas-note-content').value = '';
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
})();
