/**
 * داشبورد یکپارچه درمانگر — رفتار سمت مرورگر.
 *
 * همه‌ی عملیات حساس سمت سرور دوباره بررسی می‌شوند؛ این فایل فقط تجربه‌ی
 * کاربری را بهتر می‌کند و هیچ تصمیم امنیتی نمی‌گیرد.
 */
(function ($) {
	'use strict';

	var cfg = window.PasDashboard || {};

	function toast(message, type) {
		var $box = $('<div class="pas-notice" role="status"></div>')
			.addClass('pas-notice-' + (type || 'info'))
			.text(message);

		$('.pas-main').prepend($box);

		window.setTimeout(function () {
			$box.fadeOut(200, function () {
				$(this).remove();
			});
		}, 6000);
	}

	function post(action, payload, nonceKey) {
		return $.ajax({
			url: cfg.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: $.extend(
				{
					action: action,
					nonce: (cfg.nonces && cfg.nonces[nonceKey]) || ''
				},
				payload || {}
			)
		});
	}

	/* منوی موبایل */
	function closeNav() {
		$('#pas-sidebar').removeClass('is-open');
		$('.pas-nav-toggle').attr('aria-expanded', 'false');
		$('body').removeClass('pvn-nav-open');
	}

	$(document).on('click', '.pas-nav-toggle', function () {
		var $sidebar = $('#pas-sidebar');
		var open = $sidebar.toggleClass('is-open').hasClass('is-open');
		$(this).attr('aria-expanded', open ? 'true' : 'false');
		$('body').toggleClass('pvn-nav-open', open);
	});

	$(document).on('click', '[data-pvn-close-nav]', closeNav);

	$(document).on('keydown', function (event) {
		if (event.key === 'Escape') {
			closeNav();
		}
	});

	/* ── حالت رنگ ───────────────────────────────────────────
	   انتخاب در localStorage ذخیره می‌شود؛ اسکریپت زودهنگام در <head>
	   attribute را پیش از رندر می‌گذارد تا پرش رنگ نداشته باشیم. */
	/*
	 * همان کلید و همان attributes کیت رابط (ui.js). اگر کیت فعال باشد،
	 * مدیریت کلیک را به [data-pui-theme-toggle] خودش می‌سپاریم تا دو
	 * هندلر روی یک کلیک همدیگر را خنثی نکنند. اگر کیت نباشد (حالت تنزل)،
	 * همین کد کار می‌کند.
	 */
	var THEME_KEY = 'pui-theme';

	function applyTheme(theme) {
		if (window.PUI) {
			return;
		}

		document.documentElement.setAttribute('data-pui-theme', theme);
		document.documentElement.setAttribute('data-pui-theme-choice', theme);
		try {
			window.localStorage.setItem(THEME_KEY, theme);
		} catch (e) {}
		$('.pvn-theme-toggle').attr('aria-pressed', theme === 'dark' ? 'true' : 'false');
	}

	(function () {
		if (window.PUI) {
			return;
		}

		var saved = null;
		try {
			saved = window.localStorage.getItem(THEME_KEY);
		} catch (e) {}
		if (saved === 'dark' || saved === 'light') {
			applyTheme(saved);
		}
	})();

	$(document).on('click', '.pvn-theme-toggle', function () {
		if (window.PUI) {
			return;
		}

		var current = document.documentElement.getAttribute('data-pui-theme');
		applyTheme(current === 'dark' ? 'light' : 'dark');
	});

	/* ── تب‌های پرونده ────────────────────────────────────── */
	function activateCaseTab(name, focus) {
		$('.pvn-case-tab').each(function () {
			var on = String($(this).attr('data-pvn-case-tab')) === name;
			$(this).toggleClass('is-active', on)
				.attr('aria-selected', on ? 'true' : 'false')
				.attr('tabindex', on ? '0' : '-1');
		});

		$('[data-pvn-case-panel]').each(function () {
			if (String($(this).attr('data-pvn-case-panel')) === name) {
				$(this).removeAttr('hidden');
			} else {
				$(this).attr('hidden', 'hidden');
			}
		});

		if (focus) {
			$('#pvn-ctab-' + name).trigger('focus');
		}

		if (window.history && window.history.replaceState) {
			window.history.replaceState(null, '', '#' + name);
		}
	}

	$(document).on('click', '.pvn-case-tab', function () {
		activateCaseTab(String($(this).attr('data-pvn-case-tab')), false);
	});

	$(document).on('keydown', '.pvn-case-tab', function (event) {
		if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
			return;
		}

		event.preventDefault();

		var $tabs = $('.pvn-case-tab');
		var index = $tabs.index(this);
		var next = event.key === 'ArrowLeft' ? index + 1 : index - 1;

		if (next < 0) { next = $tabs.length - 1; }
		if (next >= $tabs.length) { next = 0; }

		activateCaseTab(String($tabs.eq(next).attr('data-pvn-case-tab')), true);
	});

	(function () {
		var hash = String(window.location.hash || '').replace('#', '').replace(/[^a-z_]/g, '');
		if (hash !== '' && $('.pvn-case-tab[data-pvn-case-tab="' + hash + '"]').length) {
			activateCaseTab(hash, false);
		}
	})();

	/* فوکوس پس از پرش به محتوا */
	$(document).on('click', '.pas-skip-link', function () {
		window.setTimeout(function () {
			$('#pas-main').trigger('focus');
		}, 0);
	});

	/* کپی لینک دعوت آزمون */
	$(document).on('click', '[data-pas-copy]', function () {
		var $button = $(this);
		var text = String($button.attr('data-pas-copy') || '');

		if (!text) {
			return;
		}

		var original = $button.text();
		var done = function () {
			$button.text('کپی شد');
			window.setTimeout(function () { $button.text(original); }, 2000);
		};

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done).catch(function () {
				$button.prev('input').trigger('select');
			});
			return;
		}

		$button.prev('input').trigger('select');
		document.execCommand('copy');
		done();
	});

	/* تأیید عملیات مخرب */
	$(document).on('click', '[data-pas-confirm]', function (event) {
		var message = $(this).data('pas-confirm') || cfg.i18n.confirmDelete;

		if (!window.confirm(message)) {
			event.preventDefault();
		}
	});

	/* ویرایش یادداشت — فرم پایین فهرست را پر می‌کند و note_id را ست می‌دهد
	   تا همان هندلر ذخیره، مسیر ویرایش (و ساخت تاریخچه) را اجرا کند. */
	function resetNoteForm() {
		var $form = $('#pas-note-form');

		if (!$form.length) {
			return;
		}

		$form.find('input[name="note_id"]').val('0');
		$form.find('#pas-session-date').val($form.find('#pas-session-date').attr('data-default') || '');
		$form.find('#pas-session-number').val('0');
		$form.find('#pas-note-content').val('');
		$form.removeClass('is-editing');
		$form.find('.pas-note-form-title').text(cfg.i18n.newTitle);
		$form.find('button[type="submit"]').text(cfg.i18n.addNote);
		$form.find('[data-pas-cancel-edit]').attr('hidden', 'hidden');
	}

	$(document).on('click', '[data-pas-edit-note]', function () {
		var $button = $(this);
		var $form = $('#pas-note-form');

		if (!$form.length) {
			return;
		}

		$form.find('input[name="note_id"]').val($button.attr('data-note-id'));
		$form.find('#pas-session-date').val($button.attr('data-session-date') || '');
		$form.find('#pas-session-number').val($button.attr('data-session-number') || '0');
		$form.find('#pas-note-content').val($button.closest('.pas-note').find('.pas-note-body').text());

		$form.addClass('is-editing');
		$form.find('.pas-note-form-title').text(cfg.i18n.editTitle);
		$form.find('button[type="submit"]').text(cfg.i18n.saveChanges);
		$form.find('[data-pas-cancel-edit]').removeAttr('hidden');

		if ($form[0].scrollIntoView) {
			$form[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
		}

		$form.find('#pas-note-content').trigger('focus');
	});

	$(document).on('click', '[data-pas-cancel-edit]', function (event) {
		event.preventDefault();
		resetNoteForm();
	});

	/* ثبت/ویرایش یادداشت */
	$(document).on('submit', '#pas-note-form', function (event) {
		event.preventDefault();

		var $form = $(this);
		var $submit = $form.find('button[type="submit"]');

		$submit.prop('disabled', true);

		post('pas_dashboard_save_note', $form.serializeArray().reduce(function (acc, field) {
			acc[field.name] = field.value;
			return acc;
		}, {}), 'note_save')
			.done(function (response) {
				if (response && response.success) {
					toast(response.data.message, 'success');
					window.setTimeout(function () {
						window.location.reload();
					}, 600);
				} else {
					toast((response && response.data && response.data.message) || cfg.i18n.error, 'error');
				}
			})
			.fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
				toast(message || cfg.i18n.error, 'error');
			})
			.always(function () {
				$submit.prop('disabled', false);
			});
	});

	/* حذف یادداشت */
	$(document).on('click', '[data-pas-delete-note]', function () {
		var $button = $(this);
		var caseId = $button.data('case-id');
		var noteId = $button.data('note-id');

		if (!window.confirm(cfg.i18n.confirmDelete)) {
			return;
		}

		$button.prop('disabled', true);

		post('pas_dashboard_delete_note', { case_id: caseId, note_id: noteId }, 'note_delete')
			.done(function (response) {
				if (response && response.success) {
					toast(response.data.message, 'success');
					window.setTimeout(function () {
						window.location.reload();
					}, 500);
				} else {
					toast((response && response.data && response.data.message) || cfg.i18n.error, 'error');
				}
			})
			.fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
				toast(message || cfg.i18n.error, 'error');
			})
			.always(function () {
				$button.prop('disabled', false);
			});
	});

	/* بازگردانی نسخه */
	$(document).on('click', '[data-pas-restore-note]', function () {
		var $button = $(this);

		if (!window.confirm(cfg.i18n.confirmTitle + ': ' + $button.data('label'))) {
			return;
		}

		post('pas_dashboard_restore_note', {
			case_id: $button.data('case-id'),
			history_id: $button.data('history-id')
		}, 'note_restore')
			.done(function (response) {
				if (response && response.success) {
					toast(response.data.message, 'success');
					window.setTimeout(function () {
						window.location.reload();
					}, 500);
				} else {
					toast((response && response.data && response.data.message) || cfg.i18n.error, 'error');
				}
			});
	});

	/* خواندن اعلان‌ها */
	$(document).on('click', '[data-pas-read-notification]', function () {
		var id = $(this).data('pas-read-notification');

		post('pas_dashboard_mark_notifications_read', { notification_id: id }, 'notifications_read')
			.done(function () {
				window.location.reload();
			});
	});

	/* ── به‌روزرسانی افزونه‌ها ────────────────────────────────
	   هر بسته در یک درخواست AJAX جدا آپدیت می‌شود و صفحه هرگز با POST کامل
	   جابه‌جا نمی‌شود. پیش از این، یک درخواست طولانی (چند بسته با هم) می‌توانست
	   از سقف زمان اجرای PHP بگذرد و پاسخ نیمه‌کاره، صفحهٔ سفید نشان دهد؛
	   اکنون خطا همان‌جا در ردیف افزونه به‌صورت پیام دیده می‌شود. */
	var updateBusy = false;

	function setUpdateState($scope, text, tone) {
		var $state = $scope.find('[data-pas-update-state]').first();

		if (!$state.length) {
			return;
		}

		$state.text(text || '')
			.removeClass('is-ok is-error is-busy')
			.addClass(tone ? 'is-' + tone : '');
	}

	function updateOnePlugin(slug) {
		var $form = $('[data-pas-update-form][data-slug="' + slug + '"]');
		var deferred = $.Deferred();

		post('pas_dashboard_update_plugin', { slug: slug }, 'updates_run')
			.done(function (response) {
				var ok = !!(response && response.success);
				var message = (response && response.data && response.data.message) || (ok ? cfg.i18n.updateDone : cfg.i18n.error);

				setUpdateState($form, ok ? cfg.i18n.updateDone : message, ok ? 'ok' : 'error');

				if (ok) {
					$form.find('[data-pas-update-trigger]').prop('disabled', true).text(cfg.i18n.updateDone);
				}

				deferred.resolve({ ok: ok, message: message });
			})
			.fail(function (xhr) {
				var message = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || cfg.i18n.error;

				setUpdateState($form, message, 'error');
				deferred.resolve({ ok: false, message: message });
			});

		return deferred.promise();
	}

	$(document).on('submit', '[data-pas-update-form]', function (event) {
		event.preventDefault();

		if (updateBusy) {
			return;
		}

		var $form = $(this);
		var isAll = $form.is('[data-pas-update-all]');

		if (!window.confirm(isAll ? cfg.i18n.updateAllConfirm : cfg.i18n.updateConfirm)) {
			return;
		}

		var slugs = [];

		if (isAll) {
			$('[data-pas-update-form][data-slug]').each(function () {
				slugs.push(String($(this).attr('data-slug') || ''));
			});
		} else {
			slugs.push(String($form.attr('data-slug') || ''));
		}

		slugs = slugs.filter(function (slug) {
			return slug !== '';
		});

		if (!slugs.length) {
			return;
		}

		updateBusy = true;
		$form.find('[data-pas-update-trigger]').prop('disabled', true);
		setUpdateState($form, cfg.i18n.updating, 'busy');
		toast(cfg.i18n.updating, 'info');

		var failures = 0;
		var index = 0;

		function next() {
			if (index >= slugs.length) {
				updateBusy = false;

				toast(failures === 0 ? cfg.i18n.updateDone : cfg.i18n.error, failures === 0 ? 'success' : 'error');

				window.setTimeout(function () {
					window.location.reload();
				}, failures === 0 ? 900 : 2400);

				return;
			}

			var slug = slugs[index];
			index++;

			if (isAll) {
				setUpdateState($('[data-pas-update-form][data-slug="' + slug + '"]'), cfg.i18n.updating, 'busy');
			}

			updateOnePlugin(slug).then(function (result) {
				if (!result.ok) {
					failures++;
				}
				next();
			});
		}

		next();
	});
})(jQuery);
