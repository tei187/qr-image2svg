<?php

namespace Tests\Unit\Utilities;

use PHPUnit\Framework\TestCase;
use tei187\QrImage2Svg\Utilities\PathValidator;

class PathValidatorTest extends TestCase
{
    public function testvalidateWithValidPath()
    {
        $this->assertEquals(__DIR__, PathValidator::validate(__DIR__, true, false));
    }

    public function testvalidateWithInvalidPath()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Path is not a directory: \invalid\path');
        PathValidator::validate('/invalid/../path', true, false);
    }

    public function testvalidateWithEmptyPath()
    {
        $this->expectException(\InvalidArgumentException::class);
        PathValidator::validate('');
    }

    public function testvalidateWithNullPath()
    {
        $this->expectException(\TypeError::class);
        PathValidator::validate(null);
    }

    public function testvalidateWithSpecialCharacters()
    {
        $this->expectException(\InvalidArgumentException::class);
        PathValidator::validate('/path/with/special/!@#$%^&*()');
    }

    public function testvalidateWithWindowsStylePath()
    {
        $path = 'C:\\';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Directory is not writable: ' . $path);
        PathValidator::validate($path, true, false);
    }

    public function testvalidateWithRelativePathAllowed()
    {
        PathValidator::setAllowedRelativePath(true);
        $result = PathValidator::validate(__DIR__ . DIRECTORY_SEPARATOR . '..', true, false);
        $this->assertEquals(realpath(__DIR__ . DIRECTORY_SEPARATOR . ".."), $result);
    }

    public function testvalidateWithRelativePathDisallowed()
    {
        $result = PathValidator::validate(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . '..', true, false);
        $this->assertEquals(__DIR__, $result);
    }
}
