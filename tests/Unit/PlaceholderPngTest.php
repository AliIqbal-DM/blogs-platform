<?php

namespace Tests\Unit;

use Database\Seeders\Support\PlaceholderPng;
use PHPUnit\Framework\TestCase;

class PlaceholderPngTest extends TestCase
{
    public function test_it_draws_a_png_of_the_requested_size_with_gd(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not installed.');
        }

        $info = getimagesizefromstring(PlaceholderPng::make(320, 180, '#e11d48', 'A'));

        $this->assertSame([320, 180, 'image/png'], [$info[0], $info[1], $info['mime']]);
    }

    public function test_without_gd_it_falls_back_to_an_embedded_png(): void
    {
        $info = getimagesizefromstring(PlaceholderPng::make(320, 180, '#e11d48', 'A', useGd: false));

        $this->assertSame('image/png', $info['mime']);
    }
}
