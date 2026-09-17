<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AuditLog;
use App\Core\Database;
use App\Models\Reseller;
use App\Models\WalletTransaction;
use RuntimeException;

/**
 * Reseller wallet. All balance changes go through a locked transaction so
 * concurrent sales can never produce a negative balance or a lost update.
 */
final class WalletService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::instance();
    }

    /**
     * Add funds to a reseller wallet.
     *
     * @return array<string,mixed> The created wallet transaction
     */
    public function credit(int $resellerId, float $amount, string $description = '', ?int $actorId = null, string $referenceType = 'manual', ?int $referenceId = null): array
    {
        if ($amount <= 0) {
            throw new RuntimeException('Credit amount must be greater than zero.');
        }

        return $this->db->transaction(function () use ($resellerId, $amount, $description, $actorId, $referenceType, $referenceId): array {
            $reseller = $this->lock($resellerId);
            $before = (float) $reseller['wallet_balance'];
            $after = round($before + $amount, 2);

            (new Reseller())->updateById($resellerId, ['wallet_balance' => $after]);

            $transactions = new WalletTransaction();
            $id = $transactions->create([
                'reseller_id'    => $resellerId,
                'type'           => 'credit',
                'amount'         => round($amount, 2),
                'balance_before' => $before,
                'balance_after'  => $after,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'description'    => substr($description, 0, 250),
                'created_by'     => $actorId,
                'created_at'     => now(),
            ]);

            AuditLog::record('wallet.credit', 'reseller', $resellerId, [
                'amount' => $amount, 'balance_after' => $after, 'description' => $description,
            ], $actorId);

            return (array) $transactions->find($id);
        });
    }

    /**
     * Deduct funds. Throws when the balance (plus any approved credit limit)
     * is insufficient — a negative balance is never written.
     *
     * @return array<string,mixed>
     */
    public function debit(int $resellerId, float $amount, string $description = '', ?int $actorId = null, string $referenceType = 'sale', ?int $referenceId = null): array
    {
        if ($amount <= 0) {
            throw new RuntimeException('Debit amount must be greater than zero.');
        }

        return $this->db->transaction(function () use ($resellerId, $amount, $description, $actorId, $referenceType, $referenceId): array {
            $reseller = $this->lock($resellerId);
            $before = (float) $reseller['wallet_balance'];
            $creditLimit = (float) $reseller['credit_limit'];
            $after = round($before - $amount, 2);

            if ($after < -$creditLimit + 0.0001 && $after < 0) {
                throw new RuntimeException(sprintf(
                    'Insufficient wallet balance. Available: %s, required: %s.',
                    money($before + $creditLimit),
                    money($amount)
                ));
            }

            (new Reseller())->updateById($resellerId, ['wallet_balance' => $after]);

            $transactions = new WalletTransaction();
            $id = $transactions->create([
                'reseller_id'    => $resellerId,
                'type'           => 'debit',
                'amount'         => round($amount, 2),
                'balance_before' => $before,
                'balance_after'  => $after,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'description'    => substr($description, 0, 250),
                'created_by'     => $actorId,
                'created_at'     => now(),
            ]);

            AuditLog::record('wallet.debit', 'reseller', $resellerId, [
                'amount' => $amount, 'balance_after' => $after, 'description' => $description,
            ], $actorId);

            return (array) $transactions->find($id);
        });
    }

    public function balance(int $resellerId): float
    {
        $reseller = (new Reseller())->find($resellerId);

        return $reseller === null ? 0.0 : (float) $reseller['wallet_balance'];
    }

    public function canAfford(int $resellerId, float $amount): bool
    {
        $reseller = (new Reseller())->find($resellerId);
        if ($reseller === null) {
            return false;
        }

        return ((float) $reseller['wallet_balance'] + (float) $reseller['credit_limit']) >= $amount;
    }

    /**
     * Price a plan for a reseller (their discounted price when configured).
     *
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $reseller
     */
    public function resellerPrice(array $plan, array $reseller): float
    {
        $resellerPrice = $plan['reseller_price'] ?? null;
        if ($resellerPrice !== null && (float) $resellerPrice > 0) {
            return round((float) $resellerPrice, 2);
        }

        $commission = (float) ($reseller['commission_rate'] ?? 0);

        return round((float) $plan['price'] * (1 - $commission / 100), 2);
    }

    /** @return array<string,mixed> Row locked FOR UPDATE inside the caller's transaction. */
    private function lock(int $resellerId): array
    {
        $row = $this->db->selectOne(
            'SELECT * FROM `' . $this->db->table('resellers') . '` WHERE `id` = :id AND `deleted_at` IS NULL FOR UPDATE',
            ['id' => $resellerId]
        );
        if ($row === null) {
            throw new RuntimeException('Reseller account not found.');
        }
        if ((int) $row['is_active'] !== 1) {
            throw new RuntimeException('This reseller account is inactive.');
        }

        return $row;
    }
}
