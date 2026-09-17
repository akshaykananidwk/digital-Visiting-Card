<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Response;
use App\Core\Uploader;
use App\Models\CardService as CardServiceModel;
use App\Services\PlanLimiter;
use Throwable;

final class ServiceController extends PanelController
{
    public function index(string $id): Response
    {
        $card = $this->ownedCard($id);

        return $this->render('customer.services', [
            'title'    => 'Services: ' . (string) $card['title'],
            'card'     => $card,
            'services' => (new CardServiceModel())->forCard((int) $card['id']),
            'limit'    => PlanLimiter::canAddService($this->userId(), (int) $card['id']),
        ]);
    }

    public function store(string $id): Response
    {
        $card = $this->ownedCard($id);

        $limit = PlanLimiter::canAddService($this->userId(), (int) $card['id']);
        if (!$limit['allowed']) {
            $this->error($limit['message']);

            return $this->redirect('cards/' . (int) $card['id'] . '/services');
        }

        $data = $this->validate([
            'title'       => 'required|string|min:2|max:150',
            'description' => 'nullable|string|max:1000',
            'icon'        => 'nullable|string|max:60',
            'price'       => 'nullable|numeric|min:0|max:99999999',
            'price_label' => 'nullable|string|max:60',
            'cta_label'   => 'nullable|string|max:60',
            'cta_link'    => 'nullable|url|max:500',
        ]);

        $services = new CardServiceModel();
        $data['card_id'] = (int) $card['id'];
        $data['is_active'] = 1;
        $data['sort_order'] = $services->nextSortOrder((int) $card['id']);
        $data['created_at'] = now();
        $data['image'] = $this->uploadImage();

        $services->create($data);
        $this->success('Service added.');

        return $this->redirect('cards/' . (int) $card['id'] . '/services');
    }

    public function update(string $id, string $serviceId): Response
    {
        $card = $this->ownedCard($id);
        $services = new CardServiceModel();

        $service = $services->findForCard((int) $serviceId, (int) $card['id']);
        if ($service === null) {
            $this->error('That service was not found.');

            return $this->redirect('cards/' . (int) $card['id'] . '/services');
        }

        $data = $this->validate([
            'title'       => 'required|string|min:2|max:150',
            'description' => 'nullable|string|max:1000',
            'icon'        => 'nullable|string|max:60',
            'price'       => 'nullable|numeric|min:0|max:99999999',
            'price_label' => 'nullable|string|max:60',
            'cta_label'   => 'nullable|string|max:60',
            'cta_link'    => 'nullable|url|max:500',
        ]);

        $data['is_active'] = $this->request->bool('is_active', true) ? 1 : 0;

        $image = $this->uploadImage();
        if ($image !== null) {
            if (!empty($service['image'])) {
                Uploader::delete((string) $service['image']);
            }
            $data['image'] = $image;
        }

        $services->updateById((int) $service['id'], $data);
        $this->success('Service updated.');

        return $this->redirect('cards/' . (int) $card['id'] . '/services');
    }

    public function destroy(string $id, string $serviceId): Response
    {
        $card = $this->ownedCard($id);
        $services = new CardServiceModel();

        $service = $services->findForCard((int) $serviceId, (int) $card['id']);
        if ($service !== null) {
            if (!empty($service['image'])) {
                Uploader::delete((string) $service['image']);
            }
            $services->deleteById((int) $service['id']);
            $this->success('Service removed.');
        }

        return $this->redirect('cards/' . (int) $card['id'] . '/services');
    }

    public function reorder(string $id): Response
    {
        $card = $this->ownedCard($id);
        $order = array_map('intval', $this->request->array('order'));

        if ($order !== []) {
            (new CardServiceModel())->reorder((int) $card['id'], $order);
        }

        return $this->ok('Order saved.');
    }

    private function uploadImage(): ?string
    {
        $file = $this->request->file('image');
        if ($file === null) {
            return null;
        }

        $storage = PlanLimiter::canUseStorage($this->userId(), (int) ($file['size'] ?? 0));
        if (!$storage['allowed']) {
            $this->error($storage['message']);

            return null;
        }

        try {
            return (new Uploader())->image($file, 'cards', 800);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return null;
        }
    }
}
