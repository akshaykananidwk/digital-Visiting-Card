<?php

declare(strict_types=1);

/**
 * Update file handling.
 *
 * An update that cannot write one file rolls the whole release back, so the
 * rules about which files it may touch matter as much as the download. Two
 * categories exist for good reason:
 *
 *   protected  - the server's own data and settings, never touched
 *   seed-only  - shipped with the platform, but the server's once it exists
 *
 * .user.ini is the second kind. A site needs it, because it carries the PHP
 * limits on hosts where PHP is not an Apache module, but control panels
 * manage that file too -- cPanel marks it immutable so a site cannot override
 * the settings it hands out. An update that insisted on replacing it failed
 * and rolled back, which is what this guards against.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\UpdateService;

$pass = 0;
$fail = 0;
$ok = static function (string $m) use (&$pass): void { echo "  PASS  {$m}\n"; $pass++; };
$no = static function (string $m) use (&$fail): void { echo "  FAIL  {$m}\n"; $fail++; };

$service = new UpdateService();
$reflection = new ReflectionObject($service);
$applyFiles = $reflection->getMethod('applyFiles');
$applyFiles->setAccessible(true);

/** Build a throwaway "incoming release" tree. */
$makeRelease = static function (array $files): string {
    $root = sys_get_temp_dir() . '/dvc-release-' . bin2hex(random_bytes(4));
    foreach ($files as $path => $contents) {
        $full = $root . '/' . $path;
        if (!is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }
        file_put_contents($full, $contents);
    }

    return $root;
};
$removeTree = static function (string $dir) use (&$removeTree): void {
    foreach (glob($dir . '/*') ?: [] as $entry) {
        is_dir($entry) ? $removeTree($entry) : @unlink($entry);
    }
    @rmdir($dir);
};

// --- An existing .user.ini is left alone ----------------------------------
$userIni = BASE_PATH . '/.user.ini';
$hadUserIni = is_file($userIni);
$originalUserIni = $hadUserIni ? (string) file_get_contents($userIni) : null;
if (!$hadUserIni) {
    file_put_contents($userIni, "; placed by the test\n");
}
$before = (string) file_get_contents($userIni);

$release = $makeRelease([
    '.user.ini'            => "display_errors = Off\nupload_max_filesize = 99M\n",
    'tests/.updater-probe' => "written by the updater\n",
]);
try {
    $result = $applyFiles->invoke($service, $release, UpdateService::DEFAULT_PROTECTED);
    (string) file_get_contents($userIni) === $before
        ? $ok('an existing .user.ini is not replaced by an update')
        : $no('AN UPDATE OVERWROTE .user.ini');
    $result['skipped'] >= 1
        ? $ok('.user.ini is counted as skipped rather than written')
        : $no('.user.ini was not skipped');
    is_file(BASE_PATH . '/tests/.updater-probe')
        ? $ok('ordinary files are still written')
        : $no('ordinary files were not written');
} catch (Throwable $e) {
    $no('applyFiles threw where it should have skipped: ' . $e->getMessage());
}
@unlink(BASE_PATH . '/tests/.updater-probe');
$removeTree($release);

// --- A missing .user.ini is created --------------------------------------
@unlink($userIni);
$release = $makeRelease(['.user.ini' => "display_errors = Off\nupload_max_filesize = 16M\n"]);
try {
    $applyFiles->invoke($service, $release, UpdateService::DEFAULT_PROTECTED);
    is_file($userIni)
        ? $ok('a server without .user.ini is given one')
        : $no('.user.ini was not created on a server that lacks it');
} catch (Throwable $e) {
    $no('applyFiles threw while seeding .user.ini: ' . $e->getMessage());
}
$removeTree($release);

// Put the file back exactly as it was.
if ($originalUserIni !== null) {
    file_put_contents($userIni, $originalUserIni);
} elseif (!$hadUserIni) {
    @unlink($userIni);
}

// --- Protected paths stay protected ---------------------------------------
$isProtected = $reflection->getMethod('isProtected');
$isProtected->setAccessible(true);
foreach (['.env', 'storage/logs/app.log', 'uploads/cards/x.jpg', 'install/.installed'] as $path) {
    $isProtected->invoke(null, $path, UpdateService::DEFAULT_PROTECTED)
        ? $ok("{$path} is protected from updates")
        : $no("{$path} is NOT protected from updates");
}
foreach (['app/Core/App.php', 'assets/css/app.css', 'config/routes.php'] as $path) {
    $isProtected->invoke(null, $path, UpdateService::DEFAULT_PROTECTED)
        ? $no("{$path} is protected, so updates could never ship a fix for it")
        : $ok("{$path} is replaceable by an update");
}

// --- A file that cannot be replaced explains itself ------------------------
$blocked = BASE_PATH . '/.updater-blocked-probe';
$removeTree($blocked);
mkdir($blocked . '/keep', 0755, true);          // a directory cannot be replaced by rename()
$release = $makeRelease(['.updater-blocked-probe' => "incoming\n"]);
try {
    $applyFiles->invoke($service, $release, UpdateService::DEFAULT_PROTECTED);
    $no('replacing an unwritable path silently succeeded');
} catch (Throwable $e) {
    $message = $e->getMessage();
    str_contains($message, 'owned by') && str_contains($message, 'PHP runs as')
        ? $ok('a failed replace reports owner, permissions and the user PHP runs as')
        : $no('a failed replace gives no diagnosis: ' . $message);
}
$removeTree($release);
$removeTree($blocked);
@unlink(BASE_PATH . '/.updater-blocked-probe.dvcnew');

echo "\nRESULT: {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
