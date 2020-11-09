<?php

declare(strict_types=1);

namespace Cicnavi\SimpleFileCache\Services;

use Cicnavi\SimpleFileCache\Exceptions\CacheException;
use Cicnavi\SimpleFileCache\Services\Interfaces\FileSystemServiceInterface;

class FileSystemService implements FileSystemServiceInterface
{
    /**
     * @inheritDoc
     */
    public function dirExists(string $dirPath): bool
    {
        return is_dir($dirPath);
    }

    /**
     * @inheritDoc
     */
    public function createDir(string $dirPath, $mode = 0755, $recursive = true): bool
    {
        if ($this->dirExists($dirPath)) {
            throw new CacheException(sprintf('Error creating dir. Dir exists (%s)', $dirPath));
        }

        return mkdir($dirPath, $mode, $recursive);
    }

    /**
     * @inheritDoc
     */
    public function isWritableDir(string $dirPath): bool
    {
        if ((! $this->dirExists($dirPath)) || (! is_writable($dirPath))) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function fileExists(string $path): bool
    {
        return file_exists($path) && is_file($path);
    }

    /**
     * @inheritDoc
     */
    public function isWritableFile(string $filePath): bool
    {
        if ((! $this->fileExists($filePath)) || (! is_writable($filePath))) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function isReadableFile(string $filePath): bool
    {
        if ((! $this->fileExists($filePath)) || (! is_readable($filePath))) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function createFile(string $filePath): bool
    {
        if ($this->fileExists($filePath)) {
            throw new CacheException(sprintf('Error creating a file. File exists (%s)', $filePath));
        }

        return touch($filePath);
    }

    /**
     * @inheritDoc
     */
    public function storeDataToFile(string $filePath, string $data): bool
    {
        if (! $this->isWritableFile($filePath)) {
            throw new CacheException(sprintf('Error saving data. File not writable (%s)', $filePath));
        }

        return (bool) file_put_contents($filePath, $data);
    }

    /**
     * @inheritDoc
     */
    public function getDataFromFile(string $filePath, string $fileGetContentsFunc = 'file_get_contents')
    {
        if (! is_callable($fileGetContentsFunc)) {
            throw new CacheException('Provided file get contents function is not callable.');
        }

        if (! $this->fileExists($filePath)) {
            throw new CacheException(sprintf('Error getting data. File does not exists (%s)', $filePath));
        }

        $data = call_user_func($fileGetContentsFunc, $filePath);

        if ($data === false) {
            throw new CacheException(sprintf('Error reading data from file (%s)', $filePath));
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function deleteFile(string $filePath): bool
    {
        if (! $this->fileExists($filePath)) {
            throw new CacheException(sprintf('Error deleting %s. File does not exists.', $filePath));
        }

        return unlink($filePath);
    }

    /**
     * @inheritDoc
     */
    public function rmDirRecursive(string $dirPath): bool
    {
        foreach (scandir($dirPath) as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }
            if (is_dir("$dirPath/$file")) {
                $this->rmDirRecursive("$dirPath/$file");
            } else {
                unlink("$dirPath/$file");
            }
        }

        return rmdir($dirPath);
    }
}
