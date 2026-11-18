<?php

namespace Tests\Unit;

use App\Support\BrandColor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BrandColorTest extends TestCase
{
    public function test_only_six_digit_hex_colors_are_valid(): void
    {
        $this->assertSame('#e11d48', BrandColor::valid('#e11d48'));
        $this->assertSame('#E11D48', BrandColor::valid('#E11D48'));

        foreach ([null, '', 'red', '#fff', '#e11d4', '#e11d488', 'e11d48', '#e11d48;x', 42] as $invalid) {
            $this->assertNull(BrandColor::valid($invalid));
        }
    }

    public function test_invalid_colors_fall_back_to_the_default(): void
    {
        $this->assertSame('#e11d48', BrandColor::orDefault('#e11d48'));
        $this->assertSame(BrandColor::DEFAULT, BrandColor::orDefault('nope'));
        $this->assertSame('#4f46e5', BrandColor::DEFAULT);
    }

    #[DataProvider('foregrounds')]
    public function test_foreground_is_the_more_readable_of_white_and_dark(string $brand, string $expected): void
    {
        $this->assertSame($expected, BrandColor::foreground($brand));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function foregrounds(): array
    {
        return [
            'default indigo' => ['#4f46e5', '#ffffff'],
            'rose' => ['#e11d48', '#ffffff'],
            'black' => ['#000000', '#ffffff'],
            'yellow' => ['#facc15', '#18181b'],
            'white' => ['#ffffff', '#18181b'],
            'lime' => ['#84cc16', '#18181b'],
        ];
    }
}
