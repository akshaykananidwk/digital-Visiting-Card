<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Models\Card;
use App\Models\Lead;

final class LeadController extends AdminController
{
    public function index(): Response
    {
        $filters = ['search' => $this->request->string('q'), 'status' => $this->request->string('status')];
        $result = (new Lead())->searchAll($filters, $this->page(), 30);

        // Attach the card each lead came from.
        $cardIds = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['card_id'], $result['data'])));
        $cards = [];
        if ($cardIds !== []) {
            $placeholders = implode(',', array_fill(0, count($cardIds), '?'));
            foreach ($this->db()->select(
                'SELECT `id`, `title`, `slug` FROM `' . $this->db()->table('cards') . '` WHERE `id` IN (' . $placeholders . ')',
                $cardIds
            ) as $card) {
                $cards[(int) $card['id']] = $card;
            }
        }

        return $this->render('admin.leads', [
            'title'   => 'Leads',
            'result'  => $result,
            'filters' => $filters,
            'cards'   => $cards,
            'total'   => (new Lead())->count([]),
        ]);
    }
}
