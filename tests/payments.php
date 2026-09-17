<?php
/**
 * Payment verification test.
 *
 * A stub RazorpayService replaces only the two methods that talk to the
 * network, so the real CheckoutService / SubscriptionService / invoice code
 * paths are exercised end to end without contacting Razorpay.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Crypto;
use App\Core\Database;
use App\Core\Settings;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\RazorpayService;

$pass = 0; $fail = 0;
function ok(string $m): void { global $pass; echo "  PASS  $m\n"; $pass++; }
function bad(string $m): void { global $fail; echo "  FAIL  $m\n"; $fail++; }
function chk(bool $c, string $m): void { $c ? ok($m) : bad($m); }

// --- Configure a fake gateway --------------------------------------------
Settings::set('razorpay_key_id', 'rzp_test_FAKEKEY123', 'payment');
Settings::set('razorpay_key_secret', 'fake_secret_abcdef', 'payment');
Settings::set('razorpay_webhook_secret', 'whsec_test_123456', 'payment');
Settings::set('gst_enabled', true, 'payment', 'boolean');
Settings::set('gst_rate', 18, 'payment', 'float');
Settings::set('gst_state', 'Gujarat', 'payment');
Settings::set('gst_number', '24ABCDE1234F1Z5', 'payment');
Settings::flush();
Settings::load(true);

/** Stubbed gateway: local logic is real, network calls are simulated. */
final class StubRazorpay extends RazorpayService
{
    /** @var array<string,array<string,mixed>> */
    public array $payments = [];

    /** @var array<string,array<string,mixed>> */
    public array $orders = [];

    public function createOrder(float $amount, string $currency, string $receipt, array $notes = []): array
    {
        $id = 'order_' . substr(md5($receipt . microtime()), 0, 14);
        $this->orders[$id] = [
            'id' => $id, 'amount' => $this->toSubunits($amount),
            'currency' => strtoupper($currency), 'receipt' => $receipt, 'status' => 'created',
        ];

        return $this->orders[$id];
    }

    public function fetchPayment(string $paymentId): array
    {
        if (!isset($this->payments[$paymentId])) {
            throw new RuntimeException('Payment not found at the gateway.');
        }

        return $this->payments[$paymentId];
    }

    public function capture(string $paymentId, float $amount, string $currency = 'INR'): array
    {
        $this->payments[$paymentId]['status'] = 'captured';

        return $this->payments[$paymentId];
    }

    /** Test helper: register what the gateway would report for a payment. */
    public function seedPayment(string $id, string $orderId, float $amount, string $status = 'captured'): void
    {
        $this->payments[$id] = [
            'id' => $id, 'order_id' => $orderId, 'amount' => $this->toSubunits($amount),
            'currency' => 'INR', 'status' => $status, 'method' => 'upi', 'captured' => $status === 'captured',
        ];
    }
}

$gateway = new StubRazorpay();
$checkout = new CheckoutService($gateway);
$db = Database::instance();

$user = (new User())->findByEmail('akshay@example.com');
$plan = (new Plan())->findBySlug('pro');
echo "Customer: {$user['email']}  Plan: {$plan['name']} (" . money((float) $plan['price']) . ")\n\n";

// --- 1. GST breakdown -----------------------------------------------------
echo "== 1. Price breakdown with GST ==\n";
$breakdown = $checkout->priceBreakdown($plan);
printf("  subtotal=%s tax(%.0f%%)=%s total=%s\n", money($breakdown['subtotal']), $breakdown['tax_rate'], money($breakdown['tax_amount']), money($breakdown['total']));
chk(abs($breakdown['subtotal'] - 999.00) < 0.01, 'subtotal matches the plan price');
chk(abs($breakdown['tax_amount'] - 179.82) < 0.01, 'GST calculated at 18%');
chk(abs($breakdown['total'] - 1178.82) < 0.01, 'total = subtotal + GST');

// --- 2. Order creation ----------------------------------------------------
echo "\n== 2. Order creation ==\n";
$created = $checkout->createOrder($user, $plan);
$order = $created['order'];
$gatewayOrderId = (string) $created['gateway_order']['id'];
printf("  order %s → gateway %s\n", $order['order_number'], $gatewayOrderId);
chk((string) $order['status'] === 'pending', 'local order starts as pending');
chk(abs((float) $order['total'] - $breakdown['total']) < 0.01, 'order total matches the breakdown');
chk((int) $created['gateway_order']['amount'] === 117882, 'gateway amount sent in paise');

$sign = static fn (string $payload): string => Crypto::hmac($payload, 'fake_secret_abcdef');

// --- 3. Forged signature --------------------------------------------------
echo "\n== 3. Tampering: forged signature ==\n";
$gateway->seedPayment('pay_forged', $gatewayOrderId, $breakdown['total']);
$result = $checkout->confirmPayment($gatewayOrderId, 'pay_forged', 'deadbeef' . str_repeat('0', 56), (int) $user['id']);
chk(!$result['success'], 'forged signature rejected');
chk((string) (new Order())->find((int) $order['id'])['status'] !== 'paid', 'order NOT marked paid');
chk((new Subscription())->firstWhere(['order_id' => (int) $order['id'], 'status' => 'active']) === null, 'no subscription created');
echo "  message: {$result['message']}\n";

// --- 4. Amount tampering --------------------------------------------------
echo "\n== 4. Tampering: gateway reports a smaller amount ==\n";
$created2 = $checkout->createOrder($user, $plan);
$order2 = $created2['order'];
$gw2 = (string) $created2['gateway_order']['id'];
$gateway->seedPayment('pay_cheap', $gw2, 1.00);          // paid ₹1 instead of ₹1178.82
$result = $checkout->confirmPayment($gw2, 'pay_cheap', $sign($gw2 . '|pay_cheap'), (int) $user['id']);
chk(!$result['success'], 'amount mismatch rejected even with a VALID signature');
chk((string) (new Order())->find((int) $order2['id'])['status'] === 'failed', 'order marked failed');
echo "  message: {$result['message']}\n";

