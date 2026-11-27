<?php
namespace PsychAssessments\Core;
if ( ! defined( 'ABSPATH' ) ) exit;

interface TestInterface {
    public function get_id(): string;
    public function get_name(): string;
    public function get_description(): string;
    public function get_version(): string;
    public function get_category(): string;   // mood | anxiety | personality | relationship | substance | other
    public function get_scale(): array;       // ['min'=>0,'max'=>4,'labels'=>[...]]
    public function get_questions(): array;   // [1 => 'متن سوال', ...]
    public function get_schema_labels(): array;
    public function get_domain_labels(): array;
    public function calculate( array $answers ): array;
    public function render_report( array $data, object $session ): string;

    /**
     * آیا این آزمون به نمودار (Chart.js) نیاز دارد؟
     * پیاده‌سازی پیش‌فرض در BaseTest مقدار true برمی‌گرداند.
     * آزمون‌هایی که فقط نمره کلی دارند (مثل ACE) می‌توانند false برگردانند.
     */
    public function needs_chart(): bool;
}
