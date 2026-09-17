<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Response;
use App\Core\Uploader;
use App\Models\CardProduct;
use App\Services\PlanLimiter;
use Throwable;

final class ProductController extends PanelController
{
    public function index(string $id): Response
    {
        $card = $this->ownedCard($id);

        return $this->render('customer.products', [
            'title'    => 'Products: ' . (string) $card['title'],
            'card'     => $card,
            'products' => (new CardProduct())->forCard((int) $card['id']),
            'limit'    => PlanLimiter::canAddProduct($this->userId(), (int) $card['id']),
        ]);
    }

    public function store(string $id): Response
    {
        $card = $this->ownedCard($id);

        $limit = PlanLimiter::canAddProduct($this->userId(), (int) $card['id']);
        if (!$limit['allowed']) {
            $this->error($limit['message']);

            return $this->redirect('cards/' . (int) $card['id'] . '/products');
        }

        $data = $this->productRules();
        $products = new CardProduct();

        $data['card_id'] = (int) $card['id'];
        $data['sort_order'] = $products->nextSortOrder((int) $card['id']);
        $data['is_active'] = 1;
        $data['created_at'] = now();
        $data['image'] = $this->uploadImage();

        $products->create($data);
        $this->success('Product added.');

        return $this->redirect('cards/' . (int) $card['id'] . '/products');
    }

    public function update(string $id, string $productId): Response
    {
        $card = $this->ownedCard($id);
        $products = new CardProduct();

        $product = $products->findForCard((int) $productId, (int) $card['id']);
        if ($product === null) {
            $this->error('That product was not found.');

            return $this->redirect('cards/' . (int) $card['id'] . '/products');
        }

        $data = $this->productRules();
        $data['is_active'] = $this->request->bool('is_active', true) ? 1 : 0;
        $data['is_featured'] = $this->request->bool('is_featured') ? 1 : 0;

        $image = $this->uploadImage();
        if ($image !== null) {
            if (!empty($product['image'])) {
                Uploader::delete((string) $product['image']);
            }
            $data['image'] = $image;
        }

        $products->updateById((int) $product['id'], $data);
        $this->success('Product updated.');

        return $this->redirect('cards/' . (int) $card['id'] . '/products');
    }

    public function destroy(string $id, string $productId): Response
    {
        $card = $this->ownedCard($id);
        $products = new CardProduct();

        $product = $products->findForCard((int) $productId, (int) $card['id']);
        if ($product !== null) {
            if (!empty($product['image'])) {
                Uploader::delete((string) $product['image']);
            }
            $products->deleteById((int) $product['id']);
            $this->success('Product removed.');
        }

        return $this->redirect('cards/' . (int) $card['id'] . '/products');
    }

    public function reorder(string $id): Response
    {
        $card = $this->ownedCard($id);
        $order = array_map('intval', $this->request->array('order'));

        if ($order !== []) {
            (new CardProduct())->reorder((int) $card['id'], $order);
        }

        return $this->ok('Order saved.');
    }

    /** @return array<string,mixed> */
    private function productRules(): array
    {
        $data = $this->validate([
            'name'           => 'required|string|min:2|max:190',
            'description'    => 'nullable|string|max:1000',
            'price'          => 'nullable|numeric|min:0|max:99999999',
            'discount_price' => 'nullable|numeric|min:0|max:99999999',
            'sku'            => 'nullable|string|max:60',
            'category'       => 'nullable|string|max:100',
            'stock_status'   => 'nullable|in:in_stock,out_of_stock,made_to_order',
            'cta_type'       => 'nullable|in:whatsapp,call,link,none',
            'cta_link'       => 'nullable|url|max:500',
        ]);

        $data['stock_status'] = $data['stock_status'] ?? 'in_stock';
        $data['cta_type'] = $data['cta_type'] ?? 'whatsapp';

        if (isset($data['discount_price'], $data['price'])
            && $data['discount_price'] !== null && $data['price'] !== null
            && (float) $data['discount_price'] >= (float) $data['price']) {
            $data['discount_price'] = null;          // a "discount" above list price is meaningless
        }

        return $data;
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
            return (new Uploader())->image($file, 'products', 1000);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return null;
        }
    }
}
