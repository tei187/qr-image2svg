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
    private static $maxPathLength;

    /**
     * Initializes the maximum path length based on the operating system.
     *
     * @return void
     */
    public static function init()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $objShell = new \COM("Shell.Application");
            $objFolder = $objShell->Namespace(0x14);
            self::$maxPathLength = $objFolder->MaxFileNameLength;
        } else {
            self::$maxPathLength = PHP_MAXPATHLEN;
        }
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
        $realPath = realpath($path);

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
}