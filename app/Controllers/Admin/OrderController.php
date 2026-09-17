<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Response;
use App\Core\Settings;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\RazorpayService;
use App\Services\SubscriptionService;
use Throwable;

final class OrderController extends AdminController
{
    public function index(): Response
    {
        $filters = ['search' => $this->request->string('q'), 'status' => $this->request->string('status')];

        return $this->render('admin.orders.index', [
            'title'   => 'Orders',
            'result'  => (new Order())->search($filters, $this->page(), 25),
            'filters' => $filters,
            'stats'   => (new Order())->revenueStatistics(),
        ]);
    }

    public function show(string $id): Response
    {
        $order = (new Order())->find((int) $id);
        if ($order === null) {
            $this->error('Order not found.');

            return $this->redirect('admin/orders');
        }

        return $this->render('admin.orders.show', [
            'title'    => 'Order ' . (string) $order['order_number'],
            'order'    => $order,
            'user'     => (new User())->find((int) $order['user_id']),
            'plan'     => (new Plan())->find((int) $order['plan_id']),
            'payments' => (new Payment())->forOrder((int) $order['id']),
            'invoice'  => (new Invoice())->findByOrder((int) $order['id']),
        ]);
    }

    public function refund(string $id): Response
    {
        $orders = new Order();
        $order = $orders->find((int) $id);
        if ($order === null) {
            return $this->redirect('admin/orders');
        }
        if ((string) $order['status'] !== 'paid') {
            $this->error('Only a paid order can be refunded.');

            return $this->redirect('admin/orders/' . (int) $order['id']);
        }

        $payments = (new Payment())->forOrder((int) $order['id']);
        $captured = null;
        foreach ($payments as $payment) {
            if (in_array((string) $payment['status'], ['captured', 'authorized'], true)) {
                $captured = $payment;
                break;
            }
        }

        if ($captured === null) {
            $this->error('No captured payment was found for this order.');

            return $this->redirect('admin/orders/' . (int) $order['id']);
        }

        try {
            (new RazorpayService())->refund(
                (string) $captured['gateway_payment_id'],
                (float) $order['total'],
                $this->request->string('reason')
            );

            $orders->updateById((int) $order['id'], ['status' => 'refunded']);
            (new Payment())->updateById((int) $captured['id'], ['status' => 'refunded']);

            if (!empty($order['subscription_id'])) {
                (new SubscriptionService())->cancel((int) $order['subscription_id'], 'Refunded by administrator');
            }

            AuditLog::record('admin.order_refunded', 'order', (int) $order['id'], [
                'payment_id' => $captured['gateway_payment_id'],
                'amount'     => $order['total'],
            ]);

            $this->success('Refund requested with the payment gateway and the subscription was cancelled.');
        } catch (Throwable $e) {
            $this->error('Refund failed: ' . $e->getMessage());
        }

        return $this->redirect('admin/orders/' . (int) $order['id']);
    }

    public function payments(): Response
    {
        $filters = ['search' => $this->request->string('q'), 'status' => $this->request->string('status')];

        return $this->render('admin.orders.payments', [
            'title'   => 'Payments',
            'result'  => (new Payment())->search($filters, $this->page(), 25),
            'filters' => $filters,
        ]);
    }

    public function invoices(): Response
    {
        $filters = ['search' => $this->request->string('q'), 'status' => $this->request->string('status')];

        return $this->render('admin.orders.invoices', [
            'title'   => 'Invoices',
            'result'  => (new Invoice())->search($filters, $this->page(), 25),
            'filters' => $filters,
        ]);
    }

    public function invoice(string $id): Response
    {
        $invoice = (new Invoice())->find((int) $id);
        if ($invoice === null) {
            $this->error('Invoice not found.');

            return $this->redirect('admin/invoices');
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
            'navGroups' => [],
            'mobileNav' => [],
        ]);
    }
}
