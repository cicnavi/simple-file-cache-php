<?php

declare(strict_types=1);

namespace Cicnavi\Tests\SimpleFileCache\Cache;

use Cicnavi\SimpleFileCache\Exceptions\CacheException;
use Cicnavi\SimpleFileCache\Exceptions\InvalidArgumentException;
use Cicnavi\SimpleFileCache\SimpleFileCache;
use Cicnavi\SimpleFileCache\Services\FileSystemService;
use Exception;
use PHPUnit\Framework\TestCase;
use Cicnavi\Tests\SimpleFileCache\Tools;
use Cicnavi\SimpleFileCache\CacheItem;

/**
 * Class SimpleFileCacheTest
 * @package Cicnavi\Tests\Cache
 *
 * @covers \Cicnavi\SimpleFileCache\SimpleFileCache
 */
class SimpleFileCacheTest extends TestCase
{
    protected static string $testCachePath;

    protected static string $testKey = 'testKey';
    protected static string $testValue = 'testValue';
    protected static string $testCacheName = 'test-cache';

    protected static array $testItemArray;

    public function setUp(): void
    {
        self::$testCachePath = dirname(__DIR__, 1) . '/tmp/cache-test';

        $sampleObj = new CacheItem('sample');

        self::$testItemArray = [
            self::$testKey => [
                CacheItem::ARRAY_KEY_VALUE => self::$testValue,
                CacheItem::ARRAY_KEY_VALUE_TYPE => gettype(self::$testValue),
                CacheItem::ARRAY_KEY_EXPIRES_AT => null,
                CacheItem::ARRAY_KEY_CREATED_AT => time(),
                CacheItem::ARRAY_KEY_VERSION => CacheItem::VERSION
            ],
            'expired' => [
                CacheItem::ARRAY_KEY_VALUE => self::$testValue,
                CacheItem::ARRAY_KEY_VALUE_TYPE => gettype(self::$testValue),
                CacheItem::ARRAY_KEY_EXPIRES_AT => time() - 1,
                CacheItem::ARRAY_KEY_CREATED_AT => time(),
                CacheItem::ARRAY_KEY_VERSION => CacheItem::VERSION
            ],
            'oldVersion' => [
                CacheItem::ARRAY_KEY_VALUE => self::$testValue,
                CacheItem::ARRAY_KEY_VALUE_TYPE => gettype(self::$testValue),
                CacheItem::ARRAY_KEY_EXPIRES_AT => null,
                CacheItem::ARRAY_KEY_CREATED_AT => time(),
                CacheItem::ARRAY_KEY_VERSION => -1
            ],
            'object' => [
                CacheItem::ARRAY_KEY_VALUE => serialize($sampleObj),
                CacheItem::ARRAY_KEY_VALUE_TYPE => gettype($sampleObj),
                CacheItem::ARRAY_KEY_EXPIRES_AT => null,
                CacheItem::ARRAY_KEY_CREATED_AT => time(),
                CacheItem::ARRAY_KEY_VERSION => CacheItem::VERSION
            ],
            'resource' => [
                CacheItem::ARRAY_KEY_VALUE => 'resource',
                CacheItem::ARRAY_KEY_VALUE_TYPE => 'resource',
                CacheItem::ARRAY_KEY_EXPIRES_AT => null,
                CacheItem::ARRAY_KEY_CREATED_AT => time(),
                CacheItem::ARRAY_KEY_VERSION => CacheItem::VERSION
            ],
        ];

        mkdir(self::$testCachePath, 0764, true);
    }

