<?php

namespace tei187\QrImage2Svg\Processors;

use tei187\QrImage2Svg\Configuration;
use tei187\QrImage2Svg\Processor;

/**
 * The class provides methods for creating, rescaling, and saving image files, as well as for analyzing the image data to determine 
 * which tiles of the QR code should be filled or blank in the final SVG output using the Imagick extension.
 */
class Imagick extends Processor {
    /**
     * Indicates whether the tile row count suggestion method was applied and ran correctly.
     *
     * @var bool
     */
    private $hasSuggested = false;

    /**
     * Constructs an Imagick converter instance with the given configuration.
     *
     * @param Configuration $config The configuration to use for the converter.
     * @param bool $trimImage Whether to trim the image after conversion.
     */
    function __construct(Configuration $config, bool $trimImage = false) {
        $this->config = $config;
        
        if($this->config->getFullInputPath() !== null) {
            $this->image['obj'] = new \Imagick($this->config->getFullInputPath());
            if($trimImage) $this->_trimImage();
            $this->_setImageDimensions([$this->image['obj']->getImageWidth(), $this->image['obj']->getImageHeight()]);
        }
    }

    /**
     * Retrieves the size of the input image.
     *
     * @param string $path Path to image file.
     * @return array|null An array containing the width and height of the image, or null if the size could not be determined.
     */
    protected function _retrieveImageSize(?string $image = null) {
        if($image === null) {
            $image = $this->image['obj'];
        } else {
            $image = new \Imagick($image);
        }
        $w = $this->image['obj']->getImageWidth();
        $h = $this->image['obj']->getImageHeight();
        
        return ($w !== 0 && $h !== 0) ? [$w, $h] : null;
    }

    /**
     * Sets the values for each tile in the image.
     *
     * @return void
     */
    protected function _setTilesValues() : void {
        foreach($this->tilesData as $k => $values) {
            $pixel = $this->image['obj']->getImagePixelColor($values['tileMiddle']['x'], $values['tileMiddle']['y']);
            $this->tilesData[$k]['values'] = $pixel->getColor();
        }
    }

    /**
     * Probes the tiles of the image for color information and determines which tiles should be filled or blank based on a configured threshold.
     *
     * @return void
     */
    protected function _probeTilesForColor() {
        foreach($this->tilesData as $k => $tile) {
            $color = $tile['values'];
            $avg = ($color['r'] + $color['g'] + $color['b']) / 3;

            if ($avg <= $this->config->getThreshold()) {
                $this->filledTileMatrix[] = $tile['renderAt'];
            }
        }
    }

    /**
     * Queries the image for color type.
     *
     * @return string The detected color type of the image, such as "rgb", "gray", or "cmyk".
     */
    protected function _getColorType() : string {
        return $this->image['obj']->getImageColorspace();
    }

    /**
     * Rescales currently handled image resource by assigned dimensions.
     *
     * @param integer $w Output width in pixels.
     * @param integer $h Output height in pixels;
     * @param string|null $suffix If the output file should should have a specific suffix (if not set, will default to 'temp').
     * @return void
     */
    protected function _rescaleImage(int $w, int $h, ?string $suffix = null) {
        $this->image['obj']->resizeImage($w, $h, \Imagick::FILTER_LANCZOS, 1);
        
        $path = is_null($suffix)
            ? $this->config->getFullOutputPath('temp')
            : $this->config->getFullOutputPath($suffix);
        
        $this->_setImageDimensions([$w, $h]);
    }

    /**
     * Trims white image border, based on a simulated 200-255 RGB threshold.
     *
     * @return boolean True if the image was successfully trimmed, false otherwise.
     */
    protected function _trimImage() : bool {
        $this->image['obj']->trimImage(0);
        $newWidth = $this->image['obj']->getImageWidth();
        $newHeight = $this->image['obj']->getImageHeight();
        
        if ($newWidth != 0 && $newHeight != 0) {
            $this->_setImageDimensions([$newWidth, $newHeight]);
            $this->image['obj']->writeImage($this->config->getFullOutputPath('trimmed'));
            return true;
        }
        return false;
    }

