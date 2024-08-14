<?php

namespace tei187\QrImage2Svg\Processors;

use tei187\QrImage2Svg\Configuration;
use tei187\QrImage2Svg\Processor;
use tei187\QrImage2Svg\Processors\ImageMagick\Commands;
use tei187\QrImage2Svg\Utilities\PathValidator;

/**
 * The class provides methods for creating, rescaling, and saving image files, as well as for analyzing the image data to determine 
 * which tiles of the QR code should be filled or blank in the final SVG output.
 * 
 * The class is responsible for handling various image file formats (locale-based support), and for trimming the image 
 * borders if necessary.
 * 
 * The `output()` method generates the final SVG image based on the processed image data.
 */
class ImageMagick extends Processor {
    /**
     * Indicates whether the tile row count suggestion method was applied and ran correctly.
     *
     * @var bool
     */
    private $hasSuggested = false;

    private $optimizedImagePath = null;

    private $tempImagePath = null;

    private $trimmedImagePath = null;

    /**
     * Constructs an ImageMagick converter instance with the given configuration.
     *
     * @param Configuration $config The configuration to use for the converter.
     * @param bool $trimImage Whether to trim the image after conversion.
     */
    function __construct(Configuration $config, bool $trimImage = false) {
        $this->config = $config;

        Commands::setPrefix( $config->getImMagickPrefixUse() );
        Commands::setUseConvert( $config->getImConvertPrefixUse() );
        
        if($this->config->getFullInputPath() !== null) {
            if($trimImage) $this->_trimImage();
            $this->_setImageDimensions( $this->_retrieveImageSize($this->config->getFullInputPath()) ); // set image dimensions
        }
    }

    /**
     * Retrieves the size of the input image.
     *
     * @param string $path Path to image file.
     * @return array|null An array containing the width and height of the image, or null if the size could not be determined.
     */
    protected function _retrieveImageSize(string $path) {
        list( $w, $h ) = explode("x", shell_exec(Commands::imageSize($path)), 2);
        
        $w = (int) $w;
        $h = (int) $h;

        $output = 
            $w !== 0 && $h !== 0 
                ? [ $w, $h ] 
                : null;

        return $output;
    }

    /**
     * Set flag whether the `magick` command prefix should be used (environment specific).
     *
     * @param boolean $flag Whether to use the `magick` command prefix.
     * @return self The current instance of the ImageMagick converter.
     */
    public function _setPrefixUse(bool $flag = true) : self {
        Commands::setPrefix($flag);
        return $this;
    }

    /**
     * Sets the values for each tile in the image.
     *
     * This method generates the ImageMagick-specific syntax for each tile, chunks the syntax into smaller parts to avoid shell command length limits, 
     * executes the `identify` command to retrieve the pixel values for each tile, and maps the output to the `$tilesData` array.
     *
     * @return void
     */
    protected function _setTilesValues() : void {
        // generate IM-specific syntax for each tile
        $commandParts = [];
        foreach($this->tilesData as $k => $values) {
            $commandParts[] = Commands::colorAtPixel($values['tileMiddle']['x'], $values['tileMiddle']['y']);
        }
    
        // chunk IM-specific syntax array (by low value of 150, due to shell limit)
        $commandChunks = array_chunk($commandParts, 150);
        $tempFile = tempnam( PathValidator::getTempDirectory(), $this->config->getFileBase() . '_qr_colors_');
    
        foreach($commandChunks as $chunk) {
            $part = implode("..", $chunk);
            $command = Commands::colorAtPixelsChain($this->optimizedImagePath, $part);
            file_put_contents($tempFile, shell_exec($command) . "..", FILE_APPEND);
        }
    
        $output = file_get_contents($tempFile);
        unlink($tempFile);
    
        // mapping output
        $colorData = array_map(function($v) {
            if($v !== null && strlen(trim($v)) > 0) {
                preg_match_all("/\d+/", $v, $match);
                return $match[0];
            }
            return null;
        }, explode("..", trim($output, ".")));
    
        // save values
        foreach($colorData as $k => $v) {
            if ($v !== null) {
                $this->tilesData[$k]['values'] = $v;
            }
        }
    }

