<?php
namespace PsychAssessments\Core;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registry — ثبت و دسترسی به آزمون‌ها
 */
class Registry {

    private static array $tests = [];

    // لیست پیش‌فرض آزمون‌های پرکاربرد (اگر هنوز در تنظیمات ذخیره نشده)
    public static array $popular_default = [
        'phq9', 'gad7', 'bdi', 'bai', 'dass21', 'scl90r',
        'ghq28', 'ras', 'csi', 'das', 'cts2', 'ecr', 'neo', 'ysq',
    ];

    // ── ترتیب نمایش دسته‌بندی‌ها ──────────────────────────────────────────
    private static array $category_order = [
        'mental_health', 'relationship', 'personality', 'wellbeing', 'screening',
    ];

    public static function register( TestInterface $test ): void {
        self::$tests[ $test->get_id() ] = $test;
    }

    public static function get( string $id ): ?TestInterface {
        return self::$tests[$id] ?? null;
    }

    /** @return TestInterface[] */
    public static function all(): array {
        return self::$tests;
    }

    public static function ids(): array {
        return array_keys( self::$tests );
    }

    /** گروه‌بندی آزمون‌ها بر اساس دسته‌بندی — به ترتیب استاندارد */
    public static function by_category(): array {
        $grouped = [];
        foreach ( self::$tests as $id => $test ) {
            $cat = $test->get_category();
            $grouped[$cat][] = $test;
        }
        $ordered = [];
        foreach ( self::$category_order as $cat ) {
            if ( isset( $grouped[$cat] ) ) {
                usort( $grouped[$cat], fn($a, $b) => strcmp( $a->get_name(), $b->get_name() ) );
                $ordered[$cat] = $grouped[$cat];
            }
        }
        foreach ( $grouped as $cat => $tests ) {
            if ( ! isset( $ordered[$cat] ) ) {
                usort( $tests, fn($a, $b) => strcmp( $a->get_name(), $b->get_name() ) );
                $ordered[$cat] = $tests;
            }
        }
        return $ordered;
    }

    /**
     * آزمون‌های پرکاربرد — از wp_options خونده میشه
     * اگر تنظیم نشده باشه، از $popular_default استفاده می‌کند
     */
    public static function popular(): array {
        $saved = get_option( 'psy_quiz_popular_tests', null );
        $ids   = ( is_array( $saved ) && ! empty( $saved ) )
            ? $saved
            : self::$popular_default;

        $result = [];
        foreach ( $ids as $id ) {
            if ( isset( self::$tests[$id] ) ) {
                $result[$id] = self::$tests[$id];
            }
        }
        return $result;
    }

    /** برچسب فارسی دسته‌بندی‌ها */
    public static function category_label( string $cat ): string {
        return [
            'mental_health' => '🧠 سلامت روان',
            'relationship'  => '💑 زوجین و روابط',
            'personality'   => '🪞 شخصیت و طرحواره',
            'wellbeing'     => '📊 خودشناسی و رفاه',
            'screening'     => '🔍 غربالگری ترکیبی',
            'mood'          => '🧠 سلامت روان',
            'anxiety'       => '🧠 سلامت روان',
            'trauma'        => '🧠 سلامت روان',
            'sleep'         => '🧠 سلامت روان',
            'substance'     => '🧠 سلامت روان',
            'other'         => '📊 خودشناسی و رفاه',
        ][$cat] ?? $cat;
    }
}
