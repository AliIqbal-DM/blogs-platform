<?php

namespace App\Support;

class BrandColor
{
    public const DEFAULT = '#4f46e5';

    private const WHITE = '#ffffff';

    /** zinc-900, the text color used across the app. */
    private const DARK = '#18181b';

    /**
     * The color when it is a #rrggbb hex string, otherwise null.
     */
    public static function valid(mixed $color): ?string
    {
        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : null;
    }

    public static function orDefault(mixed $color): string
    {
        return static::valid($color) ?? static::DEFAULT;
    }

    /**
     * White or dark text for a background of the given #rrggbb color, whichever has the higher WCAG contrast.
     */
    public static function foreground(string $background): string
    {
        $luminance = static::luminance($background);

        $onWhite = 1.05 / ($luminance + 0.05);
        $onDark = ($luminance + 0.05) / (static::luminance(static::DARK) + 0.05);

        return $onWhite >= $onDark ? static::WHITE : static::DARK;
    }

    /**
     * WCAG 2 relative luminance.
     */
    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (string $channel) {
            $value = hexdec($channel) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
