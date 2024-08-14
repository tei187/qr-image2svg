<?php

namespace tei187\QrImage2Svg;

use tei187\QrImage2Svg\Utilities\PathValidator;

/**
 * Represents the configuration settings for the QR code conversion process.
 *
 * This class provides methods to set and retrieve the various configuration parameters,
 * such as the number of steps, threshold value, color channel, input path, output directory,
 * and file name. It also includes validation logic to ensure the configuration parameters
 * are within the expected ranges.
 * 
 * @todo processor picker leading to factory
 */
class Configuration
{
    /**
     * The number of steps to use in the QR code conversion process, which must be at least 21.
     * 
     * Should be understood as the amount of square tiles in vertical or horizontal alignment.
     * Mathematically should correspond to the length of any timing sequence length added to 14 (7 x 2 - length of two corner markers).
     * 
     * @var int|null
     */
    private ?int $steps;
    
    /**
     * The threshold value to use in the QR code conversion process, which must be between 0 and 255.
     * 
     * @var int
     */
    private int $threshold;

    /**
     * The color channel to use in the QR code conversion process, which must be 'red', 'green', or 'blue'.
     * 
     * @var string|null
     */
    private string $channel;
    
    /**
     * The path to the input image file.
     *
     * @var string|null
     */
    private ?string $inputDir;

    /**
     * The directory to save the output SVG file.
     *
     * @var string|null
     */
    private ?string $outputDir;
    
    /**
     * The name of the output SVG file.
     *
     * @var string|null
     */
    private ?string $fileName;

    /**
     * File extension.
     *
     * @var string|null
     */
    private ?string $fileExtension;

    /**
     * Determines whether to use the "convert" prefix when processing images through ImageMagick command line prompt.
     *
     * @var bool
     */
    private bool $imUseConvertPrefix = true;

    /**
     * Determines whether to use the "magick" prefix when processing images through ImageMagick command line prompt.
     *
     * @var bool
     */
    private bool $imUseMagickPrefix = true;
    

    /**
     * Base name of the file, without extension... which does not make it the basename, but let's not mention that any further.
     *
     * @var string|null
     */
    private ?string $fileBase;

    /**
     * The minimum number of steps to use in the QR code conversion process.
     * 
     * @var int
     */
    public const MIN_STEPS = 21;

    /**
     * The maximum number of steps to use in the QR code conversion process.
     * 
     * This constant represents the maximum number of steps that can be used in the QR code conversion process.
     *
     * @var int
     */
    public const MAX_STEPS = 177;
    
    /**
     * The default threshold value to use in the QR code conversion process, which must be between 0 and 255.
     * 
     * @var int
     */
    public const DEFAULT_THRESHOLD = 127;
    
    /**
     * The default color channel to use in the QR code conversion process, which must be 'red', 'green', or 'blue'.
     * 
     * @var string
     */
    public const DEFAULT_CHANNEL = 'red';

    /**
     * Constructs a new Configuration instance with the specified parameters.
     *
     * @param string|null $inputDir The directory to input the image file from.
     * @param string|null $outputDir The directory to save the output SVG file.
     * @param string|null $fileName The name of the input image file.
     * @param int|null $steps The number of steps to use in the QR code conversion process, which must be at least 21.
     * @param int $threshold The threshold value to use in the QR code conversion process, which must be between 0 and 255.
     * @param string $channel The color channel to use in the QR code conversion process, which must be 'red', 'green', or 'blue'.
     */
    function __construct(
        ?string $inputDir = null, 
        ?string $outputDir = null, 
        ?string $fileName = null, 
        ?int $steps = null, 
        int $threshold = self::DEFAULT_THRESHOLD, 
        string $channel = self::DEFAULT_CHANNEL,
        bool $imUseMagickPrefix = true,
        bool $imUseConvertPrefix = true
    ) {
        $this->setSteps($steps);
        $this->setThreshold($threshold);
        $this->setChannel($channel);
        $this->setInputDir($inputDir);
        $this->setOutputDir($outputDir);
        $this->setFileName($fileName);
        $this->setImMagickPrefixUse($imUseMagickPrefix);
        $this->setImConvertPrefixUse($imUseConvertPrefix);
    }

    // parameters - steps

        /**
         * Gets the number of steps used in the QR code conversion process.
         * 
         * Steps are the amount of square tiles in vertical or horizontal alignment, otherwise
         * called modules.
         *
         * @return int|null The number of steps.
         */
        public function getSteps()
        {
            return $this->steps;
        }

