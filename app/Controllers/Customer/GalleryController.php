<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Response;
use App\Core\Uploader;
use App\Models\CardGallery;
use App\Services\CardPresenter;
use App\Services\PlanLimiter;
use Throwable;

final class GalleryController extends PanelController
{
    public function index(string $id): Response
    {
        $card = $this->ownedCard($id);
        $gallery = new CardGallery();

        return $this->render('customer.gallery', [
            'title'       => 'Gallery: ' . (string) $card['title'],
            'card'        => $card,
            'images'      => $gallery->forCard((int) $card['id'], 'image'),
            'videos'      => array_merge($gallery->forCard((int) $card['id'], 'video'), $gallery->forCard((int) $card['id'], 'youtube')),
            'imageLimit'  => PlanLimiter::canAddGalleryItem($this->userId(), (int) $card['id'], 'image'),
            'videoLimit'  => PlanLimiter::canAddGalleryItem($this->userId(), (int) $card['id'], 'video'),
            'storage'     => PlanLimiter::usage($this->userId())['storage'],
        ]);
    }

    public function store(string $id): Response
    {
        $card = $this->ownedCard($id);
        $gallery = new CardGallery();
        $userId = $this->userId();

        $files = $_FILES['images'] ?? null;
        if (!is_array($files) || !isset($files['name'])) {
            $this->error('Please choose at least one image.');

            return $this->redirect('cards/' . (int) $card['id'] . '/gallery');
        }

        $names = (array) $files['name'];
        $added = 0;
        $errors = [];

        foreach (array_keys($names) as $index) {
            $limit = PlanLimiter::canAddGalleryItem($userId, (int) $card['id'], 'image');
            if (!$limit['allowed']) {
                $errors[] = $limit['message'];
                break;
            }

            $file = [
                'name'     => $files['name'][$index],
                'type'     => $files['type'][$index],
                'tmp_name' => $files['tmp_name'][$index],
                'error'    => $files['error'][$index],
                'size'     => $files['size'][$index],
            ];
            if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $storage = PlanLimiter::canUseStorage($userId, (int) $file['size']);
            if (!$storage['allowed']) {
                $errors[] = $storage['message'];
                break;
            }

            try {
                $path = (new Uploader())->image($file, 'gallery', 1600);
                $dimensions = @getimagesize(UPLOAD_PATH . '/' . $path);

                $gallery->create([
                    'card_id'    => (int) $card['id'],
                    'type'       => 'image',
                    'path'       => $path,
                    'title'      => mb_substr((string) $file['name'], 0, 190),
                    'width'      => $dimensions !== false ? (int) $dimensions[0] : null,
                    'height'     => $dimensions !== false ? (int) $dimensions[1] : null,
                    'filesize'   => (int) @filesize(UPLOAD_PATH . '/' . $path),
                    'sort_order' => $gallery->nextSortOrder((int) $card['id']),
                    'created_at' => now(),
                ]);
                $added++;
            } catch (Throwable $e) {
                $errors[] = (string) $file['name'] . ': ' . $e->getMessage();
            }
        }

        if ($added > 0) {
            $this->success($added . ' image(s) added to the gallery.');
        }
        foreach (array_slice($errors, 0, 3) as $error) {
            $this->error($error);
        }

        return $this->redirect('cards/' . (int) $card['id'] . '/gallery');
    }

    public function storeVideo(string $id): Response
    {
        $card = $this->ownedCard($id);

        $limit = PlanLimiter::canAddGalleryItem($this->userId(), (int) $card['id'], 'video');
        if (!$limit['allowed']) {
            $this->error($limit['message']);

            return $this->redirect('cards/' . (int) $card['id'] . '/gallery');
        }

        $data = $this->validate([
            'url'   => 'required|url|max:500',
            'title' => 'nullable|string|max:190',
        ]);

        $embed = CardPresenter::youtubeEmbed((string) $data['url']);
        if ($embed === null) {
            $this->error('Please paste a valid YouTube link (youtube.com/watch?v=… or youtu.be/…).');

            return $this->redirect('cards/' . (int) $card['id'] . '/gallery');
        }

        $gallery = new CardGallery();
        $gallery->create([
            'card_id'    => (int) $card['id'],
            'type'       => 'youtube',
            'path'       => (string) $data['url'],
            'thumbnail'  => CardPresenter::youtubeThumb((string) $data['url']),
            'title'      => $data['title'] ?? 'Video',
            'sort_order' => $gallery->nextSortOrder((int) $card['id']),
            'created_at' => now(),
        ]);

        $this->success('Video added.');

        return $this->redirect('cards/' . (int) $card['id'] . '/gallery');
    }

    public function destroy(string $id, string $itemId): Response
    {
        $card = $this->ownedCard($id);
        $gallery = new CardGallery();

        $item = $gallery->findForCard((int) $itemId, (int) $card['id']);
        if ($item !== null) {
            if ((string) $item['type'] === 'image' && !empty($item['path'])) {
                Uploader::delete((string) $item['path']);
            }
            $gallery->deleteById((int) $item['id']);
            $this->success('Item removed from the gallery.');
        }

        return $this->redirect('cards/' . (int) $card['id'] . '/gallery');
    }
}
