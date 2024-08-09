<?php

namespace tei187\QrImage2Svg\Utilities;

/**
 * Class PathValidator
 * 
 * Validates and sanitizes file system paths.
 */
class PathValidator
{
    /**
     * @var int|null Maximum allowed path length
     */
    private static $maxPathLength = null;

    /**
     * @var string|null Path which will be used as-if root.
     */
    private static $rootDirectory = null;

    /**
     * Caches the results of the realpath() function to improve performance.
     * 
     * @var array<string, string>
     */
    private static $realpathCache = [];

    /**
     * The maximum number of entries to cache in the realpath() cache.
     */
    private static $maxCacheSize = 1000;

    /**
     * Determines whether caching of realpath() results is enabled.
     *
     * When enabled, the realpath() function calls are cached to improve performance.
     */
    private static $cachingEnabled = true;

    // METHODS

        /**
         * Sets the root directory for path validation.
         *
         * @param string $directory The root directory path
         * @throws \InvalidArgumentException If the directory is invalid
         */
        public static function setRootDirectory(string $directory): void
        {
            $realPath = self::getRealPath($directory);
            if ($realPath === false || !is_dir($realPath)) {
                throw new \InvalidArgumentException("Invalid root directory: $directory");
            }
            self::$rootDirectory = $realPath;
        }

        /**
         * Initializes the maximum path length based on the operating system.
         * 
         * For Windows, it uses the maximum path length reported by the Windows API. It will produce
         * the maximum length of a path on the current system only if the COM extension is available.
         *
         * @return void
         */
        public static function init_old(): void
        {
            if (PHP_OS_FAMILY === 'Windows') {
                try {
                    $objShell = new \COM("Shell.Application");
                    $objFolder = $objShell->Namespace(0x14);
                    self::$maxPathLength = $objFolder->MaxFileNameLength;
                } catch (\Exception $e) {
                    // Ignore COM errors and use the default PHP_MAXPATHLEN
                    self::$maxPathLength = PHP_MAXPATHLEN;
                }
            } else {
                self::$maxPathLength = PHP_MAXPATHLEN;
            }
        }

        /**
         * Initialize maximum path length based on server environment settings.
         *
         * @return void
         */
        public static function init(): void
        {
            self::$maxPathLength = PHP_MAXPATHLEN;
        }

        /**
         * Validates a given path.
         *
         * @param string $path The path to validate
         * @param bool $mustExist Whether the path must exist
         * @param bool $isFile Whether the path should be a file (true) or directory (false)
         * @return string The validated and sanitized path
         * @throws \InvalidArgumentException If the path is invalid or inaccessible
         */
        public static function validate(string $path, bool $mustExist = true, bool $isFile = true): string
        {
            if (self::$maxPathLength === null) {
                self::init();
            }

            $path = self::sanitize($path);
            $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            $realPath = self::getRealPath($path);

            if (self::$rootDirectory !== null) {
                if (strpos($realPath, self::$rootDirectory) !== 0) {
                    throw new \InvalidArgumentException("Path is outside the allowed root directory");
                }
            }

            if ($mustExist && $realPath === false) {
                throw new \InvalidArgumentException("Path does not exist or is not accessible: $path");
            }

            if (strlen($realPath) > self::$maxPathLength) {
                throw new \InvalidArgumentException("Path exceeds maximum length of " . self::$maxPathLength . " characters");
            }

            if ($isFile) {
                if (!is_file($realPath)) {
                    throw new \InvalidArgumentException("Path is not a file: $path");
                }
                if (!is_readable($realPath)) {
                    throw new \InvalidArgumentException("File is not readable: $path");
                }
            } else {
                if (!is_dir($realPath)) {
                    throw new \InvalidArgumentException("Path is not a directory: $path");
                }
                if (!is_writable($realPath)) {
                    throw new \InvalidArgumentException("Directory is not writable: $path");
                }
            }

            if (self::isSymlink($realPath)) {
                throw new \InvalidArgumentException("Symlinks are not allowed: $path");
            }

            return $realPath;
        }

        /**
         * Sanitizes a path by removing null bytes and parent directory references.
         *
         * @param string $path The path to sanitize
         * @return string The sanitized path
         */
        public static function sanitize(string $path): string
        {
            return str_replace(["\0", '../'], '', $path);
        }

        /**
         * Checks if a given path is a symlink.
         *
         * @param string $path The path to check
         * @return bool True if the path is a symlink, false otherwise
         */
        public static function isSymlink(string $path): bool
        {
            return is_link($path);
        }

    // CACHE HANDLING
        /**
         * Enables or disables the realpath caching mechanism.
         *
         * When caching is enabled, the realpath of a path is cached to improve performance.
         * When caching is disabled, the cache is cleared.
         *
         * @param bool $enabled Whether to enable or disable realpath caching.
         * @return void
         */
        public static function setRealpathCaching(bool $enabled): void
        {
            self::$cachingEnabled = $enabled;
            if (!$enabled) {
                self::$realpathCache = []; // Clear the cache when disabling
            }
        }

        /**
         * Manages the size of the realpath cache by removing the oldest entries if the cache 
         * size exceeds the maximum allowed size.
         * 
         * @return void
         */
        private static function manageCacheSize(): void
        {
            if (count(self::$realpathCache) > self::$maxCacheSize) {
                // Remove oldest entries
                $removeCount = count(self::$realpathCache) - self::$maxCacheSize;
                self::$realpathCache = array_slice(self::$realpathCache, $removeCount, null, true);
            }
        }

        /**
         * Gets the real path of the given path, caching the result for improved performance
         * if caching is enabled.
         *
         * If caching is enabled, the method will first check the cache for the real path. If
         * the real path is not in the cache, * it will call `realpath()` to get the real 
         * path and store it in the cache. The cache size is managed to avoid * excessive 
         * memory usage.
         *
         * @param string $path The path to get the real path for.
         * @return string The real path of the given path.
         */
        public static function getRealPath(string $path): string {
            if (self::$cachingEnabled && isset(self::$realpathCache[$path])) {
                return self::$realpathCache[$path];
            }
        
            $realPath = realpath($path);
            
            if (self::$cachingEnabled) {
                self::$realpathCache[$path] = $realPath;
                self::manageCacheSize();
            }
        
            return $realPath;
        }
}