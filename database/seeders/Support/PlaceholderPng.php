<?php

namespace Database\Seeders\Support;

/**
 * Placeholder images for demo data, drawn at seed time so no binaries are committed.
 */
final class PlaceholderPng
{
    /** A 1x1 PNG, used when GD is not installed. */
    private const FALLBACK = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    /**
     * A solid #rrggbb image with a lighter band and an optional short white label in the middle.
     */
    public static function make(int $width, int $height, string $color, ?string $label = null, ?bool $useGd = null): string
    {
        if (! ($useGd ?? function_exists('imagecreatetruecolor'))) {
            return base64_decode(self::FALLBACK);
        }

        [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');

        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));

        $band = imagecolorallocatealpha($image, 255, 255, 255, 100);
        imagefilledpolygon($image, [0, $height, (int) ($width * 0.55), 0, $width, 0, (int) ($width * 0.45), $height], $band);

        if ($label !== null && $label !== '') {
            self::drawLabel($image, $label, $width, $height);
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    /**
     * GD's built-in font is tiny, so the label is drawn small and scaled up.
     */
    private static function drawLabel(\GdImage $image, string $label, int $width, int $height): void
    {
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($label);
        $textHeight = imagefontheight($font);

        $text = imagecreatetruecolor($textWidth, $textHeight);
        $transparent = imagecolorallocatealpha($text, 0, 0, 0, 127);
        imagealphablending($text, false);
        imagefill($text, 0, 0, $transparent);
        imagesavealpha($text, true);
        imagestring($text, $font, 0, 0, $label, imagecolorallocate($text, 255, 255, 255));

        $scale = max(1, (int) floor(min($width * 0.6 / $textWidth, $height * 0.5 / $textHeight)));
        $targetWidth = $textWidth * $scale;
        $targetHeight = $textHeight * $scale;

        imagealphablending($image, true);
        imagecopyresized($image, $text, intdiv($width - $targetWidth, 2), intdiv($height - $targetHeight, 2), 0, 0, $targetWidth, $targetHeight, $textWidth, $textHeight);
        imagedestroy($text);
    }
}
