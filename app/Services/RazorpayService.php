<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\Env;
use App\Core\Http;
use App\Core\Logger;
use App\Core\Settings;
use RuntimeException;

/**
 * Razorpay integration built directly on the REST API (no Composer package
 * required). Payments are ALWAYS verified server side — the browser's
 * success callback alone never activates a subscription.
 */
final class RazorpayService
{
    private const API = 'https://api.razorpay.com/v1';

    private string $keyId;

    private string $keySecret;

    public function __construct()
    {
        $this->keyId = (string) (Settings::get('razorpay_key_id') ?: Env::get('RAZORPAY_KEY_ID', ''));
        $this->keySecret = (string) (Settings::get('razorpay_key_secret') ?: Env::get('RAZORPAY_KEY_SECRET', ''));
    }

    public function isConfigured(): bool
    {
        return $this->keyId !== '' && $this->keySecret !== '';
    }

    public function keyId(): string
    {
        return $this->keyId;
    }

    public function webhookSecret(): string
    {
        return (string) (Settings::get('razorpay_webhook_secret') ?: Env::get('RAZORPAY_WEBHOOK_SECRET', ''));
    }

    public function isTestMode(): bool
    {
        return str_starts_with($this->keyId, 'rzp_test');
    }

    /**
     * Create a Razorpay order.
     *
     * @param array<string,string> $notes
     * @return array<string,mixed>
     */
    public function createOrder(float $amount, string $currency, string $receipt, array $notes = []): array
    {
        $this->assertConfigured();

        $response = Http::post(
            self::API . '/orders',
            [
                'amount'          => $this->toSubunits($amount),
                'currency'        => strtoupper($currency),
                'receipt'         => substr($receipt, 0, 40),
                'payment_capture' => 1,
                'notes'           => $this->sanitiseNotes($notes),
            ],
            ['Content-Type' => 'application/json'],
            30,
            $this->keyId . ':' . $this->keySecret
        );

        if ($response['status'] !== 200 || !is_array($response['json'])) {
            $message = (string) ($response['json']['error']['description'] ?? ($response['error'] !== '' ? $response['error'] : 'Unknown gateway error'));
            Logger::error('Razorpay order creation failed', ['status' => $response['status'], 'message' => $message]);
            throw new RuntimeException('Payment gateway error: ' . $message);
        }

        return $response['json'];
    }

    /**
     * Verify the checkout handler signature:
     *   HMAC_SHA256(order_id + "|" + payment_id, key_secret) === signature
     */
    public function verifyPaymentSignature(string $razorpayOrderId, string $razorpayPaymentId, string $signature): bool
    {
        if ($this->keySecret === '' || $signature === '') {
            return false;
        }

        return Crypto::hmacEquals($razorpayOrderId . '|' . $razorpayPaymentId, $this->keySecret, $signature);
    }

    /** Verify a webhook body against the configured webhook secret. */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = $this->webhookSecret();
        if ($secret === '' || $signature === '') {
            return false;
        }

        return Crypto::hmacEquals($payload, $secret, $signature);
    }

    /**
     * Fetch a payment from Razorpay. This is the authoritative check — the
     * status returned here (not the browser) decides whether an order is paid.
     *
     * @return array<string,mixed>
     */
    public function fetchPayment(string $paymentId): array
    {
        $this->assertConfigured();

        $response = Http::get(
            self::API . '/payments/' . rawurlencode($paymentId),
            [],
            30,
            $this->keyId . ':' . $this->keySecret
        );

        if ($response['status'] !== 200 || !is_array($response['json'])) {
            $message = (string) ($response['json']['error']['description'] ?? 'Could not fetch payment');
            throw new RuntimeException('Payment verification failed: ' . $message);
        }

        return $response['json'];
    }

    /** @return array<string,mixed> */
    public function fetchOrder(string $orderId): array
    {
        $this->assertConfigured();

        $response = Http::get(
            self::API . '/orders/' . rawurlencode($orderId),
            [],
            30,
            $this->keyId . ':' . $this->keySecret
        );

        if ($response['status'] !== 200 || !is_array($response['json'])) {
            throw new RuntimeException('Could not fetch the order from the payment gateway.');
        }

        return $response['json'];
    }

    /**
     * Capture an authorised payment (only needed when auto-capture is off).
     *
     * @return array<string,mixed>
     */
    public function capture(string $paymentId, float $amount, string $currency = 'INR'): array
    {
        $this->assertConfigured();

        $response = Http::post(
            self::API . '/payments/' . rawurlencode($paymentId) . '/capture',
            ['amount' => $this->toSubunits($amount), 'currency' => strtoupper($currency)],
            ['Content-Type' => 'application/json'],
            30,
            $this->keyId . ':' . $this->keySecret
        );

        if ($response['status'] !== 200 || !is_array($response['json'])) {
            throw new RuntimeException('Payment capture failed.');
        }

        return $response['json'];
    }

    /** @return array<string,mixed> */
    public function refund(string $paymentId, ?float $amount = null, string $reason = ''): array
    {
        $this->assertConfigured();

        $body = [];
        if ($amount !== null) {
            $body['amount'] = $this->toSubunits($amount);
        }
        if ($reason !== '') {
            $body['notes'] = ['reason' => substr($reason, 0, 250)];
        }

        $response = Http::post(
            self::API . '/payments/' . rawurlencode($paymentId) . '/refund',
            $body === [] ? '{}' : $body,
            ['Content-Type' => 'application/json'],
            30,
            $this->keyId . ':' . $this->keySecret
        );

        if (!in_array($response['status'], [200, 201], true) || !is_array($response['json'])) {
            $message = (string) ($response['json']['error']['description'] ?? 'Refund failed');
            throw new RuntimeException('Refund failed: ' . $message);
        }

        return $response['json'];
    }

    /** Connectivity/credential check used by the admin settings screen. */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Razorpay key id and key secret are not configured.'];
        }

        $response = Http::get(self::API . '/payments?count=1', [], 20, $this->keyId . ':' . $this->keySecret);

        if ($response['status'] === 200) {
            return ['success' => true, 'message' => 'Connected to Razorpay in ' . ($this->isTestMode() ? 'TEST' : 'LIVE') . ' mode.'];
        }
        if ($response['status'] === 401) {
            return ['success' => false, 'message' => 'Razorpay rejected the credentials (401 Unauthorized).'];
        }

        return [
            'success' => false,
            'message' => 'Could not reach Razorpay (HTTP ' . $response['status'] . ') ' . $response['error'],
        ];
    }

    public function toSubunits(float $amount): int
    {
        return (int) round($amount * 100);
    }

    public static function fromSubunits(int $subunits): float
    {
        return round($subunits / 100, 2);
    }

    /**
     * @param array<string,string> $notes
     * @return array<string,string>
     */
    private function sanitiseNotes(array $notes): array
    {
        $clean = [];
        $count = 0;
        foreach ($notes as $key => $value) {
            if ($count++ >= 15) {
                break;
            }
            $clean[substr((string) $key, 0, 250)] = substr((string) $value, 0, 250);
        }

        return $clean;
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Online payments are not available yet. Please contact support.');
        }
    }
}
