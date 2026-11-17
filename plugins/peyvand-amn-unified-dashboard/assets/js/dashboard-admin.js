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
	$(document).on('click', '.pas-nav-toggle', function () {
		var $sidebar = $('#pas-sidebar');
		var open = $sidebar.toggleClass('is-open').hasClass('is-open');
		$(this).attr('aria-expanded', open ? 'true' : 'false');
	});

	/* تأیید عملیات مخرب */
	$(document).on('click', '[data-pas-confirm]', function (event) {
		var message = $(this).data('pas-confirm') || cfg.i18n.confirmDelete;

		if (!window.confirm(message)) {
			event.preventDefault();
		}
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
})(jQuery);
