<?php

declare(strict_types=1);

/**
 * QR encoder verification.
 *
 * Every payload is encoded at all four error-correction levels and then read
 * back with zbarimg, so the check is "a real scanner recovers exactly what we
 * encoded", not "the encoder did not throw". Install zbar-tools to run the
 * decode half; without it the suite still verifies encoding and SVG output.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\QrCode;

$pass = 0;
$fail = 0;
$ok = static function (string $m) use (&$pass): void {
    echo "  PASS  {$m}\n";
    $pass++;
};
$no = static function (string $m) use (&$fail): void {
    echo "  FAIL  {$m}\n";
    $fail++;
};

$decoder = trim((string) shell_exec('command -v zbarimg 2>/dev/null'));
if ($decoder === '') {
    echo "  NOTE  zbarimg not installed -- decode checks skipped\n";
}

$out = sys_get_temp_dir() . '/dvc-qr-' . bin2hex(random_bytes(4));
mkdir($out, 0700, true);

$cases = [
    'short'          => 'A',
    'card url'       => 'https://example.com/card/akshay',
    'custom domain'  => 'https://cards.example.co.in/ak-computer-cctv-dwarka',
    'query string'   => 'https://example.com/c/1?utm=whatsapp&ref=qr',
    'hundred chars'  => str_repeat('x', 100),
    // The worst case the routing rules actually permit: a reseller's own
    // domain plus the longest slug the card route accepts, plus the QR
    // attribution parameter.
    'longest card url' => 'https://cards.a-long-reseller-domain.co.in/'
        . 'a' . str_repeat('b', 98) . '?src=qr',
];

foreach ($cases as $label => $payload) {
    foreach (['L', 'M', 'Q', 'H'] as $level) {
        $name = "{$label} [{$level}]";
        try {
            $qr = new QrCode($payload, $level);
            $png = $qr->png(8, 4);
        } catch (Throwable $e) {
            $no("{$name}: encoding threw -- " . $e->getMessage());
            continue;
        }

        if (substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            $no("{$name}: output is not a PNG");
            continue;
        }

        if ($decoder === '') {
            $ok("{$name}: encoded as version {$qr->version()}");
            continue;
        }

        $file = $out . '/' . md5($name) . '.png';
        file_put_contents($file, $png);
        $raw = (string) shell_exec('zbarimg --quiet --raw ' . escapeshellarg($file) . ' 2>/dev/null');
        rtrim($raw, "\r\n") === $payload
            ? $ok("{$name}: version {$qr->version()} decoded back to the exact payload")
            : $no("{$name}: decoded to '" . rtrim($raw, "\r\n") . "'");
    }
}

// Every symbol version must encode and read back, not just the small ones.
// A payload sized to each version's capacity forces that exact version.
/**
 * Smallest payload that forces a given symbol version, found by asking the
 * encoder itself rather than duplicating its capacity tables in the test.
 */
$lengthForVersion = static function (int $target, string $level, int $max): ?int {
    $lo = 1;
    $hi = $max;
    $best = null;
    while ($lo <= $hi) {
        $mid = intdiv($lo + $hi, 2);
        try {
            $version = (new QrCode(str_repeat('7', $mid), $level))->version();
        } catch (Throwable) {
            $hi = $mid - 1;
            continue;
        }
        if ($version >= $target) {
            if ($version === $target) {
                $best = $mid;
            }
            $hi = $mid - 1;
        } else {
            $lo = $mid + 1;
        }
    }

    return $best;
};

foreach (['L', 'M', 'Q', 'H'] as $level) {
    $max = (new QrCode('x', $level))->maxBytes();
    foreach ([1, 7, 10, 14, 21, 27, 28, 35, 40] as $target) {
        $length = $lengthForVersion($target, $level, $max);
        if ($length === null) {
            $no("version {$target} [{$level}]: no payload length selects this version");
            continue;
        }
        $payload = str_repeat('7', $length);
        try {
            $qr = new QrCode($payload, $level);
        } catch (Throwable $e) {
            $no("version {$target} [{$level}]: encoding threw -- " . $e->getMessage());
            continue;
        }
        if ($qr->version() !== $target) {
            $no("version {$target} [{$level}]: encoder chose version {$qr->version()}");
            continue;
        }
        if ($decoder === '') {
            $ok("version {$target} [{$level}]: encoded");
            continue;
        }
        $file = $out . '/v' . $target . $level . '.png';
        file_put_contents($file, $qr->png(8, 4));
        $raw = rtrim((string) shell_exec('zbarimg --quiet --raw ' . escapeshellarg($file) . ' 2>/dev/null'), "\r\n");
        $raw === $payload
            ? $ok("version {$target} [{$level}]: " . strlen($payload) . ' bytes decoded intact')
            : $no("version {$target} [{$level}]: decode mismatch (" . strlen($raw) . ' of ' . strlen($payload) . ' bytes)');
    }
}

// SVG output must be well-formed vector markup.
try {
    $qr = new QrCode('https://example.com/card/akshay', 'M');
    $svg = $qr->svg(8, 4);
    $xml = @simplexml_load_string($svg);
    $xml !== false && str_contains($svg, '<svg')
        ? $ok('SVG output is well-formed XML')
        : $no('SVG output is not well-formed XML');
} catch (Throwable $e) {
    $no('SVG output threw -- ' . $e->getMessage());
}

array_map('unlink', glob($out . '/*') ?: []);
@rmdir($out);

echo "\nRESULT: {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
