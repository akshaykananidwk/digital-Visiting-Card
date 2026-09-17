<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Secure file uploads.
 *
 * Every upload is checked for: PHP upload errors, real MIME type (finfo, not
 * the client-supplied value), extension whitelist, size limit and — for
 * images — that GD can actually decode the file. Files are stored with
 * random names in a date-sharded directory and are re-encoded so that any
 * embedded payload is destroyed.
 */
final class Uploader
{
    private int $maxSize;

    /** @var array<int,string> */
    private array $allowedMime;

    /** @var array<int,string> */
    private array $allowedExt;

    private int $maxWidth;

    private int $quality;

    public function __construct(?int $maxSize = null)
    {
        $this->maxSize = $maxSize ?? (int) Config::get('app.uploads.max_size', 5 * 1024 * 1024);
        $this->allowedMime = (array) Config::get('app.uploads.image_mime', ['image/jpeg', 'image/png', 'image/webp']);
        $this->allowedExt = (array) Config::get('app.uploads.image_ext', ['jpg', 'jpeg', 'png', 'webp']);
        $this->maxWidth = (int) Config::get('app.uploads.max_width', 2000);
        $this->quality = (int) Config::get('app.uploads.quality', 82);
    }

    /**
     * Store an uploaded image and return its path relative to /uploads.
     *
     * @param array<string,mixed> $file  A single entry from $_FILES
     * @param string              $folder e.g. "cards", "products"
     */
    public function image(array $file, string $folder, ?int $maxWidth = null): string
    {
        $this->assertNoError($file);
        $this->assertSize($file);

        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp) && !is_file($tmp)) {
            throw new RuntimeException('Upload could not be read.');
        }

        $mime = $this->detectMime($tmp);
        if (!in_array($mime, $this->allowedMime, true)) {
            throw new RuntimeException('Only JPG, PNG, WEBP and GIF images are allowed.');
        }

        $extension = $this->extensionFor($mime);
        $clientExt = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($clientExt !== '' && !in_array($clientExt, $this->allowedExt, true)) {
            throw new RuntimeException('That file extension is not allowed.');
        }

        $info = @getimagesize($tmp);
        if ($info === false || (int) $info[0] < 1 || (int) $info[1] < 1) {
            throw new RuntimeException('The uploaded file is not a valid image.');
        }
        if ((int) $info[0] * (int) $info[1] > 60_000_000) {
            throw new RuntimeException('Image resolution is too large.');
        }

        $relative = $this->relativePath($folder, $extension);
        $absolute = UPLOAD_PATH . '/' . $relative;
        $this->ensureDirectory(dirname($absolute));

        $processed = $this->processImage($tmp, $absolute, $mime, $maxWidth ?? $this->maxWidth);
        if (!$processed) {
            // GD unavailable for this type — fall back to a plain move after
            // all the validations above have passed.
            if (!$this->moveFile($tmp, $absolute)) {
                throw new RuntimeException('Could not save the uploaded file.');
            }
        }

        @chmod($absolute, 0644);

        return $relative;
    }

    /**
     * Store a non-image document (used by the backup restore screen).
     *
     * @param array<string,mixed> $file
     * @param array<int,string>   $extensions
     * @param array<int,string>   $mimes
     */
    public function file(array $file, string $folder, array $extensions, array $mimes, ?int $maxSize = null): string
    {
        $this->assertNoError($file);
        $this->assertSize($file, $maxSize);

        $tmp = (string) $file['tmp_name'];
        $mime = $this->detectMime($tmp);
        if ($mimes !== [] && !in_array($mime, $mimes, true)) {
            throw new RuntimeException('That file type is not allowed.');
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions, true)) {
            throw new RuntimeException('That file extension is not allowed.');
        }

        $relative = $this->relativePath($folder, $ext);
        $absolute = UPLOAD_PATH . '/' . $relative;
        $this->ensureDirectory(dirname($absolute));

        if (!$this->moveFile($tmp, $absolute)) {
            throw new RuntimeException('Could not save the uploaded file.');
        }
        @chmod($absolute, 0644);

        return $relative;
    }

    /** Delete a previously stored upload (path relative to /uploads). */
    public static function delete(?string $relative): bool
    {
        if ($relative === null || trim($relative) === '') {
            return false;
        }
        if (preg_match('#^https?://#i', $relative) === 1) {
            return false;
        }

        $absolute = realpath(UPLOAD_PATH . '/' . ltrim($relative, '/'));
        $root = realpath(UPLOAD_PATH);
        if ($absolute === false || $root === false || !str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) {
            return false;
        }

        return @unlink($absolute);
    }

    // --------------------------------------------------------- internals --

    /** @param array<string,mixed> $file */
    private function assertNoError(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_OK) {
            return;
        }
        throw new RuntimeException(match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the server allows.',
            UPLOAD_ERR_PARTIAL                        => 'The upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE                        => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not store the file.',
            UPLOAD_ERR_EXTENSION                      => 'The upload was blocked by the server.',
            default                                   => 'The upload failed.',
        });
    }

    /** @param array<string,mixed> $file */
    private function assertSize(array $file, ?int $maxSize = null): void
    {
        $limit = $maxSize ?? $this->maxSize;
        if ((int) ($file['size'] ?? 0) > $limit) {
            throw new RuntimeException('Maximum file size is ' . human_size($limit) . '.');
        }
        if ((int) ($file['size'] ?? 0) <= 0) {
            throw new RuntimeException('The uploaded file is empty.');
        }
    }

    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string) finfo_file($finfo, $path);
                finfo_close($finfo);

                return strtolower($mime);
            }
        }
        $info = @getimagesize($path);

        return $info !== false ? strtolower((string) ($info['mime'] ?? '')) : 'application/octet-stream';
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default      => 'bin',
        };
    }

    private function relativePath(string $folder, string $extension): string
    {
        $folder = preg_replace('/[^a-z0-9_\-]/i', '', $folder) ?: 'misc';

        return sprintf('%s/%s/%s.%s', $folder, date('Y/m'), bin2hex(random_bytes(16)), $extension);
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Upload directory is not writable.');
        }
    }

    private function moveFile(string $tmp, string $destination): bool
    {
        if (is_uploaded_file($tmp)) {
            return move_uploaded_file($tmp, $destination);
        }

        return @rename($tmp, $destination) || @copy($tmp, $destination);
    }

    /**
     * Re-encode the image through GD: strips metadata/embedded payloads,
     * downscales oversized images and normalises orientation.
     */
    private function processImage(string $source, string $destination, string $mime, int $maxWidth): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        $image = match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($source) : false,
            'image/png'  => function_exists('imagecreatefrompng') ? @imagecreatefrompng($source) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            'image/gif'  => function_exists('imagecreatefromgif') ? @imagecreatefromgif($source) : false,
            default      => false,
        };

        if ($image === false) {
            return false;
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($source);
            $orientation = (int) ($exif['Orientation'] ?? 1);
            if (in_array($orientation, [3, 6, 8], true)) {
                $angle = match ($orientation) {
                    3 => 180,
                    6 => -90,
                    8 => 90,
                    default => 0,
                };
                $rotated = @imagerotate($image, (float) $angle, 0);
                if ($rotated !== false) {
                    imagedestroy($image);
                    $image = $rotated;
                }
            }
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($maxWidth > 0 && $width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) max(1, round($height * ($maxWidth / $width)));
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            if ($resized !== false) {
                if (in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                    imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, (int) $transparent);
                }
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
            }
        } elseif (in_array($mime, ['image/png', 'image/webp'], true)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($image, $destination, $this->quality),
            'image/png'  => imagepng($image, $destination, 7),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, $destination, $this->quality) : false,
            'image/gif'  => imagegif($image, $destination),
            default      => false,
        };

        imagedestroy($image);

        return $saved;
    }

    /** Create an additional WebP copy next to the original, when supported. */
    public static function makeWebp(string $relative): ?string
    {
        if (!function_exists('imagewebp')) {
            return null;
        }
        $absolute = UPLOAD_PATH . '/' . ltrim($relative, '/');
        if (!is_file($absolute)) {
            return null;
        }
        $mime = (string) (@getimagesize($absolute)['mime'] ?? '');
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($absolute),
            'image/png'  => @imagecreatefrompng($absolute),
            default      => false,
        };
        if ($image === false) {
            return null;
        }
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $webpRelative = preg_replace('/\.[a-z0-9]+$/i', '.webp', $relative) ?? ($relative . '.webp');
        $ok = imagewebp($image, UPLOAD_PATH . '/' . $webpRelative, 80);
        imagedestroy($image);

        return $ok ? $webpRelative : null;
    }
}
