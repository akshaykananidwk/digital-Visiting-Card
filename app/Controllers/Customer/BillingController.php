<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\AuditLog;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Url;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\CheckoutService;
use App\Services\SubscriptionService;
use Throwable;

/** Plan selection, Razorpay checkout and invoices. */
final class BillingController extends PanelController
{
    public function index(): Response
    {
        $userId = $this->userId();

        return $this->render('customer.billing.index', [
            'title'        => 'Plan & billing',
            'summary'      => (new SubscriptionService())->summaryFor($userId),
            'plans'        => (new Plan())->active(),
            'orders'       => (new Order())->forUser($userId, 20),
            'invoices'     => (new Invoice())->forUser($userId, 20),
            'history'      => (new Subscription())->historyFor($userId, 10),
            'gatewayReady' => (new CheckoutService())->gateway()->isConfigured(),
        ]);
    }

    public function checkout(string $plan): Response
    {
        $userId = $this->userId();
        $planRow = (new Plan())->findBySlug($plan);

        if ($planRow === null || (int) $planRow['is_active'] !== 1) {
            $this->error('That plan is not available.');

            return $this->redirect('billing');
        }

        $checkout = new CheckoutService();

        if ((int) $planRow['is_free'] === 1 || (float) $planRow['price'] <= 0) {
            (new SubscriptionService())->grant($userId, (int) $planRow['id'], 'self');
            $this->success('You are now on the ' . (string) $planRow['name'] . ' plan.');

            return $this->redirect('billing');
        }

        if (!$checkout->gateway()->isConfigured()) {
            $this->error('Online payments are not available right now. Please contact support to activate this plan.');

            return $this->redirect('billing');
        }

        return $this->render('customer.billing.checkout', [
            'title'     => 'Checkout: ' . (string) $planRow['name'],
            'plan'      => $planRow,
            'breakdown' => $checkout->priceBreakdown($planRow),
            'user'      => $this->currentUser(),
            'keyId'     => $checkout->gateway()->keyId(),
            'testMode'  => $checkout->gateway()->isTestMode(),
        ]);
    }

    /** AJAX: create the local + gateway order and return the checkout options. */
    public function createOrder(): Response
    {
        $userId = $this->userId();
        $planRow = (new Plan())->findBySlug($this->request->string('plan'));

        if ($planRow === null || (int) $planRow['is_active'] !== 1) {
            return $this->fail('That plan is not available.', 404);
        }

        $checkout = new CheckoutService();

        try {
            $created = $checkout->createOrder(
                $this->currentUser(),
                $planRow,
                $this->request->string('gstin') ?: null
            );
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $user = $this->currentUser();

        return $this->json([
            'success'  => true,
            'order'    => [
                'id'     => (string) $created['gateway_order']['id'],
                'amount' => (int) $created['gateway_order']['amount'],
                'currency' => (string) $created['gateway_order']['currency'],
                'number' => (string) $created['order']['order_number'],
            ],
            'key'      => $checkout->gateway()->keyId(),
            'name'     => (string) (Settings::get('site_name') ?: 'Digital Visiting Card'),
            'description' => (string) $planRow['name'] . ' plan',
            'prefill'  => [
                'name'    => (string) $user['name'],
                'email'   => (string) $user['email'],
                'contact' => (string) ($user['phone'] ?? ''),
            ],
            'callback' => Url::to('billing/verify'),
        ]);
    }

    /** Server-side verification of the checkout result. */
    public function verify(): Response
    {
        $userId = $this->userId();

        $gatewayOrderId = $this->request->string('razorpay_order_id');
        $paymentId = $this->request->string('razorpay_payment_id');
        $signature = $this->request->string('razorpay_signature');

        if ($gatewayOrderId === '' || $paymentId === '' || $signature === '') {
            return $this->fail('Incomplete payment details were received.', 422);
        }

        $result = (new CheckoutService())->confirmPayment($gatewayOrderId, $paymentId, $signature, $userId);

        if (!$result['success']) {
            AuditLog::record('payment.verification_failed', 'order', null, [
                'gateway_order_id' => $gatewayOrderId,
                'message'          => $result['message'],
            ]);

            if ($this->request->wantsJson()) {
                return $this->fail($result['message'], 402);
            }
            $this->error($result['message']);

            return $this->redirect('billing');
        }

        $orderNumber = (string) ($result['order']['order_number'] ?? '');

        if ($this->request->wantsJson()) {
            return $this->ok($result['message'], ['redirect' => Url::to('billing/success/' . $orderNumber)]);
        }

        return $this->redirect('billing/success/' . $orderNumber);
    }

    public function thankYou(string $order): Response
    {
        $orders = new Order();
        $row = $orders->findByNumber($order);

        if ($row === null || (int) $row['user_id'] !== $this->userId()) {
            $this->error('We could not find that order.');

            return $this->redirect('billing');
        }

        return $this->render('customer.billing.success', [
            'title'   => 'Payment successful',
            'order'   => $row,
            'plan'    => (new Plan())->find((int) $row['plan_id']),
            'invoice' => (new Invoice())->findByOrder((int) $row['id']),
            'summary' => (new SubscriptionService())->summaryFor($this->userId()),
        ]);
    }

    public function invoice(string $id): Response
    {
        $invoice = (new Invoice())->findForUser((int) $id, $this->userId());

        if ($invoice === null) {
            $this->error('That invoice was not found.');

            return $this->redirect('billing');
        }

        return $this->render('customer.billing.invoice', [
            'title'   => 'Invoice ' . (string) $invoice['invoice_number'],
            'invoice' => $invoice,
            'order'   => (new Order())->find((int) $invoice['order_id']),
            'seller'  => [
                'name'    => (string) (Settings::get('company_legal_name') ?: Settings::get('site_name')),
                'address' => (string) (Settings::get('company_address') ?? ''),
                'gstin'   => (string) (Settings::get('gst_number') ?? ''),
                'email'   => (string) (Settings::get('support_email') ?? ''),
                'phone'   => (string) (Settings::get('support_phone') ?? ''),
            ],
        ]);
    }
}
