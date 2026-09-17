<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\AuditLog;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Response;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\RazorpayService;
use Throwable;

/**
 * Razorpay webhook receiver.
 *
 * Security: the raw body is verified against the webhook secret before it is
 * parsed. Idempotency: every event id is recorded, so a redelivered webhook
 * can never activate a second subscription for the same payment.
 */
final class WebhookController extends Controller
{
    public function razorpay(): Response
    {
        $payload = $this->request->rawBody();
        $signature = $this->request->header('X-Razorpay-Signature');
        $gateway = new RazorpayService();

        if ($gateway->webhookSecret() === '') {
            Logger::warning('Razorpay webhook received but no webhook secret is configured.');

            return Response::json(['success' => false, 'message' => 'Webhook not configured.'], 503);
        }

        if (!$gateway->verifyWebhookSignature($payload, $signature)) {
            Logger::warning('Razorpay webhook signature mismatch', ['ip' => $this->request->ip()]);

            return Response::json(['success' => false, 'message' => 'Invalid signature.'], 401);
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return Response::json(['success' => false, 'message' => 'Malformed payload.'], 400);
        }

        $eventType = (string) ($data['event'] ?? '');
        $eventId = (string) ($this->request->header('X-Razorpay-Event-Id') ?: ($data['id'] ?? hash('sha256', $payload)));
        $db = Database::instance();

        // Idempotency guard — a duplicate delivery stops right here.
        $existing = $db->selectOne(
            'SELECT `id`, `status` FROM `' . $db->table('webhook_events') . '` WHERE `gateway` = :g AND `event_id` = :e',
            ['g' => 'razorpay', 'e' => $eventId]
        );
        if ($existing !== null) {
            Logger::info('Duplicate Razorpay webhook ignored', ['event_id' => $eventId, 'type' => $eventType]);

            return Response::json(['success' => true, 'message' => 'Already processed.']);
        }

        $logId = $db->insert('webhook_events', [
            'gateway'      => 'razorpay',
            'event_id'     => $eventId,
            'event_type'   => substr($eventType, 0, 80),
            'payload_hash' => hash('sha256', $payload),
            'status'       => 'received',
            'payload'      => json_encode($this->scrub($data), JSON_UNESCAPED_UNICODE),
            'created_at'   => now(),
        ]);

        try {
            $handled = $this->handle($eventType, $data);

            $db->update('webhook_events', [
                'status'       => $handled ? 'processed' : 'ignored',
                'processed_at' => now(),
            ], ['id' => $logId]);

            return Response::json(['success' => true, 'handled' => $handled]);
        } catch (Throwable $e) {
            Logger::error('Razorpay webhook processing failed: ' . $e->getMessage(), ['event' => $eventType]);
            $db->update('webhook_events', [
                'status'       => 'failed',
                'error'        => substr($e->getMessage(), 0, 250),
                'processed_at' => now(),
            ], ['id' => $logId]);

            // 200 keeps Razorpay from hammering us; the failure is recorded
            // for the administrator to replay manually.
            return Response::json(['success' => false, 'message' => 'Processing failed.'], 200);
        }
    }

    /** @param array<string,mixed> $data */
    private function handle(string $eventType, array $data): bool
    {
        $payment = $data['payload']['payment']['entity'] ?? null;
        if (!is_array($payment)) {
            return false;
        }

        $paymentId = (string) ($payment['id'] ?? '');
        $gatewayOrderId = (string) ($payment['order_id'] ?? '');
        if ($paymentId === '' || $gatewayOrderId === '') {
            return false;
        }

        $orders = new Order();
        $order = $orders->findByGatewayOrderId($gatewayOrderId);
        if ($order === null) {
            Logger::info('Webhook for an unknown order', ['gateway_order_id' => $gatewayOrderId]);

            return false;
        }

        return match ($eventType) {
            'payment.captured', 'order.paid' => $this->settle($order, $paymentId),
            'payment.failed'                 => $this->markFailed($order, $payment),
            'refund.created', 'refund.processed' => $this->markRefunded($order),
            default => false,
        };
    }

    /** @param array<string,mixed> $order */
    private function settle(array $order, string $paymentId): bool
    {
        if ((string) $order['status'] === 'paid') {
            return true;                             // already activated
        }

        $result = (new CheckoutService())->settle($order, $paymentId, '', 'webhook');

        AuditLog::record('payment.webhook_settled', 'order', (int) $order['id'], [
            'payment_id' => $paymentId,
            'success'    => $result['success'],
        ], (int) $order['user_id']);

        return $result['success'];
    }

    /**
     * @param array<string,mixed> $order
     * @param array<string,mixed> $payment
     */
    private function markFailed(array $order, array $payment): bool
    {
        if ((string) $order['status'] === 'paid') {
            return false;                            // do not undo a paid order
        }

        (new Order())->updateById((int) $order['id'], ['status' => 'failed']);
        Logger::info('Payment failed via webhook', [
            'order'  => $order['order_number'],
            'reason' => (string) ($payment['error_description'] ?? ''),
        ]);

        return true;
    }

    /** @param array<string,mixed> $order */
    private function markRefunded(array $order): bool
    {
        (new Order())->updateById((int) $order['id'], ['status' => 'refunded']);

        $subscriptionId = $order['subscription_id'] ?? null;
        if ($subscriptionId !== null) {
            (new \App\Services\SubscriptionService())->cancel((int) $subscriptionId, 'Payment refunded');
        }

        AuditLog::record('payment.refunded', 'order', (int) $order['id'], [], (int) $order['user_id']);

        return true;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function scrub(array $data): array
    {
        unset($data['payload']['payment']['entity']['card'], $data['payload']['payment']['entity']['notes']);

        return $data;
    }
}
