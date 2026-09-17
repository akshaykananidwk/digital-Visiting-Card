<?php

declare(strict_types=1);

namespace App\Controllers\Reseller;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Models\Reseller;

/** Shared behaviour for the reseller panel. */
abstract class ResellerPanelController extends Controller
{
    /** @var array<string,mixed>|null */
    private ?array $reseller = null;

    /** @return array<string,mixed> */
    protected function reseller(): array
    {
        if ($this->reseller !== null) {
            return $this->reseller;
        }

        $userId = Auth::instance()->id();
        if ($userId === null) {
            throw HttpException::unauthorized();
        }

        $reseller = (new Reseller())->findByUserId($userId);
        if ($reseller === null) {
            throw HttpException::forbidden('Your account is not linked to a reseller profile. Please contact support.');
        }
        if ((int) $reseller['is_active'] !== 1) {
            throw HttpException::forbidden('Your reseller account is currently inactive.');
        }

        return $this->reseller = $reseller;
    }

    protected function resellerId(): int
    {
        return (int) $this->reseller()['id'];
    }

    /**
     * Ownership-checked customer lookup — a reseller may only ever touch
     * accounts that belong to them.
     *
     * @return array<string,mixed>
     */
    protected function ownedCustomer(int|string $userId): array
    {
        $customer = (new \App\Models\User())->find((int) $userId);

        if ($customer === null || (int) ($customer['reseller_id'] ?? 0) !== $this->resellerId()) {
            throw HttpException::notFound('That customer does not belong to your account.');
        }
        if ((string) $customer['role'] !== Auth::ROLE_CUSTOMER) {
            throw HttpException::forbidden('You can only manage customer accounts.');
        }

        return $customer;
    }

    /** @param array<string,mixed> $data */
    protected function render(string $view, array $data = [], int $status = 200): Response
    {
        return parent::render($view, $data + $this->panelData(), $status);
    }

    /** @return array<string,mixed> */
    protected function panelData(): array
    {
        $reseller = $this->reseller();

        return [
            'reseller'  => $reseller,
            'navGroups' => [
                '' => [
                    ['href' => url('reseller'), 'label' => 'Dashboard', 'icon' => 'grid'],
                    ['href' => url('reseller/customers'), 'label' => 'Customers', 'icon' => 'users'],
                    ['href' => url('reseller/cards'), 'label' => 'Cards', 'icon' => 'layout'],
                ],
                'Business' => [
                    ['href' => url('reseller/wallet'), 'label' => 'Wallet', 'icon' => 'wallet'],
                    ['href' => url('reseller/orders'), 'label' => 'Sales', 'icon' => 'package'],
                    ['href' => url('reseller/branding'), 'label' => 'Branding', 'icon' => 'layout'],
                ],
                'My account' => [
                    ['href' => url('dashboard'), 'label' => 'My own cards', 'icon' => 'layers'],
                    ['href' => url('account'), 'label' => 'Account', 'icon' => 'settings'],
                ],
            ],
            'mobileNav' => [
                ['href' => url('reseller'), 'label' => 'Home', 'icon' => 'home'],
                ['href' => url('reseller/customers'), 'label' => 'Customers', 'icon' => 'users'],
                ['href' => url('reseller/cards'), 'label' => 'Cards', 'icon' => 'layout'],
                ['href' => url('reseller/wallet'), 'label' => 'Wallet', 'icon' => 'wallet'],
                ['href' => url('account'), 'label' => 'Account', 'icon' => 'settings'],
            ],
        ];
    }

    protected function page(): int
    {
        return max(1, $this->request->int('page', 1));
    }
}