    /**
     * Probes the tiles of the image for color information and determines which tiles should be filled or blank based on a configured threshold.
     *
     * This method analyzes the color values of each tile in the image, determines the average color value, and compares it to the configured 
     * threshold. Tiles with an average color value below the threshold are marked as filled, while tiles above the threshold are marked as blank.
     *
     * The color type of the image is detected and the color values are interpreted accordingly (grayscale, RGB, or CMYK). The resulting filled 
     * tile matrix is stored in the `$filledTileMatrix` property.
     *
     * @return void
     */
    protected function _probeTilesForColor() {
        $colorType = $this->_getColorType();
        $passedAs = strpos($colorType, "rgb")  !== false ? "rgb"  : "undefined";
        $passedAs = strpos($colorType, "gray") !== false ? "gray" : $passedAs;
        $passedAs = strpos($colorType, "cmyk") !== false ? "cmyk" : $passedAs;
        
        foreach($this->tilesData as $k => $tile) {
            switch( $passedAs ) {
                case 'gray': // assume grayscale
                    $color = $tile['values'][0];
                    $avg = round($color, 1);
                    break;
                case 'rgb': // assume rgb
                    $color = [
                        'red'   => $tile['values'][0],
                        'green' => $tile['values'][1],
                        'blue'  => $tile['values'][2]
                    ];
                    $avg = round(array_sum($color) / 3, 1);
                    break;
                case 'cmyk':
                    $color = [
                        'cyan'    => $tile['values'][0],
                        'magenta' => $tile['values'][1],
                        'yellow'  => $tile['values'][2],
                        'black'   => $tile['values'][3],
                    ];
                    $colorRGB = [
                        'red'   => 255 * (1 - ($color['cyan'] / 100))    * (1 - ($color['black'] / 100)),
                        'green' => 255 * (1 - ($color['magenta'] / 100)) * (1 - ($color['black'] / 100)),
                        'blue'  => 255 * (1 - ($color['yellow'] / 100))  * (1 - ($color['black'] / 100))
                    ];
                    $avg = round(array_sum($colorRGB) / 3, 1);
                    break;
                default:
                    // some fail here...
                    $color = false;
                    $avg = 255;
            }

            $avg <= $this->config->getThreshold()
                ? $this->filledTileMatrix[] = $tile['renderAt']
                : null;
            
        }
    }

    /**
     * Queries the image for palette type.
     *
     * @return string The detected color type of the image, such as "rgb", "gray", or "cmyk".
     */
    protected function _getColorType() : string {
        return 
            explode( 
                "(", 
                shell_exec( Commands::colorType( $this->optimizedImagePath ) ) 
            )[0];
    }

    /**
     * Rescales currently handled image resource by assigned dimensions.
     * 
     * After rescaling is done, the method updates handled resource dimensions in it's attributes.
     *
     * @param integer $w Output width in pixels.
     * @param integer $h Output height in pixels;
     * @param string|null $suffix If the output file should should have a specific suffix (if not set, will default to 'temp').
     * @return void
     */
    protected function _rescaleImage(int $w, int $h, ?string $suffix = null) {
        $this->optimizedImagePath = tempnam( PathValidator::getTempDirectory(),  $this->config->getFileBase()."_optimized" );

        $input = $this->hasSuggested
            ? $this->tempImagePath
            : $this->config->getFullInputPath();
        
        shell_exec( Commands::rescaleImage( $input, $w, $h, $this->optimizedImagePath ) );
        $this->_setImageDimensions([$w, $h]);
    }

