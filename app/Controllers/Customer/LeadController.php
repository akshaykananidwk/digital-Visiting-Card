<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\AuditLog;
use App\Core\Response;
use App\Models\Card;
use App\Models\Lead;

final class LeadController extends PanelController
{
    public function index(): Response
    {
        $userId = $this->userId();
        $filters = [
            'search'  => $this->request->string('q'),
            'status'  => $this->request->string('status'),
            'card_id' => $this->request->int('card_id'),
        ];

        return $this->render('customer.leads.index', [
            'title'   => 'Leads',
            'result'  => (new Lead())->forUser($userId, $filters, max(1, $this->request->int('page', 1)), 20),
            'filters' => $filters,
            'cards'   => (new Card())->forUser($userId),
            'unread'  => (new Lead())->unreadCount($userId),
        ]);
    }

    public function show(string $id): Response
    {
        $leads = new Lead();
        $lead = $leads->findForUser((int) $id, $this->userId());

        if ($lead === null) {
            $this->error('That lead was not found.');

            return $this->redirect('leads');
        }

        if ((string) $lead['status'] === 'new') {
            $leads->markRead((int) $lead['id']);
            $lead['status'] = 'read';
        }

        return $this->render('customer.leads.show', [
            'title' => 'Lead from ' . (string) $lead['name'],
            'lead'  => $lead,
            'card'  => (new Card())->find((int) $lead['card_id']),
        ]);
    }

    public function updateStatus(string $id): Response
    {
        $leads = new Lead();
        $lead = $leads->findForUser((int) $id, $this->userId());

        if ($lead === null) {
            return $this->redirect('leads');
        }

        $status = $this->request->string('status');
        if (!in_array($status, ['new', 'read', 'contacted', 'converted', 'spam', 'archived'], true)) {
            $this->error('Unknown status.');

            return $this->redirect('leads/' . (int) $lead['id']);
        }

        $leads->updateById((int) $lead['id'], [
            'status' => $status,
            'notes'  => mb_substr($this->request->string('notes'), 0, 2000),
        ]);

        $this->success('Lead updated.');

        return $this->redirect('leads/' . (int) $lead['id']);
    }

    public function destroy(string $id): Response
    {
        $leads = new Lead();
        $lead = $leads->findForUser((int) $id, $this->userId());

        if ($lead !== null) {
            $leads->deleteById((int) $lead['id']);
            AuditLog::record('lead.deleted', 'lead', (int) $lead['id']);
            $this->success('Lead deleted.');
        }

        return $this->redirect('leads');
    }

    public function export(): Response
    {
        $userId = $this->userId();
        $result = (new Lead())->forUser($userId, [
            'status'  => $this->request->string('status'),
            'card_id' => $this->request->int('card_id'),
        ], 1, 5000);

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            $this->error('Could not build the export.');

            return $this->redirect('leads');
        }

        fputcsv($handle, ['Date', 'Name', 'Phone', 'Email', 'Subject', 'Message', 'Status', 'Card'], ',', '"', '\\');

        $cards = [];
        foreach ((new Card())->forUser($userId) as $card) {
            $cards[(int) $card['id']] = (string) $card['title'];
        }

        foreach ($result['data'] as $lead) {
            fputcsv($handle, [
                (string) $lead['created_at'],
                (string) $lead['name'],
                (string) ($lead['phone'] ?? ''),
                (string) ($lead['email'] ?? ''),
                (string) ($lead['subject'] ?? ''),
                str_replace(["\r", "\n"], ' ', (string) ($lead['message'] ?? '')),
                (string) $lead['status'],
                $cards[(int) $lead['card_id']] ?? '',
            ], ',', '"', '\\');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        AuditLog::record('lead.exported', 'lead', null, ['count' => count($result['data'])]);

        return Response::download("\xEF\xBB\xBF" . $csv, 'leads-' . date('Y-m-d') . '.csv', 'text/csv; charset=utf-8');
    }
}
