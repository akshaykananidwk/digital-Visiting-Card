<?php

declare(strict_types=1);

/**
 * A spread of design codes covering every layout, with and without the
 * entrance animation, so the rendering suite cannot miss the combination
 * that renders blank.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$picked = [];
$seen = [];

foreach (Database::instance()->query('SELECT code, layout, config FROM templates ORDER BY id')->fetchAll() as $row) {
    $config = json_decode((string) $row['config'], true) ?: [];
    $reveal = in_array('reveal', $config['effects'] ?? [], true);
    $key = (string) $row['layout'] . ($reveal ? ':reveal' : '');
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $picked[] = $row['code'];
    }
}

echo json_encode(array_slice($picked, 0, 30)), "\n";
