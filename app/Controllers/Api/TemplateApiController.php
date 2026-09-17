<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Template;

final class TemplateApiController extends Controller
{
    public function index(): Response
    {
        $result = (new Template())->browse([
            'search'   => $this->request->string('q'),
            'category' => $this->request->int('category'),
            'premium'  => $this->request->string('premium'),
            'style'    => $this->request->string('style'),
            'mode'     => $this->request->string('mode'),
            'sort'     => $this->request->string('sort', 'featured'),
        ], max(1, $this->request->int('page', 1)), max(1, min(48, $this->request->int('per_page', 24))));

        return $this->json([
            'success'  => true,
            'total'    => $result['total'],
            'page'     => $result['page'],
            'pages'    => $result['pages'],
            'data'     => array_map(static fn (array $row): array => [
                'id'      => (int) $row['id'],
                'code'    => $row['code'],
                'name'    => $row['name'],
                'layout'  => $row['layout'],
                'style'   => $row['style'],
                'mode'    => $row['theme_mode'],
                'colors'  => $row['config']['palette'] ?? [],
                'premium' => (bool) $row['is_premium'],
                'preview' => url('templates/preview/' . $row['code']),
            ], $result['data']),
        ]);
    }
}