    public function tearDown(): void
    {
        Tools::rmdirRecursive(self::$testCachePath);
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testConstructWithInvalidPathThrows(): void
    {
        $this->expectException(CacheException::class);

        new SimpleFileCache('invalid-path');
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testConstructWithInvalidCacheNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SimpleFileCache(self::$testCachePath, 'invalid cache name');
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testConstructWithCustomCacheName(): void
    {
        $cache = new SimpleFileCache(self::$testCachePath, self::$testCacheName);
        $this->assertSame(self::$testCacheName, $cache->getCacheName());
        $this->assertTrue(is_dir($cache->getCachePath()));
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testGetWithInvalidKeyThrows(): void
    {
        $cache = new SimpleFileCache(self::$testCachePath);

        $this->expectException(InvalidArgumentException::class);

        $cache->get('a b');
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testGetDefaultValue(): void
    {
        $cache = new SimpleFileCache(self::$testCachePath);

        $default = 'default';
        $this->assertSame($default, $cache->get('nonexistand', $default));
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testEnsureCachePathExistenceThrows(): void
    {
        // Ensure cache files...
        new SimpleFileCache(self::$testCachePath, self::$testCacheName);

        $fileServiceStub = $this->createStub(FileSystemService::class);
        $fileServiceStub->method('isWritableDir')
            ->willReturn(true);
        $fileServiceStub->method('dirExists')
            ->willReturn(false);
        $fileServiceStub->method('createDir')
            ->will($this->throwException(new Exception('Test error')));

        $this->expectException(CacheException::class);
        new SimpleFileCache(self::$testCachePath, self::$testCacheName, $fileServiceStub);
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testSetGet(): void
    {
        $cache = new SimpleFileCache(self::$testCachePath);
        $cache->set(self::$testKey, self::$testValue);
        $this->assertTrue($cache->has(self::$testKey));
        $this->assertSame(self::$testValue, $cache->get(self::$testKey));
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testHas(): void
    {
        $cache = new SimpleFileCache(self::$testCachePath);
        $this->assertFalse($cache->has('none'));
        $cache->set(self::$testKey, self::$testValue);
        $this->assertTrue($cache->has(self::$testKey));
        $cache->set(self::$testKey, self::$testValue, -1);
        $this->assertFalse($cache->has(self::$testKey));
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testPersistDataThrows(): void
    {
        // Ensure cache files...
        new SimpleFileCache(self::$testCachePath, self::$testCacheName);

        $fileServiceStub = $this->createStub(FileSystemService::class);
        $fileServiceStub->method('isWritableDir')
            ->willReturn(true);
        $fileServiceStub->method('fileExists')
            ->willReturn(true);
        $fileServiceStub->method('storeDataToFile')
            ->will($this->throwException(new Exception()));

        $this->expectException(CacheException::class);

        $cache = new SimpleFileCache(self::$testCachePath, self::$testCacheName, $fileServiceStub);
        $cache->set('test', 'test');
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testReadCacheItemArrayThrows(): void
    {
        // Ensure cache files...
        new SimpleFileCache(self::$testCachePath, self::$testCacheName);

        $fileServiceStub = $this->createStub(FileSystemService::class);
        $fileServiceStub->method('isWritableDir')
            ->willReturn(true);
        $fileServiceStub->method('isReadableFile')
            ->willReturn(true);
        $fileServiceStub->method('getDataFromFile')
            ->will($this->throwException(new Exception()));

        $this->expectException(CacheException::class);

        $cache = new SimpleFileCache(self::$testCachePath, self::$testCacheName, $fileServiceStub);
        $cache->get('test', 'test');
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testDelete(): void
    {
        $cache = new SimpleFileCache(self::$testCachePath, self::$testCacheName);
        $cache->delete('non_existent');
        $cache->set('foo', 'bar');
        $this->assertSame('bar', $cache->get('foo'));
        $cache->delete('foo');
        $this->assertNull($cache->get('foo'));
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testGetSetDeleteMultiple(): void
    {
        $cache = new SimpleFileCache(self::$testCachePath, self::$testCacheName);
        $values = ['foo' => 'bar', 'test' => 'test'];
        $cache->setMultiple($values);
        $this->assertSame($values, $cache->getMultiple(array_keys($values)));
        $cache->deleteMultiple(array_keys($values));
        $this->assertNotSame($values, $cache->getMultiple(array_keys($values)));
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testSetMultipleFails(): void
    {
        // Ensure cache files...
        new SimpleFileCache(self::$testCachePath, self::$testCacheName);

        $fileServiceStub = $this->createStub(FileSystemService::class);
        $fileServiceStub->method('isWritableDir')
            ->willReturn(true);
        $fileServiceStub->method('dirExists')
            ->willReturn(true);
        $fileServiceStub->method('fileExists')
            ->willReturn(true);
        $fileServiceStub->method('storeDataToFile')
            ->willReturn(false);

        $cache = new SimpleFileCache(self::$testCachePath, self::$testCacheName, $fileServiceStub);
        $values = ['foo' => 'bar', 'test' => 'test'];
        $this->assertFalse($cache->setMultiple($values));
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testDeleteMultipleFails(): void
    {
        // Ensure cache files...
        new SimpleFileCache(self::$testCachePath, self::$testCacheName);

        $fileServiceStub = $this->createStub(FileSystemService::class);
        $fileServiceStub->method('isWritableDir')
            ->willReturn(true);
        $fileServiceStub->method('dirExists')
            ->willReturn(true);
        $fileServiceStub->method('fileExists')
            ->willReturn(true);
        $fileServiceStub->method('deleteFile')
            ->willReturn(false);

        $cache = new SimpleFileCache(self::$testCachePath, self::$testCacheName, $fileServiceStub);
        $values = ['foo' => 'bar', 'test' => 'test'];
        $this->assertFalse($cache->deleteMultiple($values));
    }

    /**
     * @uses \Cicnavi\SimpleFileCache\Services\FileSystemService
     */
    public function testValidateIterable(): void
    {
        $cache = new SimpleFileCache(self::$testCachePath, self::$testCacheName);
        $this->expectException(InvalidArgumentException::class);
        /**
         * @noinspection PhpParamsInspection For testing purposes
         * @psalm-suppress InvalidArgument For testing purposes
         */
        $cache->getMultiple('invalid');
    }

    public function testClear(): void
    {
        $cache = new SimpleFileCache(self::$testCachePath, self::$testCacheName);
        $cache->set('foo', 'bar');
        $this->assertSame('bar', $cache->get('foo'));
        $cache->clear();
        $this->assertNull($cache->get('foo'));
    }

    public function testGetExpired(): void
    {
        $cache = new SimpleFileCache(self::$testCachePath, self::$testCacheName);
        $cache->set('foo', 'bar', -1);
        $this->assertNull($cache->get('foo'));
    }

    public function testStoreGetObject(): void
    {
        $cache = new SimpleFileCache(self::$testCachePath, self::$testCacheName);
        $sampleObj = new CacheItem('sample');
        $cache->set('sampleObj', $sampleObj);
        $cachedObj = $cache->get('sampleObj');
        $this->assertInstanceOf(CacheItem::class, $cachedObj);
        $this->assertSame($sampleObj->getValue(), $cachedObj->getValue());
    }

    public function testIsInvalidOrExpiredCacheItemArray(): void
    {
        $cache = new SimpleFileCache(self::$testCachePath, self::$testCacheName);
        $this->assertTrue($cache->isInvalidOrExpiredCacheItemArray(self::$testItemArray['oldVersion']));
        $this->assertFalse($cache->isInvalidOrExpiredCacheItemArray(self::$testItemArray['testKey']));
    }
}
