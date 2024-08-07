<?php

namespace tei187\QrImage2Svg\Processors;

use GdImage;
use tei187\QrImage2Svg\Utilities\PathValidator;
use tei187\QrImage2Svg\Configuration;
use tei187\QrImage2Svg\Processor;

/**
 * The `GD` class is a concrete implementation of the `Converter` interface that uses the GD library to process image files.
 * 
 * The class provides methods for creating, rescaling, and saving image files, as well as for analyzing the image data to determine 
 * which tiles should be filled or blank in the final SVG output.
 * 
 * The class is responsible for handling various image file formats, including PNG, JPEG, GIF, and WebP, and for trimming the image 
 * borders if necessary.
 * 
 * The `output()` method generates the final SVG image based on the processed image data.
 */
class GD extends Processor {
    /**
     * Constructs a new instance of the `GD` class with the specified configuration and optional image trimming.
     *
     * @param Configuration $config The configuration object to use for this converter.
     * @param bool $trimImage Whether to trim the image borders before processing.
     */
    function __construct(Configuration $config, bool $trimImage = false) {
        $this->config = $config;

        if($this->config->getFullInputPath() !== null) {
            $this->_createImage();
            if($trimImage) {
                $this->_trimImage();
            }
            $this->_setImageDimensions($this->_retrieveImageSize($this->config->getFullInputPath()));
        }
    }

    /**
     * Creates an image object from the specified file path.
     *
     * @return resource|\GdImage|false The created image resource, or false on failure.
     */
    private function _createImage() {
        $img = self::_createFrom($this->config->getFullInputPath());
        $this->image['obj'] = $img;
        return $img;
    }

    /**
     * Creates an image object from the specified file path.
     *
     * @param string|null $path The file path of the image to create. If null, the full path from the configuration will be used.
     * @return resource|\GdImage|false The created image resource, or false on failure.
     * 
     * @todo probably would be better to use mime type checks?
     */
    static protected function _createFrom($path) {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        switch(strtolower($ext)) {
            case "png":   $img =  imagecreatefrompng($path); break;
            case "jpg":   $img = imagecreatefromjpeg($path); break;
            case "jpeg":  $img = imagecreatefromjpeg($path); break;
            case "gif":   $img =  imagecreatefromgif($path); break;
            case "webp":  $img = imagecreatefromwebp($path); break;
            default;      $img = false;
        }
        return $img;
    }

    /**
     * Sets the color values for each tile in the tilesData array.
     *
     * This method iterates through the tilesData array and retrieves the color values for the middle point of each tile.
     * The color values are stored in the 'values' key of each tile's data array.
     *
     * @return void
     */
    protected function _setTilesValues() : void {
        foreach($this->tilesData as $k => $values) {
            $this->tilesData[$k]['values'] = imagecolorsforindex(
                $this->image['obj'], 
                imagecolorat(
                    $this->image['obj'], 
                    $values['tileMiddle']['x'], 
                    $values['tileMiddle']['y']
                )
            );
        }
    }
    
    /**
     * Checks whether the tile should be filled or blank.
     *
     * This method iterates through the `tilesData` array and retrieves the color values for the middle point of each tile.
     * It then calculates the average of the red, green, and blue color values for each tile.
     * If the average is less than or equal to the configured threshold, the tile's `renderAt` coordinates are added to the `filledTileMatrix` array.
     * 
     * @return void
     */
    protected function _probeTilesForColor() : void {
        foreach($this->tilesData as $k => $tile) {
            $c = $tile['values'];
            $avg = round(($c['red'] + $c['green'] + $c['blue']) / 3, 0);
            if($avg <= $this->config->getThreshold()) {
                $this->filledTileMatrix[] = $tile['renderAt'];
            }
        }
    }

