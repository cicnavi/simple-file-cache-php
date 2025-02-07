<?php

declare(strict_types=1);

namespace Cicnavi\SimpleFileCache\Services\Interfaces;

use Cicnavi\SimpleFileCache\Exceptions\CacheException;

interface FileSystemServiceInterface
{
    /**
     * Check if the directory exists.
     * @param string $dirPath
     * @return bool True if exists, else false.
     */
    public function dirExists(string $dirPath): bool;

    /**
     * @param string $dirPath
     * @param int $mode
     * @param bool $recursive
     * @return bool True for success, else false.
     * @throws CacheException If dir already exists
     */
    public function createDir(string $dirPath, int $mode = 0777, bool $recursive = true): bool;

    /**
     * @param string $dirPath
     * @return bool True if writable, else false.
     */
    public function isWritableDir(string $dirPath): bool;

    /**
     * @param string $path
     * @return bool True if exists, else false.
     */
    public function fileExists(string $path): bool;

    /**
     * @param string $filePath
     * @return bool True if writable, else false.
     */
    public function isWritableFile(string $filePath): bool;

    /**
     * @param string $filePath
     * @return bool True if readable, else false.
     */
    public function isReadableFile(string $filePath): bool;

    /**
     * @param string $filePath
     * @return bool
     * @throws CacheException If file already exists
     */
    public function createFile(string $filePath): bool;

    /**
     * @param string $filePath
     * @param string $data
     * @return bool True if success, else false.
     * @throws CacheException If file is not writable.
     */
    public function storeDataToFile(string $filePath, string $data): bool;

    /**
     * @param string $filePath
     * @return string Read data as string, or false on failure.
     * @throws CacheException If file does not exist or file could not be read.
     */
    public function getDataFromFile(string $filePath): string;

    /**
     * @param string $filePath
     * @return bool True on success, else false.
     * @throws CacheException If file does not exist.
     */
    public function deleteFile(string $filePath): bool;

    /**
     * Delete folder and all containing sub-folders and files.
     * @param string $dirPath
     * @return bool True on success, else false.
     */
    public function rmDirRecursive(string $dirPath): bool;
}
