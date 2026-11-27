<?php
/**
 * پاکسازی مقادیر حساس ورودی (رمز SMTP و کلید API).
 *
 * این تابع فقط کاراکترهای کنترلی را حذف و فاصله‌های ابتدا/انتها را trim
 * می‌کند؛ فاصله‌های داخلی رمز دست‌نخورده می‌مانند (برخلاف sanitize_text_field).
 */

namespace Pivand\SmtpMailer;

if (!defined('ABSPATH')) {
    exit;
}

function zsm_sanitize_secret($value): string
{
    return trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', (string) $value));
}