    /**
     * Retrieves the size of the image.
     *
     * This method uses the `getimagesize()` function to retrieve the width and height of the image specified by the `getFullPath()` method of the `$config` object.
     * If the image size is successfully retrieved, an array containing the width and height is returned. Otherwise, `null` is returned.
     *
     * @param string|null $path Path to image. If 'null' will pick the input file path from configuration.
     * @return array|null An array containing the width and height of the image, or `null` if the image size could not be retrieved.
     * @throws \RuntimeException if the image size could not be retrieved.
     */
    protected function _retrieveImageSize(?string $path = null) {
        $path = is_null($path) ? $this->config->getFullInputPath() : $path;

        $imageInfo = @getimagesize($path);
        if ($imageInfo === false) {
            throw new \RuntimeException("Failed to retrieve image size for file: " . $path);
        }
        return [$imageInfo[0], $imageInfo[1]];
    }
    
    /**
     * Rescales the image to the specified width and height.
     *
     * This method creates a new image with the specified dimensions and resamples the original image to fit the new dimensions. 
     * The resulting image is stored in the `$this->image['obj']` property.
     *
     * @param int $w The desired width of the rescaled image.
     * @param int $h The desired height of the rescaled image.
     * @param string|null $suffix In this processor it does absolutely nothing, friend...
     * @return false|resource|\GdImage The rescaled image, or false if the rescaling failed.
     */
    protected function _rescaleImage(int $w, int $h, ?string $suffix = null) {
        $i = is_null($this->image['obj']) || $this->image['obj'] === false 
            ? $this->_createImage() 
            : $this->image['obj'];
        
        if($i !== false) {
            $dst = imagecreatetruecolor($w, $h);
            imagecopyresampled(
                $dst, $i,
                0, 0, 
                0, 0, 
                $w, $h,
                $this->image['x'], $this->image['y']
            );
            $this->image['x'] = $w;
            $this->image['y'] = $h;
            $this->image['obj'] = $dst;
            
            return $this->image['obj'];
        }
        return false;            
    }

    /**
     * Saves the image to disk using the appropriate file format based on the input file extension.
     *
     * This method is responsible for writing the image data stored in `$this->image['obj']` to the file specified by `$this->config->getFullPath()`. 
     * It determines the appropriate image format to use based on the file extension and calls the corresponding GD image output function (e.g. 
     * `imagepng()`, `imagejpeg()`, `imagegif()`, `imagewebp()`).
     *
     * If the file extension is not recognized, the method will return `false`.
     *
     * @return void|false `false` if the file format is not supported, otherwise `void`.
     * 
     * @todo just as in some other place - consider mime type check
     */
    protected function _saveImage($suffix = null) {
        $ext = pathinfo($this->config->getFullInputPath(), PATHINFO_EXTENSION);
        switch(strtolower($ext)) {
            case "png":    imagepng($this->image['obj'], $this->config->getFullOutputPath($suffix ?? 'optimized')); break;
            case "jpg":   imagejpeg($this->image['obj'], $this->config->getFullOutputPath($suffix ?? 'optimized')); break;
            case "jpeg":  imagejpeg($this->image['obj'], $this->config->getFullOutputPath($suffix ?? 'optimized')); break;
            case "gif":    imagegif($this->image['obj'], $this->config->getFullOutputPath($suffix ?? 'optimized')); break;
            case "webp":  imagewebp($this->image['obj'], $this->config->getFullOutputPath($suffix ?? 'optimized')); break;
            default;      return false;
        }
    }

    /**
     * Trims the white border of the image stored in `$this->image['obj']` using the `imagecropauto()` function.
     *
     * This method applies a threshold of 200-255 RGB to determine the image's content area, and then crops the image to that area.
     * The resulting image is stored back in `$this->image['obj']`, and the `_saveImage()` method is called to save the trimmed image to disk.
     *
     * @return void
     */
    protected function _trimImage() {
        $dst = imagecropauto($this->image['obj'], IMG_CROP_THRESHOLD, .78, 16777215);
        if($dst !== false) {
            $this->image['obj'] = $dst;
            $this->_setImageDimensions( [ imagesx($dst), imagesy($dst) ] );
        }
    }

