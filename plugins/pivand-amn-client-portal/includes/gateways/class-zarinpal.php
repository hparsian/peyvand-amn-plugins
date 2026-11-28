<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal\Gateways;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * پوششی نازک روی REST API نسخه ۴ زرین‌پال. مبلغ‌ها بر حسب تومان دریافت و
 * به ریال (ضرب در ۱۰) برای API ارسال می‌شوند — یکی از خطاهای رایج در ادغام
 * زرین‌پال، ارسال اشتباه واحد است.
 */
final class Zarinpal
{
    private const REQUEST_URL = 'https://payment.zarinpal.com/pg/v4/payment/request.json';
    private const VERIFY_URL  = 'https://payment.zarinpal.com/pg/v4/payment/verify.json';
    private const GATEWAY_URL = 'https://payment.zarinpal.com/pg/StartPay/';
    private const SANDBOX_REQUEST_URL = 'https://sandbox.zarinpal.com/pg/v4/payment/request.json';
    private const SANDBOX_VERIFY_URL  = 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json';
    private const SANDBOX_GATEWAY_URL = 'https://sandbox.zarinpal.com/pg/StartPay/';

    private function merchant_id(): string
    {
        return (string) get_option('zdp_zarinpal_merchant_id', '');
    }

    private function is_sandbox(): bool
    {
        return (bool) get_option('zdp_zarinpal_sandbox', false);
    }

    /**
     * @return array{success: bool, authority?: string, gateway_url?: string, message?: string}
     */
    public function request_payment(int $amount_toman, string $description, string $callback_url): array
    {
        $merchant_id = $this->merchant_id();
        if (empty($merchant_id)) {
            return ['success' => false, 'message' => 'درگاه پرداخت هنوز پیکربندی نشده است.'];
        }

        $response = wp_safe_remote_post($this->is_sandbox() ? self::SANDBOX_REQUEST_URL : self::REQUEST_URL, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'merchant_id'  => $merchant_id,
                'amount'       => $amount_toman * 10, // تومان -> ریال
                'description'  => $description,
                'callback_url' => $callback_url,
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => 'خطا در اتصال به درگاه پرداخت: ' . $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return ['success' => false, 'message' => 'پاسخ نامعتبر از درگاه پرداخت دریافت شد.'];
        }

        $code = $body['data']['code'] ?? null;

        if ($code !== 100 || empty($body['data']['authority'])) {
            $error_message = $body['errors']['message'] ?? 'کد نامشخص از درگاه پرداخت دریافت شد.';

            return ['success' => false, 'message' => $error_message];
        }

        $authority = (string) $body['data']['authority'];
        if (!preg_match('/^[A-Za-z0-9_-]{10,128}$/', $authority)) {
            return ['success' => false, 'message' => 'شناسه تراکنش درگاه معتبر نیست.'];
        }

        return [
            'success'     => true,
            'authority'   => $authority,
            'gateway_url' => ($this->is_sandbox() ? self::SANDBOX_GATEWAY_URL : self::GATEWAY_URL) . rawurlencode($authority),
        ];
    }

    /**
     * @return array{success: bool, ref_id?: string, message?: string}
     */
    public function verify_payment(string $authority, int $amount_toman): array
    {
        $merchant_id = $this->merchant_id();

        if (empty($merchant_id)) {
            return ['success' => false, 'message' => 'درگاه پرداخت هنوز پیکربندی نشده است.'];
        }

        $authority = trim($authority);
        if (!preg_match('/^[A-Za-z0-9_-]{10,128}$/', $authority)) {
            return ['success' => false, 'message' => 'شناسه تراکنش نامعتبر است.'];
        }

        $response = wp_safe_remote_post($this->is_sandbox() ? self::SANDBOX_VERIFY_URL : self::VERIFY_URL, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'merchant_id' => $merchant_id,
                'amount'      => $amount_toman * 10,
                'authority'   => $authority,
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return ['success' => false, 'message' => 'پاسخ نامعتبر از درگاه پرداخت دریافت شد.'];
        }

        $code = $body['data']['code'] ?? null;

        // کد ۱۰۰: تایید موفق. کد ۱۰۱: تراکنش قبلاً تایید شده (idempotent — نباید خطا حساب شود).
        if ($code === 100 || $code === 101) {
            return ['success' => true, 'ref_id' => (string) ($body['data']['ref_id'] ?? '')];
        }

        return ['success' => false, 'message' => $body['errors']['message'] ?? 'تایید پرداخت ناموفق بود.'];
    }
}
