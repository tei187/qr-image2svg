<?php

namespace tei187\QrImage2Svg;

use tei187\QrImage2Svg\Resources\MIME;

/**
 * The `Processor` class is an abstract base class that provides common functionality for converting images to SVG format.
 * 
 * The class manages various properties related to the image being converted, such as its dimensions, tile data, and filled tile matrix.
 * It also provides methods for optimizing the size of the image, setting the tiles data, and generating the SVG representation of the image.
 * 
 * Subclasses of `Processor` must implement the abstract methods `_setTilesValues()`, `_probeTilesForColor()`, `_retrieveImageSize()`, and 
 * `_rescaleImage()` to provide the specific implementation details for the conversion process.
 */
abstract class Processor {
    /**
     * Stores the last error that occurred, if any.
     *
     * This property is used to track any errors that may have occurred during the execution of the Processor class.
     */
    protected ?string $lastError = null;
    
    /**
     * Stores the configuration for this Processor instance.
     *
     * This property holds the Configuration object that was provided when constructing the Processor instance.
     * It is used to access the various configuration settings that control the behavior of the Processor.
     */
    protected Configuration $config;
    
    /**
     * Stores the dimensions of the image.
     *
     * This array holds the width and height of the image being processed by the Processor class.
     * The 'x' and 'y' keys represent the width and height, respectively. The 'obj' key is used to
     * store an optional object associated with the image, and the 'optimized' key indicates whether
     * the image has been optimized.
     */
    protected array $image = [
        'x' => null,
        'y' => null,
        'obj' => null,
        'optimized' => false,
    ];

    /**
     * Stores the tile data for the image.
     *
     * This array holds the data for each tile in the image being processed by the Processor class.
     * The data for each tile may include information such as its position, size, or other relevant
     * details required for the conversion process.
     */
    protected array $tilesData = [];
    
    /**
     * Stores a matrix of filled tiles for the image.
     *
     * This array keeps track of which tiles in the image have been filled during the conversion process.
     * The matrix is indexed by the tile's x and y coordinates, with each element indicating whether the
     * corresponding tile is filled (true) or not (false).
     */
    protected array $filledTileMatrix = [];
    
    /**
     * Stores the number of pixels per tile for the image.
     *
     * This property holds the calculated number of pixels per tile, which is used to optimize the size of the image
     * during the conversion process. The value is determined based on the image dimensions and the configured number
     * of steps for the conversion.
     */
    protected ?int $pixelsPerTile = null;
    
    /**
     * The minimum number of pixels per tile for the image conversion process.
     * 
     * This constant defines the minimum number of pixels that should be used for each tile
     * during the image conversion. The Processor class will ensure that the number of pixels
     * per tile is at least this value, by rescaling the image if necessary.
     */
    public const MIN_PIXELS_PER_TILE = 10;

    /**
     * Constructs a new Processor instance with the provided Configuration.
     *
     * @param Configuration $config The configuration to use for this Processor instance.
     */
    public function __construct(Configuration $config) {
        $this->config = $config;
    }

    /**
     * Checks if the file at the given path has a valid MIME type.
     *
     * @param string $path The path to the file to check.
     * @return bool True if the file has a valid MIME type, false otherwise.
     */
    protected static function checkMIME(string $path) : bool {
        return MIME::check($path) !== false;
    }

    /**
     * Sets the dimensions of the image.
     *
     * @param ?array $dimensions An optional array containing the width and height of the image.
     * @return bool True if the dimensions were successfully set, false otherwise.
     */
    protected function _setImageDimensions(?array $dimensions) : bool {
        if (is_array($dimensions) && count($dimensions) === 2) {
            [$this->image['x'], $this->image['y']] = array_map('intval', $dimensions);
            return true;
        }
        $this->image['x'] = $this->image['y'] = null;
        return false;
    }

    /**
     * Optimizes the size of the image based on the configured number of steps and the image dimensions.
     * 
     * This method calculates the number of pixels per tile, ensuring that it is at least the minimum
     * required pixels per tile. If the calculated pixels per tile is different from the original, the
     * image is rescaled to match the new tile size. The `optimized` flag is set to indicate that the
     * image has been optimized.
     */
    protected function _optimizeSizePerPixelsPerTile(): void
    {
        if (!is_null($this->image['x']) && !is_null($this->image['y'])) {
            // calculate pixels per tile
            $perTile = $this->image['x'] / $this->config->getSteps();
            $this->pixelsPerTile = (fn($pt) => fmod($pt, 1) !== 0 
                ? intval(round($pt, 0, PHP_ROUND_HALF_EVEN))
                : intval($pt))($perTile);

            // adjusts pixels per tile to be at least 10
            $this->pixelsPerTile = max($this->pixelsPerTile, self::MIN_PIXELS_PER_TILE);

            // rescale image if needed
            if ($perTile != $this->pixelsPerTile) {
                $this->_rescaleImage(
                    $this->config->getSteps() * $this->pixelsPerTile, 
                    $this->config->getSteps() * $this->pixelsPerTile
                );
            }

            // set optimized flag
            $this->image['optimized'] = true;
        } else {
            $this->image['optimized'] = false;
        }
    }

