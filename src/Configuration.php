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
 */
class Configuration
{
    /**
     * The number of steps to use in the QR code conversion process, which must be at least 21.
     */
    private int $steps;
    
    /**
     * The threshold value to use in the QR code conversion process, which must be between 0 and 255.
     */
    private int $threshold;

    /**
     * The color channel to use in the QR code conversion process, which must be 'red', 'green', or 'blue'.
     */
    private string $channel;
    
    /**
     * The path to the input image file.
     *
     * @var ?string
     */
    private ?string $inputPath;

    /**
     * The directory to save the output SVG file.
     *
     * @var ?string
     */
    private ?string $outputDir;
    
    /**
     * The name of the output SVG file.
     *
     * @var ?string
     */
    private ?string $fileName;
    

    /**
     * The minimum number of steps to use in the QR code conversion process.
     */
    public const MIN_STEPS = 21;
    
    /**
     * The default threshold value to use in the QR code conversion process, which must be between 0 and 255.
     */
    public const DEFAULT_THRESHOLD = 127;
    
    /**
     * The default color channel to use in the QR code conversion process, which must be 'red', 'green', or 'blue'.
     */
    public const DEFAULT_CHANNEL = 'red';

    /**
     * Constructs a new Configuration instance with the specified parameters.
     *
     * @param string|null $inputPath The path to the input image file.
     * @param string|null $outputDir The directory to save the output SVG file.
     * @param string|null $fileName The name of the output SVG file.
     * @param int $steps The number of steps to use in the QR code conversion process, which must be at least 21.
     * @param int $threshold The threshold value to use in the QR code conversion process, which must be between 0 and 255.
     * @param string $channel The color channel to use in the QR code conversion process, which must be 'red', 'green', or 'blue'.
     */
    function __construct(
        ?string $inputPath = null, 
        ?string $outputDir = null, 
        ?string $fileName = null, 
        int $steps = self::MIN_STEPS, 
        int $threshold = self::DEFAULT_THRESHOLD, 
        string $channel = self::DEFAULT_CHANNEL
    ) {
        $this->setSteps($steps);
        $this->setThreshold($threshold);
        $this->setChannel($channel);
        $this->setFileName($fileName);
        $this->setInputPath($inputPath);
        $this->setOutputDir($outputDir);
    }

    // parameters - steps

        /**
         * Gets the number of steps used in the QR code conversion process.
         *
         * @return int The number of steps.
         */
        public function getSteps(): int
        {
            return $this->steps;
        }

        /**
         * Sets the number of steps used in the QR code conversion process.
         *
         * @param int $steps The number of steps to use, which must be at least 21.
         * @return $this The current Configuration instance for method chaining.
         * @throws \InvalidArgumentException If the number of steps is less than 21.
         */
        public function setSteps(int $steps): self
        {
            if ($steps < self::MIN_STEPS) {
                throw new \InvalidArgumentException('Steps must be at least 21.');
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
         * Gets the input path used in the QR code conversion process.
         *
         * @return string|null The input path, or null if not set.
         */
        public function getInputPath(): ?string
        {
            return $this->inputPath;
        }

        /**
         * Sets the input path used in the QR code conversion process.
         *
         * @param string|null $inputPath The input path, or null to clear the input path.
         * @return $this The current Configuration instance for method chaining.
         * @throws \InvalidArgumentException If the input path is invalid.
         */
        public function setInputPath(?string $inputPath): self
        {
            if ($inputPath !== null) {
                try {
                    $this->inputPath = PathValidator::validate($inputPath, true, true);
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException("Invalid input path: " . $e->getMessage());
                }
            } else {
                $this->inputPath = null;
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
            } else {
                $this->fileName = null;
            }
            return $this;
        }

    // method - full path

        /**
         * Gets the full path of the file, which is the combination of the input path and the file name.
         *
         * @return string|null The full path of the file, or null if the input path or file name is missing.
         * @throws \RuntimeException If the input path or file name is missing, preventing the full path from being generated.
         */
        public function getFullPath(): ?string
        {
            if ($this->inputPath === null || $this->outputDir === null || $this->fileName === null) {
                throw new \RuntimeException("Unable to generate full path. Input path or file name is missing.");
            }
            return $this->inputPath . DIRECTORY_SEPARATOR . $this->fileName;
        }
}