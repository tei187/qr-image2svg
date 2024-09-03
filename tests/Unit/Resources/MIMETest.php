<?php

namespace Tests\Resources;

use PHPUnit\Framework\TestCase;
use tei187\QrImage2Svg\Resources\MIME;

class MIMETest extends TestCase
{
    public function testMIMEPropertyExists()
    {
        $reflection = new \ReflectionClass(MIME::class);
        $this->assertTrue($reflection->hasProperty('supportedTypes'));
    }

    public function testMIMEPropertyIsArray()
    {
        $reflection = new \ReflectionClass(MIME::class);
        $property = $reflection->getProperty('supportedTypes');
        $property->setAccessible(true);
        $value = $property->getValue(new MIME());
        $this->assertIsArray($value);
    }

    public function testMIMEPropertyIsNotEmpty()
    {
        $reflection = new \ReflectionClass(MIME::class);
        $property = $reflection->getProperty('supportedTypes');
        $property->setAccessible(true);
        $value = $property->getValue(new MIME());
        $this->assertNotEmpty($value);
    }

    public function testMIMEPropertyContainsValidMIMETypes()
    {
        $reflection = new \ReflectionClass(MIME::class);
        $property = $reflection->getProperty('supportedTypes');
        $property->setAccessible(true);
        $value = $property->getValue(new MIME());
        
        foreach ($value as $mimeType) {
            $this->assertRegExp('/^[\w\-\+\.]+\/[\w\-\+\.]+$/', $mimeType);
        }
    }

    // ---

    public function testHasSupportForJPEG() {
        $this->assertTrue(MIME::checkSupport('image/jpeg'));
    }

    public function testDoesNotHaveSupportForAVIF() {
        $this->assertFalse(MIME::checkSupport('image/avif'));
    }

    public function testGetTypeReturnsFalseForNonExistentFile() {
        $this->assertFalse(MIME::getType('non_existent_file.jpg'));
    }

    public function testGetTypeReturnsMIMETypeForExistingFile() {
        $path = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR. ".." . DIRECTORY_SEPARATOR .'qr.jpg';
        $result = MIME::getType($path);
        $this->assertNotFalse($result);
        $this->assertEquals($result, 'image/jpeg');
    }
}
