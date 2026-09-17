<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AuditLog;
use App\Core\Response;
use App\Models\Card;

final class CardController extends AdminController
{
    public function index(): Response
    {
        $filters = [
            'search' => $this->request->string('q'),
            'status' => $this->request->string('status'),
        ];

        return $this->render('admin.cards', [
            'title'   => 'Cards',
            'result'  => (new Card())->searchWithOwners($filters, $this->page(), 25),
            'filters' => $filters,
            'stats'   => (new Card())->statistics(),
        ]);
    }

    public function updateStatus(string $id): Response
    {
        $cards = new Card();
        $card = $cards->find((int) $id);
        if ($card === null) {
            $this->error('Card not found.');

            return $this->redirect('admin/cards');
        }

        $status = $this->request->string('status');
        if (!in_array($status, ['draft', 'published', 'suspended', 'expired'], true)) {
            $this->error('Unknown status.');

            return $this->redirect('admin/cards');
        }

        $payload = ['status' => $status];
        if ($status === 'published' && empty($card['published_at'])) {
            $payload['published_at'] = now();
        }

        $cards->updateById((int) $card['id'], $payload);
        AuditLog::record('admin.card_status_changed', 'card', (int) $card['id'], [
            'slug' => $card['slug'], 'status' => $status,
        ]);

        $this->success('Card status changed to ' . $status . '.');

        return $this->back('admin/cards');
    }

    public function destroy(string $id): Response
    {
        $cards = new Card();
        $card = $cards->find((int) $id);
        if ($card === null) {
            return $this->redirect('admin/cards');
        }

        $cards->deleteById((int) $card['id']);
        AuditLog::record('admin.card_deleted', 'card', (int) $card['id'], ['slug' => $card['slug']]);

        $this->success('Card deleted.');

        return $this->redirect('admin/cards');
    }
}
