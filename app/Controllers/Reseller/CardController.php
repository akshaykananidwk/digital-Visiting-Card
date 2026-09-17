<?php

declare(strict_types=1);

namespace App\Controllers\Reseller;

use App\Core\Response;
use App\Models\Card;
use App\Models\User;

final class CardController extends ResellerPanelController
{
    public function index(): Response
    {
        $filters = [
            'search'      => $this->request->string('q'),
            'status'      => $this->request->string('status'),
            'reseller_id' => $this->resellerId(),
        ];

        $result = (new Card())->search($filters, $this->page(), 25);

        // Attach owner names without an N+1 query.
        $userIds = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['user_id'], $result['data'])));
        $owners = [];
        if ($userIds !== []) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            foreach ($this->db()->select(
                'SELECT `id`, `name`, `email` FROM `' . $this->db()->table('users') . '` WHERE `id` IN (' . $placeholders . ')',
                $userIds
            ) as $owner) {
                $owners[(int) $owner['id']] = $owner;
            }
        }

        return $this->render('reseller.cards', [
            'title'   => 'Customer cards',
            'result'  => $result,
            'filters' => $filters,
            'owners'  => $owners,
        ]);
    }
}
