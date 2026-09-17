<?php
/** Proves the webhook settlement path activates a plan when — and only when —
 *  the gateway itself confirms the payment. */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\CheckoutService;
use App\Services\RazorpayService;

$pass = 0; $fail = 0;
function chk(bool $c, string $m): void { global $pass, $fail; if ($c) { echo "  PASS  $m\n"; $pass++; } else { echo "  FAIL  $m\n"; $fail++; } }

final class ConfirmingGateway extends RazorpayService
{
    public function __construct(private readonly array $entity) { parent::__construct(); }
    public function fetchPayment(string $paymentId): array { return $this->entity; }
    public function capture(string $paymentId, float $amount, string $currency = 'INR'): array { return $this->entity; }
}

final class SilentGateway extends RazorpayService
{
    public function fetchPayment(string $paymentId): array { throw new RuntimeException('Gateway unreachable'); }
}

$orders = new Order();
$order = $orders->firstWhere(['status' => 'pending']);
if ($order === null) { exit("No pending order to test with.\n"); }
printf("  using order %s (%s)\n", $order['order_number'], money((float) $order['total']));

echo "\n== Gateway unreachable ==\n";
$result = (new CheckoutService(new SilentGateway()))->settle($order, 'pay_unreachable', '', 'webhook');
chk(!$result['success'], 'no activation when the gateway cannot be reached');
chk((string) $orders->find((int) $order['id'])['status'] === 'pending', 'order stays pending');

echo "\n== Gateway confirms the payment ==\n";
$entity = [
    'id' => 'pay_confirmed_1', 'order_id' => (string) $order['gateway_order_id'],
    'amount' => (int) round((float) $order['total'] * 100), 'currency' => 'INR',
    'status' => 'captured', 'method' => 'netbanking',
];
$result = (new CheckoutService(new ConfirmingGateway($entity)))->settle($order, 'pay_confirmed_1', '', 'webhook');
chk($result['success'], 'payment settled through the webhook path');
chk((string) $orders->find((int) $order['id'])['status'] === 'paid', 'order marked paid');
chk((new Subscription())->firstWhere(['order_id' => (int) $order['id'], 'status' => 'active']) !== null, 'subscription activated');
chk((new Payment())->findByGatewayPaymentId('pay_confirmed_1') !== null, 'payment recorded');
chk((new Invoice())->findByOrder((int) $order['id']) !== null, 'invoice issued');

echo "\n== Replay of the same settlement ==\n";
$subsBefore = (new Subscription())->count([]);
(new CheckoutService(new ConfirmingGateway($entity)))->settle((array) $orders->find((int) $order['id']), 'pay_confirmed_1', '', 'webhook');
chk((new Subscription())->count([]) === $subsBefore, 'replay creates no extra subscription');
chk((new Payment())->count(['gateway_payment_id' => 'pay_confirmed_1']) === 1, 'replay creates no extra payment');

echo "\nRESULT: $pass passed, $fail failed\n";
