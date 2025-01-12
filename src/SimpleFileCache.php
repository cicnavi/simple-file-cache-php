<?php

declare(strict_types=1);

namespace Cicnavi\SimpleFileCache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Cicnavi\SimpleFileCache\Exceptions\InvalidArgumentException;
use Cicnavi\SimpleFileCache\Exceptions\CacheException;
use Cicnavi\SimpleFileCache\Services\FileSystemService;
use Cicnavi\SimpleFileCache\Services\Interfaces\FileSystemServiceInterface;
use Throwable;

class SimpleFileCache implements CacheInterface
{
    /**
     * @var string $storagePath Path which can be used to create a cache file.
     */
    protected string $storagePath;

    /**
     * @var string $cacheName Name of the file which will hold cached data.
     */
    protected string $cacheName;

    /**
     * @var string
     */
    protected string $fileExtension = '.json';

    /**
     * SimpleFileCache constructor.
     * @param string $cacheName Cache name, may contain up to 64 chars: a-zA-Z0-9_-
     * @param string|null $storagePath Path to writable folder used to store the cache files
     * @param FileSystemServiceInterface|null $fileSystemService
     * @throws CacheException
     */
    public function __construct(
        string $cacheName = 'simple-file-cache',
        ?string $storagePath = null,
        protected FileSystemServiceInterface $fileSystemService = new FileSystemService()
    ) {
        $this->validateCacheName($cacheName);
        $this->cacheName = $cacheName;

        $storagePath ??= sys_get_temp_dir();
        // Make sure the path doesn't end with directory separator.
        $storagePath = rtrim($storagePath, DIRECTORY_SEPARATOR);

        $this->validateStoragePath($storagePath);
        $this->storagePath = $storagePath;

        $this->ensureCachePathExistence();
    }

    /**
     * @param string $key
     * @throws InvalidArgumentException
     */
    public function validateCacheKey(string $key): void
    {
        if (! preg_match('/^[a-zA-Z0-9_.]{1,64}$/', $key)) {
            throw new InvalidArgumentException('Cache key is not valid.');
        }
    }

