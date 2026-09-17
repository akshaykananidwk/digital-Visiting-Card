<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AuditLog;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Settings;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use RuntimeException;

/**
 * Orchestrates plan purchase: order creation, server-side verification,
 * subscription activation and GST invoice generation — all transactional and
 * idempotent so a retried callback or a duplicated webhook is harmless.
 */
final class CheckoutService
{
    private Database $db;

    private RazorpayService $razorpay;

    public function __construct(?RazorpayService $razorpay = null)
    {
        $this->db = Database::instance();
        $this->razorpay = $razorpay ?? new RazorpayService();
    }

    public function gateway(): RazorpayService
    {
        return $this->razorpay;
    }

    /**
     * Compute the price breakdown for a plan (GST inclusive/exclusive as
     * configured by the administrator).
     *
     * @param array<string,mixed> $plan
     * @return array{subtotal:float,tax_rate:float,tax_amount:float,total:float,currency:string,gst_enabled:bool}
     */
    public function priceBreakdown(array $plan, ?string $buyerState = null): array
    {
        $price = round((float) $plan['price'], 2);
        $gstEnabled = (bool) Settings::get('gst_enabled', false);
        $rate = $gstEnabled ? (float) (Settings::get('gst_rate') ?: 18) : 0.0;
        $inclusive = (bool) Settings::get('gst_inclusive', false);

        if (!$gstEnabled || $rate <= 0) {
            return [
                'subtotal' => $price, 'tax_rate' => 0.0, 'tax_amount' => 0.0,
                'total' => $price, 'currency' => (string) ($plan['currency'] ?? 'INR'), 'gst_enabled' => false,
            ];
        }

        if ($inclusive) {
            $subtotal = round($price / (1 + $rate / 100), 2);
            $tax = round($price - $subtotal, 2);
            $total = $price;
        } else {
            $subtotal = $price;
            $tax = round($price * $rate / 100, 2);
            $total = round($subtotal + $tax, 2);
        }

        return [
            'subtotal'    => $subtotal,
            'tax_rate'    => $rate,
            'tax_amount'  => $tax,
            'total'       => $total,
            'currency'    => (string) ($plan['currency'] ?? 'INR'),
            'gst_enabled' => true,
        ];
    }

