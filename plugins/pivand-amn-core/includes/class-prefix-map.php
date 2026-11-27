<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نگاشت مستندشده‌ی پیشوند کلاس‌ها به پلاگین صاحب آن‌ها. اکوسیستم پیوند امن
 * الان شش قاعده‌ی نام‌گذاری هم‌زمان دارد (Pas_, Psy_, ACO_, PWDF_, ZSM_، به‌علاوه
 * دو namespace مدرن ZoojDarman\ClientPortal و Pivand\SmtpMailer). یکی‌سازی
 * همه‌ی این‌ها ریسک شکستن اتولودرهای موجود را دارد، پس این فایل فقط مستند
 * می‌کند — چیزی را رجیستر یا لود نمی‌کند — تا موقع افزودن کلاس جدید، احتمال
 * تصادم نام (name collision) بین پلاگین‌ها کم شود.
 *
 * قانون برای کلاس‌های جدید: قبل از ساختن کلاس با پیشوند تازه، این لیست را
 * چک کن؛ اگر پلاگین از قبل پیشوندی دارد، همان را ادامه بده.
 */
final class Pas_Core_Prefix_Map
{
    public static function entries(): array
    {
        return [
            [
                'prefix'  => 'Pas_Core_',
                'plugin'  => 'پیوند امن — هسته مشترک (01)',
                'example' => 'Pas_Core_Helpers, Pas_Core_Crypto',
                'note'    => '۸ کلاس. فقط این پلاگین (core) مجاز به استفاده از این زیرپیشوند است.',
            ],
            [
                'prefix'  => 'Pas_CF_',
                'plugin'  => 'پرونده مراجعین — case-file (05)',
                'example' => 'Pas_CF_Security, Pas_CF_Installer',
                'note'    => '۶ کلاس.',
            ],
            [
                'prefix'  => 'Pas_' . '(بدون زیرپیشوند دوم)',
                'plugin'  => 'پل پیامک — sms-bridge (02)',
                'example' => 'Pas_Log، Pas_Admin، Pas_Melipayamak',
                'note'    => '۳ کلاس. با Pas_Core_ و Pas_CF_ اشتباه نشود — این سه، سه پلاگین جدا هستند که هر سه از خانواده‌ی «Pas» شروع می‌شوند.',
            ],
            [
                'prefix'  => 'Psy_',
                'plugin'  => 'آزمون‌های روان‌سنجی — quiz-enterprise (03)',
                'example' => 'Psy_Logger, Psy_Activator',
                'note'    => '۱۷ کلاس. تست‌سنج‌های روان‌شناسی (BDI, STAI, ...) از interface-test.php مشترک پیروی می‌کنند.',
            ],
            [
                'prefix'  => 'ZoojDarman\\ClientPortal\\',
                'plugin'  => 'پنل مراجعان — client-portal (04)',
                'example' => 'ZoojDarman\\ClientPortal\\Installer',
                'note'    => 'namespace مدرن PSR-4، نه پیشوند کلاس تخت. ثابت‌های سراسری این پلاگین ZDP_ هستند (ZDP_VERSION).',
            ],
            [
                'prefix'  => 'ACO_',
                'plugin'  => 'تولید محتوا و سئو — content-writer (09)',
                'example' => 'ACO_Queue, ACO_Logger',
                'note'    => '۱۰ کلاس. میراث نام «ACO Content Writer» قبل از ادغام در برند پیوند امن.',
            ],
            [
                'prefix'  => 'PWDF_',
                'plugin'  => 'فید محتوای روزانه — daily-feed (10)',
                'example' => 'PWDF_Renderer',
                'note'    => '۶ کلاس.',
            ],
            [
                'prefix'  => 'ZSM_',
                'plugin'  => 'SMTP خروجی — smtp-mailer (07) — کلاس قدیمی باقی‌مانده',
                'example' => 'ZSM_Mailer',
                'note'    => 'فقط ۱ کلاس. این پلاگین اکنون عمدتاً از namespace مدرن Pivand\\SmtpMailer استفاده می‌کند؛ ZSM_ میراث نسخه‌ی قبلی است.',
            ],
            [
                'prefix'  => 'Pivand\\SmtpMailer\\',
                'plugin'  => 'SMTP خروجی — smtp-mailer (07)',
                'example' => 'Pivand\\SmtpMailer\\Settings',
                'note'    => 'namespace مدرن PSR-4.',
            ],
            [
                'prefix'  => 'بدون کلاس اختصاصی (توابع تخت)',
                'plugin'  => 'فرم تماس — payvand-contact (08)',
                'example' => 'payvand_render_form()',
                'note'    => 'کوچک‌ترین پلاگین خانواده؛ هنوز به OOP منتقل نشده — اولویت پایین.',
            ],
            [
                'prefix'  => 'بدون کلاس اختصاصی (توابع تخت)',
                'plugin'  => 'دومرحله‌ای ادمین — 2fa (06)',
                'example' => '—',
                'note'    => '',
            ],
        ];
    }
}