    /**
     * @param string $cacheName
     * @throws InvalidArgumentException
     */
    public function validateCacheName(string $cacheName): void
    {
        if (! preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $cacheName)) {
            throw new InvalidArgumentException('Cache name is not valid.');
        }
    }

    /**
     * @param string $storagePath
     * @throws CacheException If storage path is not writable.
     */
    protected function validateStoragePath(string $storagePath): void
    {
        if (! $this->fileSystemService->isWritableDir($storagePath)) {
            throw new CacheException('Provided cache storage path is not writable.');
        }
    }

    /**
     * @param mixed $value If value is not iterable.
     * @throws InvalidArgumentException
     */
    protected function validateIterable($value): void
    {
        if (! is_iterable($value)) {
            throw new InvalidArgumentException('Value in not iterable.');
        }
    }

    /**
     * @throws CacheException If cache path does not exist or could not be created.
     */
    protected function ensureCachePathExistence(): void
    {
        try {
            if (! $this->fileSystemService->dirExists($this->getCachePath())) {
                $this->fileSystemService->createDir($this->getCachePath());
            }
        } catch (Throwable $exception) {
            throw new CacheException($exception->getMessage());
        }
    }

    /**
     * @param string $filePath
     * @param array $data
     * @return bool True if data was saved, else false.
     * @throws CacheException If file system service throws.
     */
    protected function persistData(string $filePath, array $data): bool
    {
        try {
            return (bool) $this->fileSystemService->storeDataToFile($filePath, json_encode($data));
        } catch (Throwable $exception) {
            throw new CacheException($exception->getMessage());
        }
    }

    /**
     * @return string Full path to cache folder.
     */
    public function getCachePath(): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . $this->getCacheName();
    }

    /**
     * @return string
     */
    public function getCacheName(): string
    {
        return $this->cacheName;
    }

    /**
     * @param string $filePath
     * @return array CacheItem array.
     * @throws CacheException
     */
    protected function readCacheItemArray(string $filePath): array
    {
        try {
            return json_decode($this->fileSystemService->getDataFromFile($filePath), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new CacheException('Error reading cache item array. ' . $exception->getMessage());
        }
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws CacheException If item could not be read
     * @throws InvalidArgumentException If the $key string is not a legal value
     *
     * @noinspection PhpMissingParamTypeInspection because of interface implementation
     */
    public function has($key)
    {
        $cacheItemFilePath = $this->resolveCacheItemFilePath($key);

        if (! $this->fileSystemService->isReadableFile($cacheItemFilePath)) {
            return false;
        }

        $item = $this->readCacheItemArray($cacheItemFilePath);

        if ($this->isInvalidOrExpiredCacheItemArray($item)) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws CacheException If data could not be read
     * @throws InvalidArgumentException If the $key string is not a legal value
     *
     * @noinspection PhpMissingParamTypeInspection because of interface implementation
     */
    public function get($key, $default = null)
    {
        return $this->getSingleFromData($key, $default);
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys    A list of keys that can obtained in a single operation.
     * @param mixed    $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have
     * $default as value.
     *
     * @throws CacheException If item could not be read
     * @throws InvalidArgumentException If $keys is neither an array nor a Traversable,
     * or if any of the $keys are not a legal value.
     *
     * @noinspection PhpMissingParamTypeInspection because of interface implementation
     */
    public function getMultiple($keys, $default = null)
    {
        $this->validateIterable($keys);

        $returnData = [];

        foreach ($keys as $key) {
            $returnData[$key] = $this->getSingleFromData($key, $default);
        }

        return $returnData;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed|null
     * @throws CacheException|InvalidArgumentException
     */
    protected function getSingleFromData(string $key, $default = null)
    {
        $cacheItemFilePath = $this->resolveCacheItemFilePath($key);

        if (! $this->fileSystemService->isReadableFile($cacheItemFilePath)) {
            return $default;
        }

        $item = $this->readCacheItemArray($cacheItemFilePath);

        if ($this->isInvalidOrExpiredCacheItemArray($item)) {
            $this->delete($key);
            return $default;
        }

        return (CacheItem::fromItemArray($item))->getValue($default);
    }

    /**
     * @param array $item Should represent cache item array.
     * @return bool True if invalid or expired, else false.
     */
    public function isInvalidOrExpiredCacheItemArray(array $item): bool
    {
        try {
            return (CacheItem::fromItemArray($item))->isExpired();
        } catch (Throwable) {
            return true; // It is invalid
        }
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store, must be serializable.
     * @param null|int|DateInterval $ttl Optional. The TTL value of this item. If no value is sent, it will be null
     * meaning that it will be stored indefinitely.
     *
     * @return bool True on success and false on failure.
     *
     * @throws CacheException If the item could not be stored.
     * @throws InvalidArgumentException If the $key string is not a legal value.
     *
     * @noinspection PhpMissingParamTypeInspection because of interface implementation
     */
    public function set($key, $value, $ttl = null)
    {
        return $this->setSingle($key, $value, $ttl);
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|DateInterval $ttl Optional. The TTL value of this item. If no value is sent, it will be null
     * meaning that it will be stored indefinitely.
     *
     * @return bool True on success and false on failure.
     *
     * @throws CacheException If the item could not be stored.
     * @throws InvalidArgumentException if $values is neither an array nor a Traversable,
     * or if any of the $values are not a legal value.
     *
     * @noinspection PhpMissingParamTypeInspection because of interface implementation
     */
    public function setMultiple($values, $ttl = null)
    {
        $this->validateIterable($values);

        $success = true;
        foreach ($values as $key => $value) {
            if (! $this->setSingle($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param null|int|DateInterval $ttl
     * @return bool
     * @throws CacheException|InvalidArgumentException
     */
    protected function setSingle(string $key, $value, $ttl = null): bool
    {
        $cacheItemFileName = $this->generateCacheItemFileName($key);
        $cacheItemSubDir = $this->resolveCacheItemSubDir($cacheItemFileName);

        if (! $this->fileSystemService->dirExists($cacheItemSubDir)) {
            $this->fileSystemService->createDir($cacheItemSubDir);
        }

        $cacheItemFilePath = $this->prepareFileNamePath($cacheItemSubDir, $cacheItemFileName);

        if (! $this->fileSystemService->fileExists($cacheItemFilePath)) {
            $this->fileSystemService->createFile($cacheItemFilePath);
        }

        return $this->persistData($cacheItemFilePath, $this->prepareCacheItemArray($value, $ttl));
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws CacheException If the cache item could not be deleted.
     * @throws InvalidArgumentException If the $key string is not a legal value.
     *
     * @noinspection PhpMissingParamTypeInspection because of interface implementation
     */
    public function delete($key)
    {
        $cacheItemFilePath = $this->resolveCacheItemFilePath($key);

        if ($this->fileSystemService->fileExists($cacheItemFilePath)) {
            return $this->fileSystemService->deleteFile($cacheItemFilePath);
        }

        return true;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws CacheException
     * @throws InvalidArgumentException if $keys is neither an array nor a Traversable,
     * or if any of the $keys are not a legal value.
     *
     * @noinspection PhpMissingParamTypeInspection because of interface implementation
     */
    public function deleteMultiple($keys)
    {
        $this->validateIterable($keys);

        $success = true;
        foreach ($keys as $key) {
            if (! $this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     *
     * @throws CacheException
     */
    public function clear()
    {
        return $this->fileSystemService->rmDirRecursive($this->getCachePath()) &&
            $this->ensureCachePathExistence();
    }

    /**
     * @param mixed $value
     * @param null|int|DateInterval $ttl
     * @return array
     * @throws InvalidArgumentException
     */
    protected function prepareCacheItemArray($value, $ttl = null): array
    {
        return (new CacheItem($value, $ttl))->getItemArray();
    }

    /**
     * @param string $key
     * @return string
     * @throws InvalidArgumentException
     */
    protected function resolveCacheItemFilePath(string $key): string
    {
        $cacheItemFileName = $this->generateCacheItemFileName($key);

        return $this->prepareFileNamePath(
            $this->resolveCacheItemSubDir($cacheItemFileName),
            $cacheItemFileName
        );
    }

    /**
     * @param string $dirPath
     * @param string $fileName
     * @return string
     */
    protected function prepareFileNamePath(string $dirPath, string $fileName): string
    {
        return $dirPath . DIRECTORY_SEPARATOR . $fileName;
    }

    /**
     * @param string $fileName
     * @return string
     */
    protected function resolveCacheItemSubDir(string $fileName): string
    {
        $cacheItemSubFolder = $this->generateCacheItemSubFolder($fileName);

        return $this->getCachePath() . DIRECTORY_SEPARATOR .
            $cacheItemSubFolder;
    }

    /**
     * Generate a unique hash value for the provided $key. This value is to be used as the actual file name.
     *
     * @param string $key
     * @return string
     * @throws InvalidArgumentException
     */
    protected function generateCacheItemFileName(string $key): string
    {
        $this->validateCacheKey($key);
        return hash('sha256', $key) . $this->fileExtension;
    }

    /**
     * Generate sub-folders which will contain the actual cache files. This is to prevent filesystem to be overwhelmed
     * with to many files in single folder.
     *
     * @param string $fileName
     * @return string
     */
    protected function generateCacheItemSubFolder(string $fileName): string
    {
        $lettersNum = 2;
        $subFoldersNum = 2;
        $totalChars = $lettersNum * $subFoldersNum;

        return implode(
            DIRECTORY_SEPARATOR,
            str_split(mb_substr($fileName, 0, $totalChars), $lettersNum)
        );
    }
}