    /**
     * Trims white image border, based on a simulated 200-255 RGB threshold.
     *
     * @return boolean True if the image was successfully trimmed, false otherwise.
     */
    protected function _trimImage() : bool {
        $this->trimmedImagePath = tempnam( PathValidator::getTempDirectory(),  $this->config->getFileBase()."_trim" );

        $g = shell_exec( Commands::trimImageThreshold($this->config->getFullInputPath()) );
        $c = explode("+", $g);
        $c = array_merge( 
            explode("x", $c[0]),
            array($c[1], $c[2]) 
        );
        if(count($c) == 4 && ($c[0] != 0 && $c[1] != 0)) {
            shell_exec(
                Commands::trimImageCrop(
                    $this->config->getFullInputPath(), 
                    $c, 
                    $this->trimmedImagePath));

            $this->_setImageDimensions( $this->_retrieveImageSize( $this->trimmedImagePath ) );
            return true;
        }
        return false;
    }

    /**
     * Suggests the quantity of tiles to use for the image based on its dimensions and other factors.
     *
     * This method first sets a threshold image by converting the input image to a black and white image using the configured threshold value. 
     * It then retrieves the dimensions of the threshold image and calculates the maximum tile length and marker length based on these dimensions.
     *
     * The method then searches for the corner of the image by probing each row of the threshold image and looking for the end of the border. 
     * Once the corner is found, it calculates the number of tiles to use based on the distance between the corner and the edge of the image.
     *
     * The method returns the suggested number of tiles, which will be between 21 and 177 tiles. If the method is unable to determine the 
     * number of tiles, it returns `false`.
     *
     * @return false|int The suggested number of tiles, or `false` if the number of tiles could not be determined.
     */
    public function suggestTilesQuantity() {
        $this->tempImagePath = tempnam( PathValidator::getTempDirectory(),  $this->config->getFileBase()."_temp" );
        
        // set threshold image
        shell_exec(
            Commands::suggestTilesThreshold(
                $this->config->getFullInputPath(), 
                $this->config->getThreshold(), 
                $this->tempImagePath));

        // get dimensions
        $dims = $this->_retrieveImageSize($this->tempImagePath);
        $dims = is_array($dims) 
            ? $dims 
            : [ 0, 0 ];
        sort( $dims, SORT_ASC );
        
        if($dims[0] == 0) {
            $this->hasSuggested = false;
            return false;
        }
        
        $maxTileLength = ceil($dims[0] / 20);
        $maxMarkerLength = ($maxTileLength * 7) + 1;
        $minimalTile = floor($dims[0] / 177);

        // find corner
        $found = false;
        $f = 0;
        for($y = 0; $found == false; $y++) {
            if($y == $dims[0]) {
                break;
            }

            $data = $this->__probeImageRow($this->tempImagePath, $y, $maxMarkerLength);
            $border = $this->__seekBorderEnd($data, $minimalTile);
            if($border[0]) {
                $f = $border[1];
                $found = true;
                break;
            }
        }
        
        if($f == 0 || !$found) {
            $this->hasSuggested = false;
            return false;
        } else {
            $j = abs($y - ceil($f - (($f / 7) / 2)));
            $k = $f;
            $points = [];

            for($k; $k < $dims[0]; $k++) { 
                $points[] = Commands::colorAtPixel($k, $j); 
            }

            $pointsChunks = array_chunk($points, 100); unset($points); $output = "";
            foreach($pointsChunks as $chunk) {
                $part = implode("..", $chunk);
                $output .= shell_exec( Commands::colorAtPixelsChain($this->tempImagePath, $part) ) . "..";
            }
            unset($pointsChunks, $chunk);
            $temp = array_map(
                function($v) {
                    if($v !== null && strlen(trim($v)) > 0) {
                        preg_match_all("/\d+/", $v, $match);
                        return $match[0];
                    }
                }, explode("..", trim($output, "."))
            );
            unset($output, $match);

            $interruptions = 0;
            $last = null;
            $current = null;
            foreach($temp as $k => $v) {
                if(is_null($last) && is_null($current)) {
                    $last = $v[0] < 127 ? null : $v[0];
                    continue;
                }

                $current = $v[0];
                if($last !== $current) {
                    $interruptions++;
                }
                $last = $current;
            }
            
            if($last > 127) {
                $interruptions--;
            }
            
            $check = [
                (int) round($dims[0] / ($interruptions + 14)),
                (int) round($f / 7)
            ];
            
            if($check[0] != $check[1]) {
                $this->hasSuggested = false;
                return false;
            }
            
            $result = ($interruptions + 14) > 177 
                ? 177 
                : $interruptions + 14;
            $result = $result < 21 
                ? 21 
                : $result;

            $this->hasSuggested = true;
            
            return $result;
        }
        // unlink temp file
    }

