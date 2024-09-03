<?php

namespace tei187\QrImage2Svg\Tests;

use PHPUnit\Framework\TestCase;

class SolutionsAvailabilityTest extends TestCase
{
    public function testGDAvailability()
    {
        $this->assertTrue(
            extension_loaded('gd'),
            'GD PHP extension is not available'
        );
    }

    public function testImageMagickAvailability()
    {
        $this->assertTrue(
            class_exists('Imagick'),
            'Imagick PHP extension is not available'
        );
    }

    public function testImagickAvailability()
    {
        $this->assertStringContainsString(
            'ImageMagick',
            shell_exec('magick -version'),
            'ImageMagick CLI is not available'
        );
    }
}