    /**
     * Create a local order plus the matching Razorpay order.
     *
     * @param array<string,mixed> $user
     * @param array<string,mixed> $plan
     * @return array{order:array<string,mixed>,gateway_order:array<string,mixed>,breakdown:array<string,mixed>}
     */
    public function createOrder(array $user, array $plan, ?string $gstin = null): array
    {
        if ((int) ($plan['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('This plan is not available.');
        }

        $breakdown = $this->priceBreakdown($plan);
        if ($breakdown['total'] <= 0) {
            throw new RuntimeException('This plan is free — no payment is required.');
        }

        $orders = new Order();
        $orderNumber = $orders->generateNumber();

        $orderId = $orders->create([
            'order_number'   => $orderNumber,
            'user_id'        => (int) $user['id'],
            'plan_id'        => (int) $plan['id'],
            'reseller_id'    => $user['reseller_id'] ?? null,
            'status'         => 'pending',
            'gateway'        => 'razorpay',
            'subtotal'       => $breakdown['subtotal'],
            'tax_rate'       => $breakdown['tax_rate'],
            'tax_amount'     => $breakdown['tax_amount'],
            'total'          => $breakdown['total'],
            'currency'       => $breakdown['currency'],
            'customer_gstin' => $gstin,
            'meta'           => ['plan_name' => $plan['name'], 'source' => 'self'],
            'created_at'     => now(),
        ]);

        try {
            $gatewayOrder = $this->razorpay->createOrder(
                $breakdown['total'],
                $breakdown['currency'],
                $orderNumber,
                [
                    'order_number' => $orderNumber,
                    'plan'         => (string) $plan['slug'],
                    'user_id'      => (string) $user['id'],
                ]
            );
        } catch (\Throwable $e) {
            $orders->updateById($orderId, ['status' => 'failed', 'notes' => substr($e->getMessage(), 0, 250)]);
            throw $e;
        }

        $orders->updateById($orderId, ['gateway_order_id' => (string) $gatewayOrder['id']]);

        AuditLog::record('order.created', 'order', $orderId, [
            'order_number' => $orderNumber,
            'plan'         => $plan['slug'],
            'total'        => $breakdown['total'],
        ], (int) $user['id']);

        return [
            'order'         => (array) $orders->find($orderId),
            'gateway_order' => $gatewayOrder,
            'breakdown'     => $breakdown,
        ];
    }

    /**
     * Verify a checkout result and, when genuinely paid, activate the plan.
     *
     * The signature is checked first (cheap, local) and the payment is then
     * re-fetched from Razorpay. Only a captured/authorised payment whose
     * amount and order id match our record activates the subscription.
     *
     * @return array{success:bool,message:string,order:array<string,mixed>|null,subscription:array<string,mixed>|null}
     */
    public function confirmPayment(string $gatewayOrderId, string $paymentId, string $signature, ?int $expectedUserId = null): array
    {
        $orders = new Order();
        $order = $orders->findByGatewayOrderId($gatewayOrderId);

        if ($order === null) {
            return ['success' => false, 'message' => 'We could not find that order.', 'order' => null, 'subscription' => null];
        }
        if ($expectedUserId !== null && (int) $order['user_id'] !== $expectedUserId) {
            Logger::warning('Payment confirmation for a foreign order was blocked', [
                'order_id' => $order['id'], 'expected_user' => $expectedUserId,
            ]);

            return ['success' => false, 'message' => 'This order does not belong to your account.', 'order' => null, 'subscription' => null];
        }

        // Already processed (user refreshed / webhook won the race).
        if ((string) $order['status'] === 'paid') {
            $subscription = $order['subscription_id'] !== null
                ? (new \App\Models\Subscription())->find((int) $order['subscription_id'])
                : null;

            return ['success' => true, 'message' => 'Payment already confirmed.', 'order' => $order, 'subscription' => $subscription];
        }

        if (!$this->razorpay->verifyPaymentSignature($gatewayOrderId, $paymentId, $signature)) {
            $this->recordFailure($order, $paymentId, 'SIGNATURE_MISMATCH', 'Payment signature verification failed.');
            Logger::warning('Razorpay signature mismatch', ['order_id' => $order['id'], 'payment_id' => $paymentId]);

            return ['success' => false, 'message' => 'We could not verify this payment. If money was deducted it will be refunded automatically.', 'order' => $order, 'subscription' => null];
        }

        return $this->settle($order, $paymentId, $signature, 'checkout');
    }

    /**
     * Settle a payment against an order: authoritative gateway fetch, amount
     * check, then transactional activation. Shared by the browser callback
     * and the webhook handler.
     *
     * @param array<string,mixed> $order
     * @return array{success:bool,message:string,order:array<string,mixed>|null,subscription:array<string,mixed>|null}
     */
    public function settle(array $order, string $paymentId, string $signature = '', string $source = 'webhook'): array
    {
        $orders = new Order();
        $payments = new Payment();

        try {
            $remote = $this->razorpay->fetchPayment($paymentId);
        } catch (\Throwable $e) {
            Logger::error('Could not fetch payment from Razorpay: ' . $e->getMessage(), ['payment_id' => $paymentId]);

            return ['success' => false, 'message' => 'We could not reach the payment gateway. Please try again in a moment.', 'order' => $order, 'subscription' => null];
        }

        $status = (string) ($remote['status'] ?? '');
        $remoteOrderId = (string) ($remote['order_id'] ?? '');
        $remoteAmount = RazorpayService::fromSubunits((int) ($remote['amount'] ?? 0));

        if ($remoteOrderId !== (string) $order['gateway_order_id']) {
            $this->recordFailure($order, $paymentId, 'ORDER_MISMATCH', 'Payment belongs to a different order.');

            return ['success' => false, 'message' => 'This payment does not belong to the selected order.', 'order' => $order, 'subscription' => null];
        }
        if (abs($remoteAmount - (float) $order['total']) > 0.009) {
            $this->recordFailure($order, $paymentId, 'AMOUNT_MISMATCH', 'Paid amount does not match the order total.');
            Logger::error('Razorpay amount mismatch', [
                'order_id' => $order['id'], 'expected' => $order['total'], 'received' => $remoteAmount,
            ]);

            return ['success' => false, 'message' => 'The paid amount does not match this order.', 'order' => $order, 'subscription' => null];
        }
        if (!in_array($status, ['captured', 'authorized'], true)) {
            $this->recordFailure($order, $paymentId, strtoupper($status ?: 'UNKNOWN'), (string) ($remote['error_description'] ?? 'Payment was not successful.'));

            return ['success' => false, 'message' => 'The payment was not completed (' . ($status ?: 'unknown status') . ').', 'order' => $order, 'subscription' => null];
        }

        if ($status === 'authorized') {
            try {
                $remote = $this->razorpay->capture($paymentId, (float) $order['total'], (string) $order['currency']);
            } catch (\Throwable $e) {
                Logger::warning('Auto capture failed, continuing on authorisation: ' . $e->getMessage());
            }
        }

        $subscription = $this->db->transaction(function () use ($order, $orders, $payments, $paymentId, $signature, $remote, $source) {
            $existingPayment = $payments->findByGatewayPaymentId($paymentId);
            if ($existingPayment === null) {
                $payments->create([
                    'order_id'           => (int) $order['id'],
                    'user_id'            => (int) $order['user_id'],
                    'gateway'            => 'razorpay',
                    'gateway_payment_id' => $paymentId,
                    'gateway_order_id'   => (string) $order['gateway_order_id'],
                    'gateway_signature'  => $signature !== '' ? $signature : null,
                    'method'             => (string) ($remote['method'] ?? ''),
                    'amount'             => RazorpayService::fromSubunits((int) ($remote['amount'] ?? 0)),
                    'currency'           => (string) ($remote['currency'] ?? 'INR'),
                    'status'             => (string) ($remote['status'] ?? 'captured'),
                    'verified_at'        => now(),
                    'raw_response'       => $this->scrubGatewayPayload($remote),
                    'created_at'         => now(),
                ]);
            } else {
                $payments->updateById((int) $existingPayment['id'], [
                    'status'      => (string) ($remote['status'] ?? 'captured'),
                    'verified_at' => now(),
                ]);
            }

            $orders->updateById((int) $order['id'], ['status' => 'paid', 'paid_at' => now()]);
            $fresh = (array) $orders->find((int) $order['id']);

            $subscription = (new SubscriptionService())->activateForOrder($fresh);
            $this->issueInvoice($fresh);

            AuditLog::record('payment.verified', 'order', (int) $order['id'], [
                'payment_id' => $paymentId,
                'amount'     => $order['total'],
                'source'     => $source,
            ], (int) $order['user_id']);

            return $subscription;
        });

        return [
            'success'      => true,
            'message'      => 'Payment verified and your plan is active.',
            'order'        => (array) $orders->find((int) $order['id']),
            'subscription' => $subscription,
        ];
    }

    /** Create the GST invoice for a paid order (idempotent). */
    public function issueInvoice(array $order): ?array
    {
        $invoices = new Invoice();
        $existing = $invoices->findByOrder((int) $order['id']);
        if ($existing !== null) {
            return $existing;
        }

        $user = (new User())->find((int) $order['user_id']);
        $plan = (new Plan())->find((int) $order['plan_id']);
        if ($user === null || $plan === null) {
            return null;
        }

        $sellerState = strtolower((string) (Settings::get('gst_state') ?: ''));
        $buyerState = strtolower((string) ($user['state'] ?? ''));
        $intraState = $sellerState !== '' && $sellerState === $buyerState;

        $tax = (float) $order['tax_amount'];
        $cgst = $intraState ? round($tax / 2, 2) : 0.0;
        $sgst = $intraState ? round($tax - $cgst, 2) : 0.0;
        $igst = $intraState ? 0.0 : $tax;

        $id = $invoices->create([
            'invoice_number'  => $invoices->generateNumber((string) (Settings::get('invoice_prefix') ?: 'INV')),
            'order_id'        => (int) $order['id'],
            'user_id'         => (int) $user['id'],
            'billing_name'    => (string) ($user['company'] ?: $user['name']),
            'billing_email'   => (string) $user['email'],
            'billing_phone'   => (string) ($user['phone'] ?? ''),
            'billing_address' => trim(implode(', ', array_filter([
                $user['address'] ?? '', $user['city'] ?? '', $user['state'] ?? '', $user['pincode'] ?? '',
            ]))),
            'billing_gstin'   => (string) ($order['customer_gstin'] ?: ($user['gstin'] ?? '')),
            'seller_gstin'    => (string) (Settings::get('gst_number') ?: ''),
            'place_of_supply' => (string) ($user['state'] ?? ''),
            'subtotal'        => (float) $order['subtotal'],
            'discount'        => (float) $order['discount'],
            'cgst'            => $cgst,
            'sgst'            => $sgst,
            'igst'            => $igst,
            'total'           => (float) $order['total'],
            'currency'        => (string) $order['currency'],
            'status'          => 'paid',
            'line_items'      => [[
                'description' => (string) $plan['name'] . ' subscription (' . (int) $plan['duration_days'] . ' days)',
                'hsn'         => (string) (Settings::get('gst_hsn') ?: '998314'),
                'quantity'    => 1,
                'rate'        => (float) $order['subtotal'],
                'amount'      => (float) $order['subtotal'],
            ]],
            'issued_at'       => now(),
            'created_at'      => now(),
        ]);

        return $invoices->find($id);
    }

    /** @param array<string,mixed> $order */
    private function recordFailure(array $order, string $paymentId, string $code, string $description): void
    {
        $payments = new Payment();
        if ($payments->findByGatewayPaymentId($paymentId) === null) {
            $payments->create([
                'order_id'           => (int) $order['id'],
                'user_id'            => (int) $order['user_id'],
                'gateway'            => 'razorpay',
                'gateway_payment_id' => $paymentId,
                'gateway_order_id'   => (string) $order['gateway_order_id'],
                'amount'             => (float) $order['total'],
                'currency'           => (string) $order['currency'],
                'status'             => 'failed',
                'error_code'         => substr($code, 0, 60),
                'error_description'  => substr($description, 0, 250),
                'created_at'         => now(),
            ]);
        }

        if ((string) $order['status'] !== 'paid') {
            (new Order())->updateById((int) $order['id'], ['status' => 'failed']);
        }
    }

    /**
     * Keep only non-sensitive gateway fields in our database.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function scrubGatewayPayload(array $payload): array
    {
        $keep = ['id', 'entity', 'amount', 'currency', 'status', 'order_id', 'method', 'captured',
            'description', 'bank', 'wallet', 'vpa', 'fee', 'tax', 'created_at', 'error_code', 'error_description'];

        return array_intersect_key($payload, array_flip($keep));
    }
}
