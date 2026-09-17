<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Self-contained QR Code encoder (ISO/IEC 18004), byte mode, versions 1-40,
 * all four error-correction levels. Renders to SVG, PNG (GD) and a raw
 * boolean matrix. No external library or web service is used, so cards keep
 * working on hosts without outbound internet access.
 */
final class QrCode
{
    public const ECC_L = 'L';
    public const ECC_M = 'M';
    public const ECC_Q = 'Q';
    public const ECC_H = 'H';

    /** Highest symbol version defined by the standard. */
    private const MAX_VERSION = 40;

    /** version => total codewords */
    private const TOTAL_CODEWORDS = [
        1 => 26, 2 => 44, 3 => 70, 4 => 100, 5 => 134,
        6 => 172, 7 => 196, 8 => 242, 9 => 292, 10 => 346,
        11 => 404, 12 => 466, 13 => 532, 14 => 581, 15 => 655,
        16 => 733, 17 => 815, 18 => 901, 19 => 991, 20 => 1085,
        21 => 1156, 22 => 1258, 23 => 1364, 24 => 1474, 25 => 1588,
        26 => 1706, 27 => 1828, 28 => 1921, 29 => 2051, 30 => 2185,
        31 => 2323, 32 => 2465, 33 => 2611, 34 => 2761, 35 => 2876,
        36 => 3034, 37 => 3196, 38 => 3362, 39 => 3532, 40 => 3706,
    ];