        /**
         * Sets the number of steps (modules) used in the QR code conversion process.
         * 
         * Set it manually only if you know the exact number of tiles in rows or columns within the
         * QR code, ( VERSION * 4 + 17 ). Otherwise it is advised to launch main output process
         * with suggest tiles quantity option turned on.
         *
         * @param int|null $steps The number of steps to use, which must be at least 21.
         * @return $this The current Configuration instance for method chaining.
         * @throws \InvalidArgumentException If the number of steps is less than 21.
         * @throws \InvalidArgumentException If the number of steps is greater than 177.
         */
        public function setSteps(?int $steps = null): self
        {
            if (!is_null($steps) && $steps < self::MIN_STEPS) {
                throw new \InvalidArgumentException('Steps must be at least 21.');
            } elseif (!is_null($steps) && $steps > self::MAX_STEPS) {
                throw new \InvalidArgumentException('Steps must be at most 177.');
            }
            $this->steps = $steps;
            return $this;
        }

    // parameters - threshold

        /**
         * Gets the threshold value used in the QR code conversion process.
         *
         * @return int The threshold value, which must be between 0 and 255.
         */
        public function getThreshold(): int
        {
            return $this->threshold;
        }

        /**
         * Sets the threshold value used in the QR code conversion process.
         *
         * @param int $threshold The threshold value, which must be between 0 and 255.
         * @return $this The current Configuration instance for method chaining.
         * @throws \InvalidArgumentException If the threshold value is less than 0 or greater than 255.
         */
        public function setThreshold(int $threshold): self
        {
            if ($threshold < 0 || $threshold > 255) {
                throw new \InvalidArgumentException('Threshold must be between 0 and 255.');
            }
            $this->threshold = $threshold;
            return $this;
        }

    // parameters - channel

        /**
         * Gets the channel used in the QR code conversion process.
         *
         * @return string The channel, which must be one of 'red', 'green', or 'blue'.
         */
        public function getChannel(): string
        {
            return $this->channel;
        }

        /**
         * Sets the channel used in the QR code conversion process.
         *
         * @param string $channel The channel, which must be one of 'red', 'green', or 'blue'.
         * @return $this The current Configuration instance for method chaining.
         * @throws \InvalidArgumentException If the channel is not one of the valid values.
         */
        public function setChannel(string $channel): self
        {
            $validChannels = ['red', 'green', 'blue'];
            if (!in_array($channel, $validChannels)) {
                throw new \InvalidArgumentException('Invalid channel. Must be one of: ' . implode(', ', $validChannels));
            }
            $this->channel = $channel;
            return $this;
        }

    // parameters - input path

        /**
         * Gets the input directory path used in the QR code conversion process.
         *
         * @return string|null The input path, or null if not set.
         */
        public function getInputDir(): ?string
        {
            return $this->inputDir;
        }

        /**
         * Sets the input path used in the QR code conversion process.
         *
         * @param string|null $inputDir The input directory path, or null to clear the input path.
         * @return $this The current Configuration instance for method chaining.
         * @throws \InvalidArgumentException If the input path is invalid.
         */
        public function setInputDir(?string $inputDir): self
        {
            if ($inputDir !== null) {
                try {
                    $this->inputDir = PathValidator::validate($inputDir, true, false);
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException("Invalid input path: " . $e->getMessage());
                }
            } else {
                $this->inputDir = null;
            }
            return $this;
        }

    // parameters - output dir

        /**
         * Gets the output directory used in the QR code conversion process.
         *
         * @return string|null The output directory, or null if not set.
         * @throws \RuntimeException If the output directory is not set.
         */
        public function getOutputDir(): ?string
        {
            if ($this->outputDir === null) {
                throw new \RuntimeException("Output directory is not set.");
            }
            return $this->outputDir;
        }

        /**
         * Sets the output directory used in the QR code conversion process.
         *
         * @param string|null $outputDir The output directory, or null to clear the output directory.
         * @return $this The current Configuration instance for method chaining.
         * @throws \InvalidArgumentException If the output directory is invalid.
         */
        public function setOutputDir(?string $outputDir): self
        {
            if ($outputDir !== null) {
                try {
                    $this->outputDir = PathValidator::validate($outputDir, true, false);
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException("Invalid output directory: " . $e->getMessage());
                }
            } else {
                $this->outputDir = null;
            }
            return $this;
        }

    // parameters - file name

        /**
         * Gets the file name used in the QR code conversion process.
         *
         * @return string|null The file name, or null if not set.
         */
        public function getFileName(): ?string
        {
            return $this->fileName;
        }