    /**
     * Sets the tiles data for the image.
     * 
     * This method populates the `$tilesData` array with information about each tile in the image, including
     * the coordinates where the tile should be rendered, the coordinates of the middle of the tile, and a
     * placeholder for the tile's values.
     */
    protected function _setTilesData() : void {
        $this->tilesData = [];
        for($y = 0; $y < $this->config->getSteps(); $y++) {
            for($x = 0; $x < $this->config->getSteps(); $x++) {
                $this->tilesData[] = [
                    'renderAt' => [
                        'x' => $x, 
                        'y' => $y
                    ],
                    'tileMiddle' => [
                        'x' => intval(floor(($x * $this->pixelsPerTile) + ($this->pixelsPerTile / 2))),
                        'y' => intval(floor(($y * $this->pixelsPerTile) + ($this->pixelsPerTile / 2))),
                    ],
                    'values' => null
                ];
            }
        }
    }

    /**
     * Generates an SVG representation of the image data stored in the `$filledTileMatrix` property.
     *
     * This method creates an SVG document with a size matching the number of steps configured in the `Configuration` object.
     * It then iterates through the `$filledTileMatrix` array and adds a `<rect>` element for each filled tile, using the tile coordinates to position the rectangle.
     * The generated SVG is written to a file in the configured output directory, and the SVG string is returned.
     *
     * @return string The generated SVG representation of the image data.
     * 
     * @todo Might be a good idea to use a package instead of building the string manually.
     */
    public function generateSVG() : string {
        $w = $this->config->getSteps();
        $h = $w;

        $svgStr = "<svg id='svg-drag' version=\"1.2\" baseProfile=\"full\" viewbox=\"0 0 $w $h\" style=\"shape-rendering: optimizespeed; shape-rendering: crispedges; min-width: ".($w*2)."px;\">\r\n";
        $svgStr .= "\t<g fill=\"#000000\">\r\n";
        foreach($this->filledTileMatrix as $tile) {
            $svgStr .= "\t\t<rect x=\"{$tile['x']}\" y=\"{$tile['y']}\" width=\"1\" height=\"1\" />\r\n";
        }
        $svgStr .= "\t</g>\r\n";
        $svgStr .= "</svg>";
    
        $path = $this->config->getOutputDir() !== null ? $this->config->getOutputDir() : null;
        file_put_contents($path . DIRECTORY_SEPARATOR . "output.svg", $svgStr);
        return $svgStr;
    }

    /**
     * Returns the version of the QR code from the column count.
     * 
     * @param int $x The column count.
     * @return int|null The version of the QR code, or null if the column count is not valid.
     */
    public static function getVersionFromColumnCount(int $x): ?int
    {
        $v = ($x - 17) / 4;
        return ($v % 1 === 0) ? (int)$v : null;
    }

    // CONFIGURATION handlers - start
    
        /**
         * Sets the configuration for the converter.
         *
         * @param Configuration $config The configuration object to use.
         * @return $this The current instance of the converter, for method chaining.
         */
        public function setConfiguration(Configuration $config): self
        {
            $this->config = $config;
            return $this;
        }

        /**
         * Returns the current configuration object used by the converter.
         *
         * @return Configuration The configuration object used by the converter.
         */
        public function getConfiguration(): Configuration {
            return $this->config;
        }

        /**
         * Returns the number of steps configured for the converter.
         *
         * @return int The number of steps configured for the converter.
         */
        public function getSteps(): int
        {
            return $this->config->getSteps();
        }

        /**
         * Returns the configured threshold value.
         *
         * @return int The configured threshold value.
         */
        public function getThreshold(): int
        {
            return $this->config->getThreshold();
        }

        /**
         * Returns the configured channel for the converter.
         *
         * @return string The configured channel for the converter.
         */
        public function getChannel(): string
        {
            return $this->config->getChannel();
        }

    // CONFIGURATION handlers - end
    
    // ---

    // ABSTRACT METHODS - start
        /**
         * Sets the values of the tiles used by the converter.
         *
         * This is an abstract method that must be implemented by concrete converter classes.
         * It is responsible for setting the values of the tiles that will be used during the
         * conversion process.
         */
        /**
         * Sets the values of the tiles used by the converter.
         *
         * This is an abstract method that must be implemented by concrete converter classes.
         * It is responsible for setting the values of the tiles that will be used during the
         * conversion process.
         */
        abstract protected function _setTilesValues();

        /**
         * Probes the tiles used by the converter for color information.
         *
         * This is an abstract method that must be implemented by concrete converter classes.
         * It is responsible for analyzing the tiles and extracting any relevant color data
         * that will be used during the conversion process.
         */
        abstract protected function _probeTilesForColor();

        /**
         * Retrieves the size of the image used by the converter.
         *
         * This is an abstract method that must be implemented by concrete converter classes.
         * It is responsible for determining the width and height of the image that will be
         * used during the conversion process.
         *
         * @return void
         */
        abstract protected function _retrieveImageSize();
        
        /**
         * Rescales the image used by the converter to the specified width and height.
         *
         * This is an abstract method that must be implemented by concrete converter classes.
         * It is responsible for resizing the image that will be used during the conversion
         * process to the specified dimensions.
         *
         * @param int $w The desired width of the image.
         * @param int $h The desired height of the image.
         */
        abstract protected function _rescaleImage(int $w, int $h);

    // ABSTRACT METHODS - end
}