    /**
     * Suggests the quantity of tiles to use for the image based on its dimensions and other factors.
     *
     * @return false|int The suggested number of tiles, or `false` if the number of tiles could not be determined.
     */
    public function suggestTilesQuantity() {
        $tempImage = clone $this->image['obj'];
        $tempImage->thresholdImage($this->config->getThreshold());
        
        $tempImage->trimImage(0);
        //$tempImage->writeImage('test.jpg');
    
        $dims = [$tempImage->getImageWidth(), $tempImage->getImageHeight()];
        sort($dims, SORT_ASC);
        
        if ($dims[0] == 0) {
            $this->hasSuggested = false;
            return false;
        }
        
        $maxTileLength = ceil($dims[0] / 20);
        $maxMarkerLength = ($maxTileLength * 7) + 1;
        $minimalTile = floor($dims[0] / 177);

        // Find corner
        $found = false;
        $f = 0;
        for ($y = 0; $y < $dims[0]; $y++) {
            $row = $this->__probeImageRow($tempImage, $y, $maxMarkerLength);
            $border = $this->__seekBorderEnd($row, $minimalTile);
            if ($border[0]) {
                $f = $border[1];
                $found = true;
                break;
            }
        }

        if ($f == 0 || !$found) {
            $this->hasSuggested = false;
            return false;
        }

        $j = abs($y - ceil($f - (($f / 7) / 2)));
        $interruptions = 0;
        $last = null;

        for ($k = $f; $k < $dims[0]; $k++) {
            $pixel = $tempImage->getImagePixelColor($k, $j);
            $current = $pixel->getColor()['r'];
            
            if ($last !== null && $last !== $current) {
                $interruptions++;
            }
            $last = $current;
        }

        if ($last > 127) {
            $interruptions--;
        }

        $check = [
            (int) round($dims[0] / ($interruptions + 14)),
            (int) round($f / 7)
        ];

        if ($check[0] != $check[1]) {
            $this->hasSuggested = false;
            return false;
        }

        $result = min(max($interruptions + 14, 21), 177);
        $this->image['obj'] = $tempImage;
        $this->hasSuggested = true;
        
        return $result;
    }

    /**
     * Generates the SVG image output based on the processed image data.
     *
     * @param bool $suggestTilesQty Flag saying whether or not the script should automatically find correct number of column rows.
     * @return bool|string False on failure, or the generated SVG image content on success.
     */
    public function output(bool $suggestTilesQty = true) {
        if ($this->config->getFullInputPath() == null) 
            return false;

        if ($suggestTilesQty) {
            $steps = $this->suggestTilesQuantity();
            $this->config->setSteps($steps);
        }

        if (!$this->image['optimized']) 
            $this->_optimizeSizePerPixelsPerTile();
    
        $this->_setTilesData();
        $this->_setTilesValues();
        
        $this->_probeTilesForColor();
        $this->tilesData = [];
        return $this->generateSVG();
    }

    /**
     * Returns specific row's pixels data.
     *
     * @param \Imagick $image Imagick object after threshold application.
     * @param int $y Height of a row to parse.
     * @param int $maxMarkerLength Max corner marker length.
     * @return array An array of pixel data for the specified row.
     */
    private function __probeImageRow(\Imagick $image, int $y, int $maxMarkerLength) {
        $row = [];
        for ($x = 0; $x <= $maxMarkerLength; $x++) {
            $pixel = $image->getImagePixelColor($x, $y);
            $row[] = $pixel->getColor();
        }
        return $row;
    }

    /**
     * Finds the end of the border length of a corner marker.
     *
     * @param array $data Pixel data for a specific row.
     * @param int $minimalTile Minimal tile length (approximately width / 177).
     * @return array [bool, int] Whether the border was found, and the x-coordinate of the end of the border.
     */
    private function __seekBorderEnd(array $data, int $minimalTile) {
        $started = false;
        $found = false;
        $w = 0;
        $b = 0;

        foreach ($data as $x => $v) {
            if ($v['r'] == 0 && !$started)
                $started = true;
            if ($v['r'] == 0)
                $b++;
            if ($v['r'] > 127) {
                $w++;
                if ($started && $x >= $minimalTile * 7) {
                    if ($w <= $b) {
                        $found = true;
                        break;
                    } else {
                        break;
                    }
                }
            }
        }

        return [$found, $x];
    }
}