    /**
     * Generates the SVG image output based on the processed image data.
     *
     * This method is responsible for the final step of the image processing pipeline.
     * It takes the optimized and processed image data, and generates the SVG image
     * that can be used for further processing or output.
     *
     * @param bool $suggestTilesQty Flag saying whether or not the script should automatically find correct number of column rows. Should be left to default 'true' unless the tiles qty is known and updated within `$this->config`.
     * @return bool|string False on failure, or the generated SVG image content on success.
     */
    public function output(bool $suggestTilesQty = true) {
        if($this->config->getFullInputPath() == null) 
            return false;

        if($suggestTilesQty) {
            $steps = $this->suggestTilesQuantity();
            $this->config->setSteps($steps);
        }

        if(!$this->image['optimized']) 
            $this->_optimizeSizePerPixelsPerTile();

        $this->_setTilesData();
        $this->_setTilesValues();
        $this->_probeTilesForColor();
        $this->tilesData = [];
        $this->_removeTempFiles();
        $result = $this->generateSVG();
        return $result;    
    }

    /**
     * Finds the end of the border length of a corner marker.
     *
     * This method takes an array of pixel data for a specific row and a minimal tile length,
     * and determines the end of the border length of a corner marker. It returns a boolean
     * indicating whether the border was found, and the x-coordinate of the end of the border.
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

        foreach($data as $x => $v) {
            if($v[0] == 0 && !$started)
                $started = true;
            if($v[0] == 0)
                $b++;
            if($v[0] > 127) {
                $w++;
                if($started && $x >= $minimalTile * 7) {
                    if($w <= $b) {
                        $found = true;
                        break;
                    } else {
                        break;
                    }
                }
            }
        }

        return [ $found, $x ];
    }

    /**
     * Returns specific row's pixels data.
     *
     * This method takes the path to an image file after threshold application, the height of a row to parse, and the maximum
     * corner marker length. It then extracts the pixel data for the specified row and returns it as an array.
     *
     * @param string $path Path to image file after threshold application.
     * @param int $y Height of a row to parse.
     * @param int $maxMarkerLength Max corner marker length.
     * @return array An array of pixel data for the specified row.
     */
    private function __probeImageRow(string $path, int $y, int $maxMarkerLength) {
        // - list cmd parts
        $points = [];
        for($x = 0; $x <= $maxMarkerLength; $x++) {
            $points[] = Commands::colorAtPixel($x, $y);
        }
        $pointsChunks = array_chunk($points, 100);
        unset($points);

        // - chunk to limit
        $output = "";
        foreach($pointsChunks as $chunk) {
            $part = implode("..", $chunk);
            $output .= shell_exec( Commands::colorAtPixelsChain($path, $part) ) . "..";
        }
        unset($pointsChunks, $chunk);

        // - parse output
        $row = array_map(
            function($v) {
                if($v !== null && strlen(trim($v)) > 0) {
                    preg_match_all("/\d+/", $v, $match);
                    return $match[0];
                }
            }, explode("..", trim($output, "."))
        );
        unset($output, $match);

        return $row;
    }

    private function _removeTempFiles() {
        foreach([
            $this->tempImagePath,
            $this->optimizedImagePath,
            $this->trimmedImagePath
        ] as $file) {
            if($file !== null && file_exists($file)) {
                unlink($file);
            }
        }
    }
}