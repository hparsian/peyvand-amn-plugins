/**
 * وردپرس با کلاس is-dismissible یک دکمه‌ی ضربدر اضافه می‌کند که با کلیک،
 * فقط نوتیس را در همان صفحه محو می‌کند (event: wp-dismiss-notice-{id}) و
 * هیچ درخواستی به سرور نمی‌فرستد. این فایل همان event را می‌گیرد و وضعیت
 * «دیده شد» را با AJAX در سرور ذخیره می‌کند تا نوتیس در بارگذاری بعدی
 * دوباره ظاهر نشود.
 */
(function () {
	'use strict';

	if (typeof PasCoreDismissKeyNotice === 'undefined') {
		return;
	}

	// توجه: دکمه‌ی .notice-dismiss را خودِ اسکریپت‌های هسته‌ی وردپرس به‌صورت
	// پویا اضافه می‌کنند، بعد از این‌که کلاس is-dismissible را تشخیص دادند —
	// نه این فایل و نه PHP آن را در markup اولیه قرار نمی‌دهند. اگر این‌جا
	// روی DOMContentLoaded مستقیماً querySelector('.notice-dismiss') بزنیم،
	// در لحظه‌ی اجرا معمولاً آن دکمه هنوز در DOM نیست، querySelector مقدار
	// null برمی‌گرداند و تابع بی‌صدا return می‌کند — یعنی listener هیچ‌وقت
	// بسته نمی‌شود و AJAX هیچ‌وقت اجرا نمی‌شود، در حالی که از دید کاربر
	// نوتیس (به لطف اسکریپت خودِ وردپرس) محو شده به نظر می‌رسد. راه‌حل درست،
	// همان الگوی خودِ core است: event delegation روی document، که مستقل از
	// زمان تزریق دکمه کار می‌کند.
	document.addEventListener('click', function (event) {
		var button = event.target.closest && event.target.closest('#pas-core-key-notice .notice-dismiss');
		if (!button) {
			return;
		}

		var body = new window.URLSearchParams();
		body.append('action', PasCoreDismissKeyNotice.action);
		body.append('nonce', PasCoreDismissKeyNotice.nonce);

		fetch(PasCoreDismissKeyNotice.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		});
		// نتیجه‌ی درخواست عمداً بررسی نمی‌شود: در بدترین حالت (شکست
		// شبکه) نوتیس فقط دوباره در بارگذاری بعدی نمایش داده می‌شود،
		// نه این‌که چیزی خراب شود.
	});
})();