        public function getFileExtension(): ?string 
        {
            return $this->fileExtension;
        }

        public function getFileBase(): ?string {
            return $this->fileBase;
        }

        /**
         * Sets the file name used in the QR code conversion process.
         *
         * @param string|null $fileName The file name, or null to clear the file name.
         * @return $this The current Configuration instance for method chaining.
         * @throws \InvalidArgumentException If the file name is invalid.
         */
        public function setFileName(?string $fileName): self
        {
            if ($fileName !== null) {
                $sanitizedFileName = PathValidator::sanitize($fileName);
                if (empty($sanitizedFileName) || $sanitizedFileName !== $fileName) {
                    throw new \InvalidArgumentException("Invalid file name.");
                }
                $this->fileName = $sanitizedFileName;
                $info = pathinfo($this->getFullInputPath());
                list($this->fileExtension, $this->fileBase) = [$info['extension'], $info['filename']];
            } else {
                $this->fileName = null;
                $this->fileExtension = null;
                $this->fileBase = null;
            }
            return $this;
        }

    // method - full path

        /**
         * Gets the full path of the file, which is the combination of the input path and the file name.
         *
         * @param bool $validate Whether file name and existence should be validated or not. False by default.
         * 
         * @return string|null The full path of the file, or null if the input path or file name is missing.
         * @throws \RuntimeException If the input path or file name is missing, preventing the full path from being generated.
         */
        public function getFullInputPath(bool $validate = false): ?string
        {
            if ($this->inputDir === null || $this->fileName === null) {
                throw new \RuntimeException("Unable to generate full path. Input path or file name is missing.");
            } else {
                $path = $this->inputDir . DIRECTORY_SEPARATOR . $this->fileName;
                if($validate) {
                    try {
                        PathValidator::validate($path);
                    } catch(\InvalidArgumentException $e) {
                        throw new \InvalidArgumentException("File path error. File does not exist or is not accessible.");
                    }
                }
            }
            return $path;
        }

        /**
         * Gets the full path of the file, which is the combination of the output path and the file name.
         *
         * @param string|null $suffix
         * @param bool $validate Whether file name and existence should be validated or not. False by default.
         * 
         * @return string|null The full path of the file, or null if the output path or file name is missing.
         * @throws \RuntimeException If the output path or file name is missing, preventing the full path from being generated.
         */
        public function getFullOutputPath(?string $suffix = null, bool $validate = false): ?string
        {
            if ($this->outputDir === null || $this->fileName === null) {
                throw new \RuntimeException("Unable to generate full path. Output path or file name is missing.");
            } else {
                $path = 
                    is_null($suffix)
                        ? $this->outputDir . DIRECTORY_SEPARATOR . $this->fileName
                        : $this->outputDir . DIRECTORY_SEPARATOR . $this->fileBase . "_" . $suffix . "." . $this->fileExtension;
                if($validate){
                    try {
                        PathValidator::validate($path);
                    } catch(\InvalidArgumentException $e) {
                        throw new \InvalidArgumentException("File path error. File does not exist or is not accessible: {$path}.");
                    }
                }
            }
            return $path;
        }
    
    // parameters - ImageMagick-specific (cmd prompt)

    
        /**
         * Sets whether the ImageMagick 'magick' prefix should be used.
         *
         * @param bool $use Whether the ImageMagick prefix should be used.
         * @return $this The current instance of the Configuration object, for method chaining.
         */
        public function setImMagickPrefixUse(bool $use): self {
            $this->imUseMagickPrefix = $use;
            return $this;
        }

        /**
         * Sets whether the ImageMagick 'convert' command prefix should be used.
         *
         * @param bool $use Whether the ImageMagick convert command prefix should be used.
         * @return $this For method chaining.
         */
        public function setImConvertPrefixUse(bool $use): self {
            $this->imUseConvertPrefix = $use;
            return $this;
        }

        /**
         * Gets whether the ImageMagick 'magick' prefix should be used.
         *
         * @return bool True if the ImageMagick prefix should be used, false otherwise.
         */
        public function getImMagickPrefixUse(): bool {
            return $this->imUseMagickPrefix;
        }

        /**
         * Gets whether the ImageMagick 'convert' command prefix should be used.
         *
         * @return bool True if the ImageMagick convert command prefix should be used, false otherwise.
         */
        public function getImConvertPrefixUse(): bool {
            return $this->imUseConvertPrefix;
        }
}