    /**
     * version => level => [ec codewords per block, [ [blocks, data codewords], ... ] ]
     */
    private const ECC_TABLE = [
        1  => ['L' => [7,  [[1, 19]]],            'M' => [10, [[1, 16]]],            'Q' => [13, [[1, 13]]],            'H' => [17, [[1, 9]]]],
        2  => ['L' => [10, [[1, 34]]],            'M' => [16, [[1, 28]]],            'Q' => [22, [[1, 22]]],            'H' => [28, [[1, 16]]]],
        3  => ['L' => [15, [[1, 55]]],            'M' => [26, [[1, 44]]],            'Q' => [18, [[2, 17]]],            'H' => [22, [[2, 13]]]],
        4  => ['L' => [20, [[1, 80]]],            'M' => [18, [[2, 32]]],            'Q' => [26, [[2, 24]]],            'H' => [16, [[4, 9]]]],
        5  => ['L' => [26, [[1, 108]]],           'M' => [24, [[2, 43]]],            'Q' => [18, [[2, 15], [2, 16]]],   'H' => [22, [[2, 11], [2, 12]]]],
        6  => ['L' => [18, [[2, 68]]],            'M' => [16, [[4, 27]]],            'Q' => [24, [[4, 19]]],            'H' => [28, [[4, 15]]]],
        7  => ['L' => [20, [[2, 78]]],            'M' => [18, [[4, 31]]],            'Q' => [18, [[2, 14], [4, 15]]],   'H' => [26, [[4, 13], [1, 14]]]],
        8  => ['L' => [24, [[2, 97]]],            'M' => [22, [[2, 38], [2, 39]]],   'Q' => [22, [[4, 18], [2, 19]]],   'H' => [26, [[4, 14], [2, 15]]]],
        9  => ['L' => [30, [[2, 116]]],           'M' => [22, [[3, 36], [2, 37]]],   'Q' => [20, [[4, 16], [4, 17]]],   'H' => [24, [[4, 12], [4, 13]]]],
        10 => ['L' => [18, [[2, 68], [2, 69]]],   'M' => [26, [[4, 43], [1, 44]]],   'Q' => [24, [[6, 19], [2, 20]]],   'H' => [28, [[6, 15], [2, 16]]]],
        11 => ['L' => [20, [[4, 81]]], 'M' => [30, [[1, 50], [4, 51]]], 'Q' => [28, [[4, 22], [4, 23]]], 'H' => [24, [[3, 12], [8, 13]]]],
        12 => ['L' => [24, [[2, 92], [2, 93]]], 'M' => [22, [[6, 36], [2, 37]]], 'Q' => [26, [[4, 20], [6, 21]]], 'H' => [28, [[7, 14], [4, 15]]]],
        13 => ['L' => [26, [[4, 107]]], 'M' => [22, [[8, 37], [1, 38]]], 'Q' => [24, [[8, 20], [4, 21]]], 'H' => [22, [[12, 11], [4, 12]]]],
        14 => ['L' => [30, [[3, 115], [1, 116]]], 'M' => [24, [[4, 40], [5, 41]]], 'Q' => [20, [[11, 16], [5, 17]]], 'H' => [24, [[11, 12], [5, 13]]]],
        15 => ['L' => [22, [[5, 87], [1, 88]]], 'M' => [24, [[5, 41], [5, 42]]], 'Q' => [30, [[5, 24], [7, 25]]], 'H' => [24, [[11, 12], [7, 13]]]],
        16 => ['L' => [24, [[5, 98], [1, 99]]], 'M' => [28, [[7, 45], [3, 46]]], 'Q' => [24, [[15, 19], [2, 20]]], 'H' => [30, [[3, 15], [13, 16]]]],
        17 => ['L' => [28, [[1, 107], [5, 108]]], 'M' => [28, [[10, 46], [1, 47]]], 'Q' => [28, [[1, 22], [15, 23]]], 'H' => [28, [[2, 14], [17, 15]]]],
        18 => ['L' => [30, [[5, 120], [1, 121]]], 'M' => [26, [[9, 43], [4, 44]]], 'Q' => [28, [[17, 22], [1, 23]]], 'H' => [28, [[2, 14], [19, 15]]]],
        19 => ['L' => [28, [[3, 113], [4, 114]]], 'M' => [26, [[3, 44], [11, 45]]], 'Q' => [26, [[17, 21], [4, 22]]], 'H' => [26, [[9, 13], [16, 14]]]],
        20 => ['L' => [28, [[3, 107], [5, 108]]], 'M' => [26, [[3, 41], [13, 42]]], 'Q' => [30, [[15, 24], [5, 25]]], 'H' => [28, [[15, 15], [10, 16]]]],
        21 => ['L' => [28, [[4, 116], [4, 117]]], 'M' => [26, [[17, 42]]], 'Q' => [28, [[17, 22], [6, 23]]], 'H' => [30, [[19, 16], [6, 17]]]],
        22 => ['L' => [28, [[2, 111], [7, 112]]], 'M' => [28, [[17, 46]]], 'Q' => [30, [[7, 24], [16, 25]]], 'H' => [24, [[34, 13]]]],
        23 => ['L' => [30, [[4, 121], [5, 122]]], 'M' => [28, [[4, 47], [14, 48]]], 'Q' => [30, [[11, 24], [14, 25]]], 'H' => [30, [[16, 15], [14, 16]]]],
        24 => ['L' => [30, [[6, 117], [4, 118]]], 'M' => [28, [[6, 45], [14, 46]]], 'Q' => [30, [[11, 24], [16, 25]]], 'H' => [30, [[30, 16], [2, 17]]]],
        25 => ['L' => [26, [[8, 106], [4, 107]]], 'M' => [28, [[8, 47], [13, 48]]], 'Q' => [30, [[7, 24], [22, 25]]], 'H' => [30, [[22, 15], [13, 16]]]],
        26 => ['L' => [28, [[10, 114], [2, 115]]], 'M' => [28, [[19, 46], [4, 47]]], 'Q' => [28, [[28, 22], [6, 23]]], 'H' => [30, [[33, 16], [4, 17]]]],
        27 => ['L' => [30, [[8, 122], [4, 123]]], 'M' => [28, [[22, 45], [3, 46]]], 'Q' => [30, [[8, 23], [26, 24]]], 'H' => [30, [[12, 15], [28, 16]]]],
        28 => ['L' => [30, [[3, 117], [10, 118]]], 'M' => [28, [[3, 45], [23, 46]]], 'Q' => [30, [[4, 24], [31, 25]]], 'H' => [30, [[11, 15], [31, 16]]]],
        29 => ['L' => [30, [[7, 116], [7, 117]]], 'M' => [28, [[21, 45], [7, 46]]], 'Q' => [30, [[1, 23], [37, 24]]], 'H' => [30, [[19, 15], [26, 16]]]],
        30 => ['L' => [30, [[5, 115], [10, 116]]], 'M' => [28, [[19, 47], [10, 48]]], 'Q' => [30, [[15, 24], [25, 25]]], 'H' => [30, [[23, 15], [25, 16]]]],
        31 => ['L' => [30, [[13, 115], [3, 116]]], 'M' => [28, [[2, 46], [29, 47]]], 'Q' => [30, [[42, 24], [1, 25]]], 'H' => [30, [[23, 15], [28, 16]]]],
        32 => ['L' => [30, [[17, 115]]], 'M' => [28, [[10, 46], [23, 47]]], 'Q' => [30, [[10, 24], [35, 25]]], 'H' => [30, [[19, 15], [35, 16]]]],
        33 => ['L' => [30, [[17, 115], [1, 116]]], 'M' => [28, [[14, 46], [21, 47]]], 'Q' => [30, [[29, 24], [19, 25]]], 'H' => [30, [[11, 15], [46, 16]]]],
        34 => ['L' => [30, [[13, 115], [6, 116]]], 'M' => [28, [[14, 46], [23, 47]]], 'Q' => [30, [[44, 24], [7, 25]]], 'H' => [30, [[59, 16], [1, 17]]]],
        35 => ['L' => [30, [[12, 121], [7, 122]]], 'M' => [28, [[12, 47], [26, 48]]], 'Q' => [30, [[39, 24], [14, 25]]], 'H' => [30, [[22, 15], [41, 16]]]],
        36 => ['L' => [30, [[6, 121], [14, 122]]], 'M' => [28, [[6, 47], [34, 48]]], 'Q' => [30, [[46, 24], [10, 25]]], 'H' => [30, [[2, 15], [64, 16]]]],
        37 => ['L' => [30, [[17, 122], [4, 123]]], 'M' => [28, [[29, 46], [14, 47]]], 'Q' => [30, [[49, 24], [10, 25]]], 'H' => [30, [[24, 15], [46, 16]]]],
        38 => ['L' => [30, [[4, 122], [18, 123]]], 'M' => [28, [[13, 46], [32, 47]]], 'Q' => [30, [[48, 24], [14, 25]]], 'H' => [30, [[42, 15], [32, 16]]]],
        39 => ['L' => [30, [[20, 117], [4, 118]]], 'M' => [28, [[40, 47], [7, 48]]], 'Q' => [30, [[43, 24], [22, 25]]], 'H' => [30, [[10, 15], [67, 16]]]],
        40 => ['L' => [30, [[19, 118], [6, 119]]], 'M' => [28, [[18, 47], [31, 48]]], 'Q' => [30, [[34, 24], [34, 25]]], 'H' => [30, [[20, 15], [61, 16]]]],
    ];

