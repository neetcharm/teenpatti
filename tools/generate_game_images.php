<?php

declare(strict_types=1);

/**
 * Generate 800x800 game card images used in the dashboard.
 */

$root = dirname(__DIR__);
$outDir = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'game';
$font = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'font' . DIRECTORY_SEPARATOR . 'solaimanLipi_bold.ttf';

if (!is_dir($outDir)) {
    fwrite(STDERR, "Output directory not found: {$outDir}\n");
    exit(1);
}

if (!function_exists('imagecreatetruecolor')) {
    fwrite(STDERR, "GD extension is required.\n");
    exit(1);
}

$games = [
    [
        'file' => 'teen_patti.png',
        'title' => 'TEEN PATTI',
        'tagline' => '3 CARD BET',
        'top' => [60, 13, 20],
        'bottom' => [28, 6, 12],
        'accent' => [244, 208, 63],
        'style' => 'teen_patti',
    ],
];

foreach ($games as $config) {
    renderCard($outDir, $font, $config);
}

echo "Generated " . count($games) . " images in {$outDir}\n";

function renderCard(string $outDir, string $font, array $config): void
{
    $w = 800;
    $h = 800;
    $img = imagecreatetruecolor($w, $h);

    imagealphablending($img, true);
    imagesavealpha($img, true);

    fillGradient($img, $w, $h, $config['top'], $config['bottom']);
    drawGlow($img, 120, 120, 250, [255, 255, 255], 115);
    drawGlow($img, 650, 220, 300, $config['accent'], 108);
    drawGlow($img, 390, 760, 520, $config['accent'], 120);

    addNoiseDots($img, $w, $h, 70);

    $frameColor = colorWithAlpha($img, $config['accent'][0], $config['accent'][1], $config['accent'][2], 35);
    imagerectangle($img, 16, 16, $w - 16, $h - 16, $frameColor);
    imagerectangle($img, 22, 22, $w - 22, $h - 22, $frameColor);

    drawStyleElement($img, $config['style'], $config['accent']);

    drawBottomPanel($img, $w, $h, $config['accent']);

    drawText($img, $font, $config['title'], 72, (int) ($w / 2), 130, [255, 255, 255]);
    drawText($img, $font, $config['tagline'], 34, (int) ($w / 2), 700, [245, 245, 245]);

    $out = $outDir . DIRECTORY_SEPARATOR . $config['file'];
    imagepng($img, $out, 6);
    imagedestroy($img);
}

function fillGradient($img, int $w, int $h, array $top, array $bottom): void
{
    for ($y = 0; $y < $h; $y++) {
        $mix = $y / max(1, ($h - 1));
        $r = (int) round($top[0] + (($bottom[0] - $top[0]) * $mix));
        $g = (int) round($top[1] + (($bottom[1] - $top[1]) * $mix));
        $b = (int) round($top[2] + (($bottom[2] - $top[2]) * $mix));
        $c = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $w, $y, $c);
    }
}

function drawGlow($img, int $cx, int $cy, int $radius, array $rgb, int $alphaStart): void
{
    for ($r = $radius; $r > 0; $r -= 3) {
        $alpha = min(127, $alphaStart + (int) ((1 - ($r / $radius)) * 22));
        $c = colorWithAlpha($img, $rgb[0], $rgb[1], $rgb[2], $alpha);
        imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $c);
    }
}

function addNoiseDots($img, int $w, int $h, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $x = random_int(20, $w - 20);
        $y = random_int(20, $h - 180);
        $size = random_int(2, 6);
        $alpha = random_int(70, 112);
        $c = colorWithAlpha($img, 255, 255, 255, $alpha);
        imagefilledellipse($img, $x, $y, $size, $size, $c);
    }
}

function drawBottomPanel($img, int $w, int $h, array $accent): void
{
    $panel = imagecolorallocatealpha($img, 8, 14, 28, 35);
    imagefilledrectangle($img, 40, $h - 180, $w - 40, $h - 40, $panel);

    $line = imagecolorallocatealpha($img, $accent[0], $accent[1], $accent[2], 35);
    imagerectangle($img, 40, $h - 180, $w - 40, $h - 40, $line);
}

function drawStyleElement($img, string $style, array $accent): void
{
    $accentColor = imagecolorallocate($img, $accent[0], $accent[1], $accent[2]);

    switch ($style) {
        case 'teen_patti':
            drawCard($img, 220, 280, 180, 240, [255, 255, 255], [220, 48, 48], 'A');
            drawCard($img, 330, 250, 180, 240, [255, 255, 255], [30, 30, 30], 'K');
            drawCard($img, 440, 280, 180, 240, [255, 255, 255], [220, 48, 48], 'Q');
            break;
    }
}

function drawCard($img, int $x, int $y, int $w, int $h, array $bg, array $fg, string $label): void
{
    $bgColor = imagecolorallocate($img, $bg[0], $bg[1], $bg[2]);
    $fgColor = imagecolorallocate($img, $fg[0], $fg[1], $fg[2]);
    $border = imagecolorallocatealpha($img, 30, 30, 30, 45);

    imagefilledrectangle($img, $x, $y, $x + $w, $y + $h, $bgColor);
    imagerectangle($img, $x, $y, $x + $w, $y + $h, $border);
    imagestring($img, 5, $x + 12, $y + 12, $label, $fgColor);
    imagestring($img, 5, $x + $w - 24, $y + $h - 30, $label, $fgColor);
}

function drawText($img, string $font, string $text, int $size, int $centerX, int $baselineY, array $rgb): void
{
    if (is_file($font) && function_exists('imagettftext')) {
        $bbox = imagettfbbox($size, 0, $font, $text);
        $textWidth = (int) abs($bbox[2] - $bbox[0]);
        $x = (int) ($centerX - ($textWidth / 2));
        $shadow = imagecolorallocatealpha($img, 0, 0, 0, 65);
        imagettftext($img, $size, 0, $x + 3, $baselineY + 3, $shadow, $font, $text);
        $color = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        imagettftext($img, $size, 0, $x, $baselineY, $color, $font, $text);
        return;
    }

    $color = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    imagestring($img, 5, max(20, $centerX - (strlen($text) * 8)), $baselineY - 20, $text, $color);
}

function colorWithAlpha($img, int $r, int $g, int $b, int $alpha)
{
    $alpha = max(0, min(127, $alpha));
    return imagecolorallocatealpha($img, $r, $g, $b, $alpha);
}