// --- 5. Order mismatch ----------------------------------------------------
echo "\n== 5. Tampering: payment belongs to a different order ==\n";
$created3 = $checkout->createOrder($user, $plan);
$order3 = $created3['order'];
$gw3 = (string) $created3['gateway_order']['id'];
$gateway->seedPayment('pay_other', 'order_SOMEONE_ELSE', $breakdown['total']);
$result = $checkout->confirmPayment($gw3, 'pay_other', $sign($gw3 . '|pay_other'), (int) $user['id']);
chk(!$result['success'], 'payment from another order rejected');
echo "  message: {$result['message']}\n";

// --- 6. Cross-account ------------------------------------------------------
echo "\n== 6. Tampering: another user's order ==\n";
$other = (new User())->findByEmail('jaydeep@example.com');
$created4 = $checkout->createOrder($user, $plan);
$gw4 = (string) $created4['gateway_order']['id'];
$gateway->seedPayment('pay_x', $gw4, $breakdown['total']);
$result = $checkout->confirmPayment($gw4, 'pay_x', $sign($gw4 . '|pay_x'), (int) $other['id']);
chk(!$result['success'], "another account cannot confirm someone else's order");
echo "  message: {$result['message']}\n";

// --- 7. Unsuccessful payment status ----------------------------------------
echo "\n== 7. Gateway reports a failed payment ==\n";
$created5 = $checkout->createOrder($user, $plan);
$gw5 = (string) $created5['gateway_order']['id'];
$gateway->seedPayment('pay_failed', $gw5, $breakdown['total'], 'failed');
$result = $checkout->confirmPayment($gw5, 'pay_failed', $sign($gw5 . '|pay_failed'), (int) $user['id']);
chk(!$result['success'], 'failed payment does not activate the plan');
echo "  message: {$result['message']}\n";

// --- 8. Happy path ----------------------------------------------------------
echo "\n== 8. Genuine payment ==\n";
$before = (new Subscription())->activeFor((int) $user['id']);
$created6 = $checkout->createOrder($user, $plan);
$order6 = $created6['order'];
$gw6 = (string) $created6['gateway_order']['id'];
$gateway->seedPayment('pay_good', $gw6, $breakdown['total']);
$result = $checkout->confirmPayment($gw6, 'pay_good', $sign($gw6 . '|pay_good'), (int) $user['id']);
chk($result['success'], 'genuine payment accepted');

$fresh = (new Order())->find((int) $order6['id']);
chk((string) $fresh['status'] === 'paid', 'order marked paid');
chk($fresh['paid_at'] !== null, 'paid_at recorded');

$subscription = (new Subscription())->activeFor((int) $user['id']);
chk($subscription !== null && (int) $subscription['plan_id'] === (int) $plan['id'], 'subscription activated on the purchased plan');
printf("  subscription #%d valid until %s\n", (int) $subscription['id'], (string) $subscription['ends_at']);

$payment = (new Payment())->findByGatewayPaymentId('pay_good');
chk($payment !== null && (string) $payment['status'] === 'captured', 'payment row recorded as captured');
chk($payment['verified_at'] !== null, 'payment marked server-verified');

$invoice = (new Invoice())->findByOrder((int) $order6['id']);
chk($invoice !== null, 'GST invoice generated');
if ($invoice !== null) {
    printf("  invoice %s — subtotal %s, CGST %s, SGST %s, IGST %s, total %s\n",
        $invoice['invoice_number'], money((float) $invoice['subtotal']),
        money((float) $invoice['cgst']), money((float) $invoice['sgst']),
        money((float) $invoice['igst']), money((float) $invoice['total']));
    $taxSum = (float) $invoice['cgst'] + (float) $invoice['sgst'] + (float) $invoice['igst'];
    chk(abs($taxSum - $breakdown['tax_amount']) < 0.02, 'invoice tax equals the charged GST');
    chk(preg_match('#^INV/\d{4}-\d{2}/\d{6}$#', (string) $invoice['invoice_number']) === 1, 'invoice number is financial-year sequential');
}

// --- 9. Idempotency ---------------------------------------------------------
echo "\n== 9. Replay / duplicate confirmation ==\n";
$countBefore = (new Subscription())->count(['user_id' => (int) $user['id'], 'status' => 'active']);
$result = $checkout->confirmPayment($gw6, 'pay_good', $sign($gw6 . '|pay_good'), (int) $user['id']);
$countAfter = (new Subscription())->count(['user_id' => (int) $user['id'], 'status' => 'active']);
chk($result['success'], 'replayed confirmation returns success (idempotent)');
chk($countBefore === $countAfter, 'no duplicate subscription created on replay');
chk((new Payment())->count(['gateway_payment_id' => 'pay_good']) === 1, 'no duplicate payment row');
chk((new Invoice())->count(['order_id' => (int) $order6['id']]) === 1, 'no duplicate invoice');

// --- 10. Webhook signature ---------------------------------------------------
echo "\n== 10. Webhook signature verification ==\n";
$payload = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_hook', 'order_id' => $gw6]]]]);
chk($gateway->verifyWebhookSignature($payload, Crypto::hmac($payload, 'whsec_test_123456')), 'valid webhook signature accepted');
chk(!$gateway->verifyWebhookSignature($payload, 'wrongsignature'), 'invalid webhook signature rejected');
chk(!$gateway->verifyWebhookSignature($payload . 'x', Crypto::hmac($payload, 'whsec_test_123456')), 'tampered webhook body rejected');

echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