    /** version => alignment pattern centre coordinates */
    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26],
        5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42],
        9 => [6, 26, 46], 10 => [6, 28, 50], 11 => [6, 30, 54], 12 => [6, 32, 58],
        13 => [6, 34, 62], 14 => [6, 26, 46, 66], 15 => [6, 26, 48, 70], 16 => [6, 26, 50, 74],
        17 => [6, 30, 54, 78], 18 => [6, 30, 56, 82], 19 => [6, 30, 58, 86], 20 => [6, 34, 62, 90],
        21 => [6, 28, 50, 72, 94], 22 => [6, 26, 50, 74, 98], 23 => [6, 30, 54, 78, 102], 24 => [6, 28, 54, 80, 106],
        25 => [6, 32, 58, 84, 110], 26 => [6, 30, 58, 86, 114], 27 => [6, 34, 62, 90, 118], 28 => [6, 26, 50, 74, 98, 122],
        29 => [6, 30, 54, 78, 102, 126], 30 => [6, 26, 52, 78, 104, 130], 31 => [6, 30, 56, 82, 108, 134], 32 => [6, 34, 60, 86, 112, 138],
        33 => [6, 30, 58, 86, 114, 142], 34 => [6, 34, 62, 90, 118, 146], 35 => [6, 30, 54, 78, 102, 126, 150], 36 => [6, 24, 50, 76, 102, 128, 154],
        37 => [6, 28, 54, 80, 106, 132, 158], 38 => [6, 32, 58, 84, 110, 136, 162], 39 => [6, 26, 54, 82, 110, 138, 166], 40 => [6, 30, 58, 86, 114, 142, 170],
    ];

    /**
     * 18-bit version information for versions >= 7: the 6-bit version number
     * followed by 12 BCH(18,6) check bits. Computed rather than tabulated so
     * there is no 34-entry table of magic constants to mistype.
     */
    private static function versionInfo(int $version): int
    {
        if ($version < 7) {
            return 0;
        }

        $remainder = $version;
        for ($i = 0; $i < 12; $i++) {
            $remainder = ($remainder << 1) ^ ((($remainder >> 11) & 1) * 0x1F25);
        }

        return (($version << 12) | $remainder) & 0x3FFFF;
    }

    private const ECC_BITS = ['L' => 0b01, 'M' => 0b00, 'Q' => 0b11, 'H' => 0b10];

    /** @var array<int,array<int,int>> 1 = dark, 0 = light, null slots filled during placement */
    private array $matrix = [];

    /** @var array<int,array<int,bool>> */
    private array $reserved = [];

    private int $size;

    private int $version;

    private string $level;

    private int $mask = 0;

    public function __construct(private readonly string $data, string $level = self::ECC_M, ?int $minVersion = null)
    {
        $this->level = in_array($level, [self::ECC_L, self::ECC_M, self::ECC_Q, self::ECC_H], true) ? $level : self::ECC_M;
        $this->version = $this->chooseVersion(strlen($this->data), $minVersion ?? 1);
        $this->size = 17 + 4 * $this->version;
        $this->build();
    }

    public static function make(string $data, string $level = self::ECC_M): self
    {
        return new self($data, $level);
    }

    // --------------------------------------------------------- Rendering --

    /** @return array<int,array<int,int>> */
    public function matrix(): array
    {
        return $this->matrix;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function svg(int $moduleSize = 8, int $margin = 4, string $dark = '#000000', string $light = '#FFFFFF'): string
    {
        $dimension = ($this->size + $margin * 2) * $moduleSize;
        $paths = [];

        for ($row = 0; $row < $this->size; $row++) {
            $col = 0;
            while ($col < $this->size) {
                if ($this->matrix[$row][$col] !== 1) {
                    $col++;

                    continue;
                }
                $run = 0;
                while ($col + $run < $this->size && $this->matrix[$row][$col + $run] === 1) {
                    $run++;
                }
                $paths[] = sprintf(
                    'M%d %dh%dv%dh-%dz',
                    ($col + $margin) * $moduleSize,
                    ($row + $margin) * $moduleSize,
                    $run * $moduleSize,
                    $moduleSize,
                    $run * $moduleSize
                );
                $col += $run;
            }
        }

        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" shape-rendering="crispEdges" role="img" aria-label="QR code">'
            . '<rect width="%d" height="%d" fill="%s"/><path d="%s" fill="%s"/></svg>',
            $dimension,
            $dimension,
            $dimension,
            $dimension,
            $dimension,
            $dimension,
            htmlspecialchars($light, ENT_QUOTES),
            implode('', $paths),
            htmlspecialchars($dark, ENT_QUOTES)
        );
    }

    /** Render a PNG. Returns binary image data. */
    public function png(int $moduleSize = 10, int $margin = 4, string $dark = '#000000', string $light = '#FFFFFF'): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('The GD extension is required to render PNG QR codes.');
        }

        $dimension = ($this->size + $margin * 2) * $moduleSize;
        $image = imagecreatetruecolor($dimension, $dimension);
        if ($image === false) {
            throw new RuntimeException('Could not allocate the QR image.');
        }

        [$lr, $lg, $lb] = self::hexToRgb($light);
        [$dr, $dg, $db] = self::hexToRgb($dark);
        $lightColor = (int) imagecolorallocate($image, $lr, $lg, $lb);
        $darkColor = (int) imagecolorallocate($image, $dr, $dg, $db);

        imagefilledrectangle($image, 0, 0, $dimension, $dimension, $lightColor);

        for ($row = 0; $row < $this->size; $row++) {
            for ($col = 0; $col < $this->size; $col++) {
                if ($this->matrix[$row][$col] === 1) {
                    $x = ($col + $margin) * $moduleSize;
                    $y = ($row + $margin) * $moduleSize;
                    imagefilledrectangle($image, $x, $y, $x + $moduleSize - 1, $y + $moduleSize - 1, $darkColor);
                }
            }
        }

        ob_start();
        imagepng($image, null, 6);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return $binary;
    }

    public function dataUri(int $moduleSize = 6, int $margin = 3): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode($this->svg($moduleSize, $margin));
    }

    /** @return array{0:int,1:int,2:int} */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return [0, 0, 0];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    // ---------------------------------------------------------- Encoding --

    private function chooseVersion(int $length, int $minVersion): int
    {
        for ($version = max(1, $minVersion); $version <= self::MAX_VERSION; $version++) {
            if ($length <= $this->capacity($version)) {
                return $version;
            }
        }

        throw new RuntimeException(
            'Data is too long for a QR code (' . $length . ' bytes; the maximum at error-correction level '
            . $this->level . ' is ' . $this->capacity(self::MAX_VERSION) . ').'
        );
    }

    /** Largest byte-mode payload this encoder can carry at the current level. */
    public function maxBytes(): int
    {
        return $this->capacity(self::MAX_VERSION);
    }

    private function capacity(int $version): int
    {
        [$ecPerBlock, $groups] = self::ECC_TABLE[$version][$this->level];
        $dataCodewords = 0;
        foreach ($groups as [$blocks, $perBlock]) {
            $dataCodewords += $blocks * $perBlock;
        }
        $countBits = $version <= 9 ? 8 : 16;
        $overheadBits = 4 + $countBits;

        return (int) floor(($dataCodewords * 8 - $overheadBits) / 8);
    }

    private function build(): void
    {
        $codewords = $this->encodeData();
        $this->initMatrix();
        $this->placeFunctionPatterns();
        $this->placeData($codewords);
        $this->mask = $this->applyBestMask();
        $this->placeFormatInfo($this->mask);
        if ($this->version >= 7) {
            $this->placeVersionInfo();
        }
    }

    /** @return array<int,int> Final interleaved codeword stream. */
    private function encodeData(): array
    {
        [$ecPerBlock, $groups] = self::ECC_TABLE[$this->version][$this->level];

        $bits = '';
        $bits .= '0100';                                            // byte mode
        $countBits = $this->version <= 9 ? 8 : 16;
        $bits .= str_pad(decbin(strlen($this->data)), $countBits, '0', STR_PAD_LEFT);
        foreach (str_split($this->data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $totalDataCodewords = 0;
        foreach ($groups as [$blocks, $perBlock]) {
            $totalDataCodewords += $blocks * $perBlock;
        }
        $capacityBits = $totalDataCodewords * 8;

        // Terminator (up to 4 zero bits) and byte alignment.
        $bits .= str_repeat('0', min(4, $capacityBits - strlen($bits)));
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        // Pad bytes 0xEC / 0x11 alternating.
        $pad = ['11101100', '00010001'];
        $index = 0;
        while (strlen($bits) < $capacityBits) {
            $bits .= $pad[$index % 2];
            $index++;
        }

        $dataCodewords = [];
        foreach (str_split($bits, 8) as $byte) {
            $dataCodewords[] = (int) bindec($byte);
        }

        // Split into blocks and compute Reed-Solomon ECC per block.
        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;
        foreach ($groups as [$blocks, $perBlock]) {
            for ($b = 0; $b < $blocks; $b++) {
                $block = array_slice($dataCodewords, $offset, $perBlock);
                $offset += $perBlock;
                $dataBlocks[] = $block;
                $ecBlocks[] = self::reedSolomon($block, $ecPerBlock);
            }
        }

        // Interleave data codewords, then EC codewords.
        $result = [];
        $maxData = 0;
        foreach ($dataBlocks as $block) {
            $maxData = max($maxData, count($block));
        }
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $result[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($ecBlocks as $block) {
                if (isset($block[$i])) {
                    $result[] = $block[$i];
                }
            }
        }

        return $result;
    }

    // ------------------------------------------------- Reed-Solomon (GF256) --

    /** @var array<int,int>|null */
    private static ?array $expTable = null;

    /** @var array<int,int>|null */
    private static ?array $logTable = null;

    private static function initGalois(): void
    {
        if (self::$expTable !== null) {
            return;
        }
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;                     // primitive polynomial
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }
        self::$expTable = $exp;
        self::$logTable = $log;
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        self::initGalois();

        return (int) self::$expTable[(self::$logTable[$a] + self::$logTable[$b]) % 255];
    }

    /** @return array<int,int> */
    private static function generatorPoly(int $degree): array
    {
        self::initGalois();
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $index => $coefficient) {
                $next[$index] ^= $coefficient;
                $next[$index + 1] ^= self::gfMul($coefficient, (int) self::$expTable[$i]);
            }
            $poly = $next;
        }

        return $poly;
    }

    /**
     * @param array<int,int> $data
     * @return array<int,int>
     */
    private static function reedSolomon(array $data, int $ecCount): array
    {
        $generator = self::generatorPoly($ecCount);
        $remainder = array_fill(0, $ecCount, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $remainder[0];
            array_shift($remainder);
            $remainder[] = 0;
            for ($i = 0; $i < $ecCount; $i++) {
                $remainder[$i] ^= self::gfMul($generator[$i + 1] ?? 0, $factor);
            }
        }

        return $remainder;
    }

    // ---------------------------------------------------------- Matrix ------

    private function initMatrix(): void
    {
        $this->matrix = array_fill(0, $this->size, array_fill(0, $this->size, -1));
        $this->reserved = array_fill(0, $this->size, array_fill(0, $this->size, false));
    }

    private function set(int $row, int $col, int $value, bool $reserve = true): void
    {
        if ($row < 0 || $col < 0 || $row >= $this->size || $col >= $this->size) {
            return;
        }
        $this->matrix[$row][$col] = $value;
        if ($reserve) {
            $this->reserved[$row][$col] = true;
        }
    }

    private function placeFunctionPatterns(): void
    {
        // Finder patterns + separators
        foreach ([[0, 0], [0, $this->size - 7], [$this->size - 7, 0]] as [$r, $c]) {
            for ($i = -1; $i <= 7; $i++) {
                for ($j = -1; $j <= 7; $j++) {
                    $row = $r + $i;
                    $col = $c + $j;
                    if ($row < 0 || $col < 0 || $row >= $this->size || $col >= $this->size) {
                        continue;
                    }
                    $inRing = ($i >= 0 && $i <= 6 && ($j === 0 || $j === 6))
                        || ($j >= 0 && $j <= 6 && ($i === 0 || $i === 6));
                    $inCore = $i >= 2 && $i <= 4 && $j >= 2 && $j <= 4;
                    $this->set($row, $col, ($inRing || $inCore) ? 1 : 0);
                }
            }
        }

        // Timing patterns
        for ($i = 8; $i < $this->size - 8; $i++) {
            $bit = $i % 2 === 0 ? 1 : 0;
            $this->set(6, $i, $bit);
            $this->set($i, 6, $bit);
        }

        // Alignment patterns
        $centres = self::ALIGNMENT[$this->version];
        foreach ($centres as $r) {
            foreach ($centres as $c) {
                // Skip the three positions overlapping the finder patterns.
                if (($r === 6 && $c === 6)
                    || ($r === 6 && $c === $this->size - 7)
                    || ($r === $this->size - 7 && $c === 6)) {
                    continue;
                }
                for ($i = -2; $i <= 2; $i++) {
                    for ($j = -2; $j <= 2; $j++) {
                        $dark = max(abs($i), abs($j)) !== 1 ? 1 : 0;
                        $this->set($r + $i, $c + $j, $dark);
                    }
                }
            }
        }

        // Dark module
        $this->set($this->size - 8, 8, 1);

        // Reserve format information areas
        for ($i = 0; $i < 9; $i++) {
            if ($this->matrix[8][$i] === -1) {
                $this->set(8, $i, 0);
            }
            if ($this->matrix[$i][8] === -1) {
                $this->set($i, 8, 0);
            }
        }
        for ($i = 0; $i < 8; $i++) {
            if ($this->matrix[8][$this->size - 1 - $i] === -1) {
                $this->set(8, $this->size - 1 - $i, 0);
            }
            if ($this->matrix[$this->size - 1 - $i][8] === -1) {
                $this->set($this->size - 1 - $i, 8, 0);
            }
        }

        // Reserve version information areas
        if ($this->version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $this->set($this->size - 11 + $j, $i, 0);
                    $this->set($i, $this->size - 11 + $j, 0);
                }
            }
        }
    }

    /** @param array<int,int> $codewords */
    private function placeData(array $codewords): void
    {
        $bits = '';
        foreach ($codewords as $codeword) {
            $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        }

        $index = 0;
        $length = strlen($bits);
        $upward = true;

        for ($right = $this->size - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right = 5;                       // skip the vertical timing column
            }
            for ($step = 0; $step < $this->size; $step++) {
                $row = $upward ? $this->size - 1 - $step : $step;
                for ($c = 0; $c < 2; $c++) {
                    $col = $right - $c;
                    if ($this->reserved[$row][$col]) {
                        continue;
                    }
                    $bit = $index < $length ? (int) $bits[$index] : 0;
                    $index++;
                    $this->matrix[$row][$col] = $bit;
                }
            }
            $upward = !$upward;
        }
    }

    private function applyBestMask(): int
    {
        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $original = $this->matrix;

        for ($mask = 0; $mask < 8; $mask++) {
            $this->matrix = $original;
            $this->applyMask($mask);
            $this->placeFormatInfo($mask);
            $penalty = $this->penalty();
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
            }
        }

        $this->matrix = $original;
        $this->applyMask($bestMask);

        return $bestMask;
    }

    private function applyMask(int $mask): void
    {
        for ($row = 0; $row < $this->size; $row++) {
            for ($col = 0; $col < $this->size; $col++) {
                if ($this->reserved[$row][$col]) {
                    continue;
                }
                $flip = match ($mask) {
                    0 => ($row + $col) % 2 === 0,
                    1 => $row % 2 === 0,
                    2 => $col % 3 === 0,
                    3 => ($row + $col) % 3 === 0,
                    4 => ((int) floor($row / 2) + (int) floor($col / 3)) % 2 === 0,
                    5 => (($row * $col) % 2) + (($row * $col) % 3) === 0,
                    6 => ((($row * $col) % 2) + (($row * $col) % 3)) % 2 === 0,
                    7 => ((($row + $col) % 2) + (($row * $col) % 3)) % 2 === 0,
                    default => false,
                };
                if ($flip) {
                    $this->matrix[$row][$col] ^= 1;
                }
            }
        }
    }

    private function placeFormatInfo(int $mask): void
    {
        $data = (self::ECC_BITS[$this->level] << 3) | $mask;
        $value = $data << 10;
        for ($i = 14; $i >= 10; $i--) {
            if ((($value >> $i) & 1) === 1) {
                $value ^= 0x537 << ($i - 10);
            }
        }
        $format = (($data << 10) | $value) ^ 0x5412;

        // $i is the bit's significance, bit 0 being the least significant.
        // The top-left copy runs up the column and then left along the row:
        // rows 0-5 and row 7 of column 8 carry bits 0-6, (8,8) carries bit 7,
        // and columns 7 then 5-0 of row 8 carry bits 8-14. Writing that copy
        // in the opposite order still leaves a symbol whose second copy is
        // right, so most of them scan; the ones where the mirrored bits
        // happen to form another valid format codeword are read with the
        // wrong mask and cannot be decoded at all.
        for ($i = 0; $i < 15; $i++) {
            $bit = ($format >> $i) & 1;

            // Top-left copy
            if ($i <= 5) {
                $this->matrix[$i][8] = $bit;
            } elseif ($i === 6) {
                $this->matrix[7][8] = $bit;
            } elseif ($i === 7) {
                $this->matrix[8][8] = $bit;
            } elseif ($i === 8) {
                $this->matrix[8][7] = $bit;
            } else {
                $this->matrix[8][14 - $i] = $bit;
            }

            // Split copy around the other two finders
            if ($i < 8) {
                $this->matrix[8][$this->size - 1 - $i] = $bit;
            } else {
                $this->matrix[$this->size - 15 + $i][8] = $bit;
            }
        }

        $this->matrix[$this->size - 8][8] = 1;     // dark module
    }

    private function placeVersionInfo(): void
    {
        $info = self::versionInfo($this->version);
        for ($i = 0; $i < 18; $i++) {
            $bit = ($info >> $i) & 1;
            $row = (int) floor($i / 3);
            $col = $this->size - 11 + ($i % 3);
            $this->matrix[$row][$col] = $bit;
            $this->matrix[$col][$row] = $bit;
        }
    }

    /** Occurrences of $needle in $haystack, counting overlaps. */
    private static function countOverlapping(string $haystack, string $needle): int
    {
        $count = 0;
        $offset = 0;
        while (($position = strpos($haystack, $needle, $offset)) !== false) {
            $count++;
            $offset = $position + 1;
        }

        return $count;
    }

    private function penalty(): int
    {
        $penalty = 0;
        $size = $this->size;

        // Rule 1 — runs of five or more same-coloured modules.
        for ($i = 0; $i < $size; $i++) {
            for ($direction = 0; $direction < 2; $direction++) {
                $run = 1;
                $previous = -1;
                for ($j = 0; $j < $size; $j++) {
                    $value = $direction === 0 ? $this->matrix[$i][$j] : $this->matrix[$j][$i];
                    if ($value === $previous) {
                        $run++;
                    } else {
                        if ($run >= 5) {
                            $penalty += 3 + ($run - 5);
                        }
                        $run = 1;
                        $previous = $value;
                    }
                }
                if ($run >= 5) {
                    $penalty += 3 + ($run - 5);
                }
            }
        }

        // Rule 2 — 2x2 blocks of the same colour.
        for ($row = 0; $row < $size - 1; $row++) {
            for ($col = 0; $col < $size - 1; $col++) {
                $value = $this->matrix[$row][$col];
                if ($value === $this->matrix[$row][$col + 1]
                    && $value === $this->matrix[$row + 1][$col]
                    && $value === $this->matrix[$row + 1][$col + 1]) {
                    $penalty += 3;
                }
            }
        }

        // Rule 3 — finder-like 1:1:3:1:1 patterns with four light modules on
        // one side. Occurrences may overlap ('10111010000101110100001' holds
        // two), and substr_count() would only see the first of each pair,
        // which lets a mask that riddles the symbol with false finder
        // patterns score as though it were clean.
        $patterns = ['10111010000', '00001011101'];
        for ($i = 0; $i < $size; $i++) {
            $rowString = '';
            $colString = '';
            for ($j = 0; $j < $size; $j++) {
                $rowString .= (string) $this->matrix[$i][$j];
                $colString .= (string) $this->matrix[$j][$i];
            }
            foreach ($patterns as $pattern) {
                $penalty += 40 * self::countOverlapping($rowString, $pattern);
                $penalty += 40 * self::countOverlapping($colString, $pattern);
            }
        }

        // Rule 4 — deviation from a 50% dark ratio.
        $dark = 0;
        foreach ($this->matrix as $row) {
            foreach ($row as $value) {
                if ($value === 1) {
                    $dark++;
                }
            }
        }
        $ratio = ($dark * 100) / ($size * $size);
        $penalty += (int) (floor(abs($ratio - 50) / 5) * 10);

        return $penalty;
    }
}
