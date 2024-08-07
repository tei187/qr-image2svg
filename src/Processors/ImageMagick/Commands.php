<?php

namespace tei187\QrImage2Svg\Processors\ImageMagick;

/**
 * Class Commands
 * 
 * This class provides a set of static methods for generating ImageMagick commands
 * to be used with PHP's exec() or similar functions.
 */
class Commands {
    
    /** @var bool Determines whether to use the 'magick' prefix in commands */
    private static $usePrefix = false;

    /**
     * Set whether to use the 'magick' prefix in commands
     *
     * @param bool $state True to use prefix, false otherwise
     * @return void
     */
    public static function setPrefix(bool $state = false): void {
        self::$usePrefix = $state;
    }

    /**
     * Resolve the command prefix based on the current setting
     *
     * @return string The resolved prefix ('magick ' or empty string)
     */
    public static function resolvePrefix(): string {
        return self::$usePrefix === true ? "magick " : "";
    }

    /**
     * Generate a command to get the image dimensions
     *
     * @param string $path Path to the image file
     * @return string The ImageMagick command
     */
    public static function imageSize(string $path): string {
        return
            self::resolvePrefix() .
            "identify -format \"%wx%h\" " .
            $path;
    }

    /**
     * Generate a command to get color information for multiple pixels (used in unison with ::colorAtPixel).
     *
     * @param string $path Path to the image file
     * @param string $part The pixel coordinates chain
     * @return string The ImageMagick command
     */
    public static function colorAtPixelsChain(string $path, string $part): string {
        return 
            self::resolvePrefix() .
            "identify -format \({$part}\) " .
            $path;
    }

    /**
     * Generate a format string for getting color at a specific pixel
     *
     * @param int $posX X-coordinate of the pixel
     * @param int $posY Y-coordinate of the pixel
     * @return string The format string for use in other commands
     */
    public static function colorAtPixel(int $posX, int $posY): string {
        return "%[pixel:s.p{" . $posX . "," . $posY . "}]";
    }

    /**
     * Generate a command to get the color type of an image
     *
     * @param string $path Path to the image file
     * @return string The ImageMagick command
     */
    public static function colorType(string $path): string {
        return
            self::resolvePrefix() .
            "identify -format %[pixel:s.p{1,1}] " .
            $path;
    }

    /**
     * Generate a command to rescale an image
     *
     * @param string $input Path to the input image
     * @param int $w New width
     * @param int $h New height
     * @param string|null $output Path to the output image (if null, overwrites input)
     * @return string The ImageMagick command
     */
    public static function rescaleImage(string $input, int $w, int $h, string $output = null): string {
        $output = $output === null ? $input : $output;

        return
            self::resolvePrefix() .
            "convert {$input} " . 
            "-interpolate Integer " . 
            "-filter point " . 
            "-resize {$w}x{$h} " . 
            "-colorspace RGB " . 
            $output;
    }

    /**
     * Generate a command to get trim threshold information
     *
     * @param string $path Path to the image file
     * @return string The ImageMagick command
     */
    public static function trimImageThreshold(string $path): string {
        return
            self::resolvePrefix() .
            "convert {$path} " .
            "-color-threshold \"RGB(200,200,200)-RGB(255,255,255)\" " .
            "-format \"%@\" " .
            "info:";
    }

    /**
     * Generate a command to crop an image based on given positions
     *
     * @param string $input Path to the input image
     * @param array $pos Array of crop positions [width, height, x, y]
     * @param string $output Path to the output image
     * @return string The ImageMagick command
     */
    public static function trimImageCrop(string $input, array $pos, string $output): string {
        return
            self::resolvePrefix() . 
            "convert {$input} " . 
            "-crop {$pos[0]}x{$pos[1]}+{$pos[2]}+{$pos[3]} " . 
            $output;
    }

    /**
     * Generate a command to suggest tiles threshold
     *
     * @param string $input Path to the input image
     * @param int $threshold Color threshold value
     * @param string $output Path to the output image
     * @return string The ImageMagick command
     */
    public static function suggestTilesThreshold(string $input, int $threshold, string $output): string {
        return
            self::resolvePrefix() . 
            "convert {$input} " .
            "-color-threshold \"RGB({$threshold},{$threshold},{$threshold})-RGB(255,255,255)\" " .
            "-trim " . 
            $output;
    }
}
