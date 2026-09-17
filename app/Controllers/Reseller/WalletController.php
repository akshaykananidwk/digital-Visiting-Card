<?php

declare(strict_types=1);

namespace App\Controllers\Reseller;

use App\Core\Response;
use App\Core\Settings;
use App\Models\WalletTransaction;

final class WalletController extends ResellerPanelController
{
    public function index(): Response
    {
        $reseller = $this->reseller();
        $transactions = new WalletTransaction();

        return $this->render('reseller.wallet', [
            'title'        => 'Wallet',
            'balance'      => (float) $reseller['wallet_balance'],
            'creditLimit'  => (float) $reseller['credit_limit'],
            'totals'       => $transactions->totals($this->resellerId()),
            'result'       => $transactions->forReseller($this->resellerId(), $this->page(), 30),
            'supportEmail' => (string) (Settings::get('support_email') ?? ''),
            'supportPhone' => (string) (Settings::get('support_phone') ?? ''),
        ]);
    }

    public function orders(): Response
    {
        $reseller = $this->reseller();

        return $this->render('reseller.orders', [
            'title'  => 'Sales',
            'result' => (new WalletTransaction())->paginate([
                'reseller_id'    => $this->resellerId(),
                'reference_type' => 'plan_sale',
            ], $this->page(), 30, 'created_at DESC'),
            'totalSales' => (float) $reseller['total_sales'],
            'commission' => (float) $reseller['commission_rate'],
        ]);
    }
}
