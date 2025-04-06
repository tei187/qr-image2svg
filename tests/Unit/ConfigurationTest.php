<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use tei187\QrImage2Svg\Configuration;

class ConfigurationTest extends TestCase
{
    private Configuration $config;

    protected function setUp(): void
    {
        $this->config = new Configuration();
    }

    public function testSetStepsWithValidValue()
    {
        $this->config->setSteps(30);
        $this->assertEquals(30, $this->config->getSteps());
    }

    public function testSetStepsWithNullValue()
    {
        $this->config->setSteps(null);
        $this->assertNull($this->config->getSteps());
    }

    public function testSetStepsWithInvalidLowValue()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Steps must be at least 21.');
        $this->config->setSteps(20);
    }

    public function testSetStepsWithInvalidHighValue()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Steps must be at most 177.');
        $this->config->setSteps(178);
    }

    public function testSetThresholdWithValidValue()
    {
        $this->config->setThreshold(100);
        $this->assertEquals(100, $this->config->getThreshold());
    }

    public function testSetThresholdWithInvalidLowValue()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Threshold must be between 0 and 255.');
        $this->config->setThreshold(-1);
    }

    public function testSetThresholdWithInvalidHighValue()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Threshold must be between 0 and 255.');
        $this->config->setThreshold(256);
    }

    public function testSetChannelWithValidValue()
    {
        $this->config->setChannel('green');
        $this->assertEquals('green', $this->config->getChannel());
    }

    public function testSetChannelWithInvalidValue()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid channel. Must be one of: red, green, blue');
        $this->config->setChannel('yellow');
    }

    public function testSetInputDirWithValidPath()
    {
        $validPath = __DIR__;
        $this->config->setInputDir($validPath);
        $this->assertEquals($this->config->getInputDir(), realpath($validPath));
    }

    public function testSetInputDirWithInvalidPath()
    {
        $invalidPath = '/invalid/path';
        $this->expectException(\InvalidArgumentException::class);
        $this->config->setInputDir($invalidPath);
    }

    public function testSetOutputDirWithValidPath()
    {
        $validPath = __DIR__;
        $this->config->setOutputDir($validPath);
        $this->assertEquals($this->config->getOutputDir(), realpath($validPath));
    }

    public function testSetOutputDirWithInvalidPath()
    {
        $invalidPath = '/invalid/output/path';
        $this->expectException(\InvalidArgumentException::class);
        $this->config->setOutputDir($invalidPath);
    }

    public function testSetFileNameWithValidName()
    {
        $this->config->setInputDir("..");
        $validFileName = 'qr.jpg';
        $this->config->setFileName($validFileName);
        $this->assertEquals($validFileName, $this->config->getFileName());
        $this->assertEquals('jpg', $this->config->getFileExtension());
        $this->assertEquals('qr', $this->config->getFileBase());
    }

    public function testSetFileNameWithInvalidName()
    {
        $invalidFileName = 'invalid.jpg';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Unable to generate full path. Input path or file name is missing.");
        $this->config->setFileName($invalidFileName);
    }

    public function testGetFullInputPathWithValidData()
    {
        $this->config->setInputDir(realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR));
        $this->config->setFileName('qr.jpg');
        $this->assertEquals(realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'qr.jpg'), $this->config->getFullInputPath());
    }

    public function testGetFullInputPathWithMissingData()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to generate full path. Input path or file name is missing.');
        $this->config->getFullInputPath();
    }

    public function testGetFullOutputPathWithValidData()
    {
        $this->config->setInputDir(__DIR__);
        $this->config->setFileName('qr.jpg');
        $this->config->setOutputDir(sys_get_temp_dir());
        $this->assertEquals(sys_get_temp_dir() . DIRECTORY_SEPARATOR . "qr.jpg", $this->config->getFullOutputPath(null, false));
    }

    public function testGetFullOutputPathWithSuffix()
    {
        $this->config->setInputDir(__DIR__);
        $this->config->setOutputDir(sys_get_temp_dir());
        $this->config->setFileName('test.jpg');
        $this->assertEquals(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_suffix.jpg', $this->config->getFullOutputPath('suffix'));
    }

    public function testGetFullOutputPathWithMissingData()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to generate full path. Output path or file name is missing.');
        $this->config->getFullOutputPath();
    }

    public function testSetAndGetImMagickPrefixUse()
    {
        $this->config->setImMagickPrefixUse(false);
        $this->assertFalse($this->config->getImMagickPrefixUse());

        $this->config->setImMagickPrefixUse(true);
        $this->assertTrue($this->config->getImMagickPrefixUse());
    }

    public function testSetAndGetImConvertPrefixUse()
    {
        $this->config->setImConvertPrefixUse(false);
        $this->assertFalse($this->config->getImConvertPrefixUse());

        $this->config->setImConvertPrefixUse(true);
        $this->assertTrue($this->config->getImConvertPrefixUse());
    }
}
