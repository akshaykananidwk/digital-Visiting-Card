<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Url;

/** Builds RFC 6350 compliant vCard 3.0 payloads for the "Save contact" button. */
final class VCardService
{
    /** @param array<string,mixed> $card */
    public static function build(array $card): string
    {
        $name = trim((string) ($card['full_name'] ?: $card['title']));
        [$first, $last] = self::splitName($name);

        $lines = [];
        $lines[] = 'BEGIN:VCARD';
        $lines[] = 'VERSION:3.0';
        $lines[] = 'N:' . self::escape($last) . ';' . self::escape($first) . ';;;';
        $lines[] = 'FN:' . self::escape($name);

        if (!empty($card['business_name'])) {
            $lines[] = 'ORG:' . self::escape((string) $card['business_name']);
        }
        if (!empty($card['designation'])) {
            $lines[] = 'TITLE:' . self::escape((string) $card['designation']);
        }
        if (!empty($card['phone'])) {
            $lines[] = 'TEL;TYPE=CELL,VOICE:' . self::escape((string) $card['phone']);
        }
        if (!empty($card['phone_alt'])) {
            $lines[] = 'TEL;TYPE=WORK,VOICE:' . self::escape((string) $card['phone_alt']);
        }
        if (!empty($card['whatsapp'])) {
            $lines[] = 'TEL;TYPE=WORK,CELL:' . self::escape((string) $card['whatsapp']);
        }
        if (!empty($card['email'])) {
            $lines[] = 'EMAIL;TYPE=INTERNET,WORK:' . self::escape((string) $card['email']);
        }
        if (!empty($card['website'])) {
            $lines[] = 'URL:' . self::escape((string) $card['website']);
        }

        $addressParts = [
            '',                                      // PO box
            '',                                      // extended address
            (string) ($card['address'] ?? ''),
            (string) ($card['city'] ?? ''),
            (string) ($card['state'] ?? ''),
            (string) ($card['pincode'] ?? ''),
            (string) ($card['country'] ?? ''),
        ];
        if (trim(implode('', $addressParts)) !== '') {
            $lines[] = 'ADR;TYPE=WORK:' . implode(';', array_map([self::class, 'escape'], $addressParts));
        }

        if (!empty($card['latitude']) && !empty($card['longitude'])) {
            $lines[] = sprintf('GEO:%s;%s', $card['latitude'], $card['longitude']);
        }
        if (!empty($card['about'])) {
            $lines[] = 'NOTE:' . self::escape(mb_substr(strip_tags((string) $card['about']), 0, 500));
        }

        $lines[] = 'URL;TYPE=Digital Card:' . Url::card((string) $card['slug']);
        $lines[] = 'REV:' . gmdate('Ymd\THis\Z');
        $lines[] = 'END:VCARD';

        // vCard requires CRLF line endings.
        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    /** @param array<string,mixed> $card */
    public static function filename(array $card): string
    {
        $base = str_slug((string) ($card['full_name'] ?: $card['title'] ?: 'contact'));

        return $base . '.vcf';
    }

    private static function escape(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\\,', '\\n'], trim($value));
    }

    /** Fold long lines at 75 octets as required by the specification. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $chunks = str_split($line, 73);

        return array_shift($chunks) . "\r\n " . implode("\r\n ", $chunks);
    }

    /** @return array{0:string,1:string} */
    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) <= 1) {
            return [$parts[0] ?? '', ''];
        }
        $last = array_pop($parts);

        return [implode(' ', $parts), $last];
    }
}