    /**
     * Trims the white border of the image stored in `$this->image['obj']` using the `imagecropauto()` function.
     *
     * This method applies a threshold of 200-255 RGB to determine the image's content area, and then crops the image to that area.
     * The resulting image is stored back in `$this->image['obj']`, and the `_saveImage()` method is called to save the trimmed image to disk.
     *
     * @param string $path Path to image.
     * @return resource|\GdImage|false `FALSE` if filetype unsupported. GD Image `resource` or `GdImage` object if correct.
     * @static
     */
    static function trimImage($path) {
        $img = self::_createFrom($path);
        if(!imageistruecolor($img)) {
            $dims = [
                'x' => imagesx($img),
                'y' => imagesy($img),
            ];
            $dst = imagecreatetruecolor($dims['x'], $dims['y']);
            imagecopy($dst, $img, 0, 0, 0, 0, $dims['x'], $dims['y']);
            $dst = imagecropauto($dst, IMG_CROP_THRESHOLD, .78, 16777215);
        } else {
            $dst = imagecropauto($img, IMG_CROP_THRESHOLD, .78, 16777215);
        }

        if($dst !== false) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            switch(strtolower($ext)) {
                case "png":  imagepng( $dst, $path); break;
                case "jpg":  imagejpeg($dst, $path); break;
                case "jpeg": imagejpeg($dst, $path); break;
                case "gif":  imagegif( $dst, $path); break;
                case "webp": imagewebp($dst, $path); break;
                default;      return false;
            }
        }
        return $dst;
    }

    /**
     * Applies a threshold to the image stored in `$this->image['obj']`, converting pixels above the threshold to white and pixels
     * below the threshold to black.
     *
     * This method iterates through each pixel in the image, calculates the average RGB value, and sets the pixel to white if the 
     * average is above the threshold, or black if the average is below the threshold.
     *
     * @param int $threshold The threshold value, between 0 and 255, to use for converting pixels to black or white.
     * @return resource|\GdImage|false `FALSE` if the threshold could not be applied, otherwise the modified image resource.
     */
    private function _applyThreshold($threshold) {
        $img = $this->image['obj'];

        $xS = imagesx($img);
        $yS = imagesy($img);

        for($x = 0; $x < $xS; $x++) {
            for($y = 0; $y < $yS; $y++) {
                $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                unset($c['alpha']);
                $avg = round(array_sum($c) / count($c), 0);
                if($avg > $threshold) {
                    imagesetpixel($img, $x, $y, imagecolorallocate($img, 255, 255, 255));
                } else {
                    imagesetpixel($img, $x, $y, imagecolorallocate($img, 0, 0, 0));
                }
            }
        }

        $img = imagecropauto($img, IMG_CROP_WHITE);
        return $img;
    }

    /**
     * Suggests the quantity of tiles for a QR code grid based on the image data.
     *
     * This method first creates the image if it hasn't been created yet, then applies a grayscale filter and a threshold to the image.
     * It then seeks the border of the corner marker in the image, counts the interruptions on the timing line, and calculates the suggested
     * tiles quantity based on the number of interruptions.
     * 
     * That's a kind of a thing my manager labels as ***"try not to understand that"***.
     *
     * @return false|int The suggested tiles quantity per axis, or false if the threshold could not be applied or the image is corrupt.
     * 
     * @todo requires some threshold filter controls
     */
    public function suggestTilesQuantity() {
        // preparation
        if(is_null($this->image['obj']))
            $this->_createImage($this->config->getFullInputPath());

        imagefilter($this->image['obj'], IMG_FILTER_GRAYSCALE);

        $img = $this->_applyThreshold(127);
        
        if($img === false) { return false; }

        $dims = [ 
            imagesx($img), 
            imagesy($img),
        ];
        $this->image['obj'] = $img;
        list($this->image['x'], $this->image['y']) = $dims;
        sort( $dims, SORT_ASC );
        
        if($dims[0] == 0) {
            return false;
        }

        $maxTileLength = ceil($dims[0] / 20); // smallest QR can have 21 tiles per axis (version 1). Dividing by 20 instead in order to have some margin for antialiasing.
        $maxMarkerLength = ($maxTileLength * 7) + 1; // marker is 7x7 tiles, so multiply length of minimal by 7 and add one pixel for change
        // its done this way so the script will stop iterating the for-loop after a certain point, meaning:
        // if the threshold limit is not found by then, the image is corrupt, not a standard QR or threshold parameter was not properly assigned

        // seeking marker edge
        $minimalTile = floor($dims[0] / 177); // minimal tile of a border
        $found = false;
        $i = 0;

        for($y = 0; $found == false; $y++) {
            if($y == $dims[0]) {
                break;
            }
            
            $p = $this->__seekBorderEnd($img, $y, $minimalTile, $maxMarkerLength);
            if($p[0]) {
                $i = $p[1];
                $found = true;
                break;
            }
        }
        
        if($i == 0 || !$found) {
            return false;
        } else {
            // count interruptions on timing line
            $j = $y + ceil($i - (($i / 7) / 2));
            //$j = ceil($i - (($i / 7) / 2)); // middle height of right-bottom corner of marker in top-left corner
            $k = $i; // x position outside the marker on right side border
            $interruptions = 0;
            $last = null;
            $current = null;

            for( $k; $k < $dims[0]; $k++) {
                $c = imagecolorsforindex($img, imagecolorat($img, $k, $j));
                $c['alpha'] = 0;
                $sum = array_sum($c);

                if(is_null($last) && is_null($current)) {
                    $last = ($sum / 3) < 127 ? null : $sum / 3; // prevents counting border-background on first rounds as an interruption
                    continue;
                }

                $current = $sum / 3;
                if($last !== $current) {
                    $interruptions++;
                }
                $last = $current;
            }

            if($last == 255) {
                $interruptions--; // prevent white untrimmed area on right side treated as interruption, last has to be filled with black
            }

            // validate marker length
            $check = [
                round($dims[0] / ($interruptions + 14)),
                round($i / 7)
            ];

            if($check[0] != $check[1]) {
                return false;
            }

            $result = ($interruptions + 14) > 177 
                ? 177 
                : $interruptions + 14;
            $result = $result < 21 
                ? 21 
                : $result;

            return $result;
        }
    }

    /**
     * Generates an SVG image based on the configuration and image data.
     *
     * This method is responsible for the final output of the generated image. It
     * creates the image, optimizes its size, sets the tiles data and values, probes
     * the tiles for color, and then generates the SVG representation of the image.
     *
     * @return false|string The generated SVG image, or false on failure.
     */
    public function output(bool $suggestTilesQty = true) {
        if($this->config->getFullInputPath() == null) 
            return false;
        $this->_createImage();

        if($suggestTilesQty) {
            $steps = $this->suggestTilesQuantity();
            $this->config->setSteps($steps);
        }

        if(!$this->image['optimized']) 
            $this->_optimizeSizePerPixelsPerTile();

        $this->_setTilesData();
        $this->_setTilesValues();
        $this->_probeTilesForColor();
        $this->tilesData = null;
        return $this->generateSVG();
    }

    /**
     * Seeks the end of the border for a corner marker in the image.
     *
     * This method scans the image horizontally from the left side, looking for the end
     * of the corner marker. It uses the pixel color to determine the start and end of
     * the marker, and returns the position of the end of the marker.
     *
     * @param resource|\GdImage $img Image resource to test.
     * @param integer $y Height of a row to parse.
     * @param integer $minimalTile Minimal tile length ~(width / 177).
     * @param integer $maxMarkerLength Max corner marker length.
     * @return array [bool,int] Returns an array with a boolean indicating if the marker was found, and the position of the end of the marker.
     */
    private function __seekBorderEnd($img, int $y, int $minimalTile, int $maxMarkerLength ) {
        $started = false;
        $found = false;
        $w = 0;
        $b = 0;

        for( $x = 0; $x <= $maxMarkerLength; $x++ ) {
            $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
            $c['alpha'] = 0;
            if(round(array_sum($c) / 3, 0) == 0 && !$started) {
                $started = true;
            }
            if(round(array_sum($c) / 3, 0) == 0) {
                $b++;
            }
            if(round(array_sum($c) / 3, 0) > 127) {
                $w++;
                if($started && $x >= $minimalTile * 7) {
                    if($w <= $b) {
                        $found = true;
                        unset($c);
                        break;
                    } else {
                        break;
                    }
                }
            }
        }

        return [ $found, $x ];
    }
}