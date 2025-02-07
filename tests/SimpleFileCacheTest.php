<?php

declare(strict_types=1);

namespace Cicnavi\Tests\SimpleFileCache;

use Cicnavi\SimpleFileCache\Exceptions\CacheException;
use Cicnavi\SimpleFileCache\Exceptions\InvalidArgumentException;
use Cicnavi\SimpleFileCache\SimpleFileCache;
use Cicnavi\SimpleFileCache\Services\FileSystemService;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Cicnavi\SimpleFileCache\CacheItem;

#[CoversClass(SimpleFileCache::class)]
#[UsesClass(CacheItem::class)]
#[UsesClass(FileSystemService::class)]
class SimpleFileCacheTest extends TestCase
{
    protected static string $testCachePath;

    protected static string $testKey = 'testKey';
    protected static string $testValue = 'testValue';
    protected static string $testCacheName = 'test-cache';

    protected static array $testItemArray;

    public function setUp(): void
    {
        self::$testCachePath = dirname(__DIR__, 1) . '/build/cache-test';

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

    public function testConstructWithoutParameters(): void
    {
        $cache = new SimpleFileCache();
        $cache->set('test', 'test');
        $this->assertTrue($cache->has('test'));
    }

    public function testConstructWithInvalidCacheNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SimpleFileCache('invalid cache name');
    }

    public function testConstructWithCustomCacheName(): void
    {
        $cache = new SimpleFileCache(self::$testCacheName);
        $this->assertSame(self::$testCacheName, $cache->getCacheName());
    }

    public function testConstructWithInvalidPathThrows(): void
    {
        $this->expectException(CacheException::class);

        new SimpleFileCache(self::$testCacheName, 'invalid-path');
    }

    public function testConstructWithCustomStoragePath(): void
    {
        $cache = new SimpleFileCache(self::$testCacheName, self::$testCachePath);
        $this->assertTrue(is_dir($cache->getCachePath()));
    }

    public function testGetWithInvalidKeyThrows(): void
    {
        $cache = new SimpleFileCache();

        $this->expectException(InvalidArgumentException::class);

        $cache->get('a b');
    }

    public function testGetDefaultValue(): void
    {
        $cache = new SimpleFileCache();

        $default = 'default';
        $this->assertSame($default, $cache->get('nonexistand', $default));
    }

    public function testEnsureCachePathExistenceThrows(): void
    {
        // Ensure cache files...
        new SimpleFileCache(self::$testCacheName, self::$testCachePath);

        $fileServiceStub = $this->createStub(FileSystemService::class);
        $fileServiceStub->method('isWritableDir')
            ->willReturn(true);
        $fileServiceStub->method('dirExists')
            ->willReturn(false);
        $fileServiceStub->method('createDir')
            ->will($this->throwException(new Exception('Test error')));

        $this->expectException(CacheException::class);
        new SimpleFileCache(self::$testCacheName, self::$testCachePath, $fileServiceStub);
    }

    public function testSetGet(): void
    {
        $cache = new SimpleFileCache(self::$testCacheName, self::$testCachePath);
        $cache->set(self::$testKey, self::$testValue);
        $this->assertTrue($cache->has(self::$testKey));
        $this->assertSame(self::$testValue, $cache->get(self::$testKey));
    }

    public function testHas(): void
    {
        $cache = new SimpleFileCache(self::$testCacheName, self::$testCachePath);
        $this->assertFalse($cache->has('none'));
        $cache->set(self::$testKey, self::$testValue);
        $this->assertTrue($cache->has(self::$testKey));
        $cache->set(self::$testKey, self::$testValue, -1);
        $this->assertFalse($cache->has(self::$testKey));
    }

    public function testPersistDataThrows(): void
    {
        // Ensure cache files...
        new SimpleFileCache(self::$testCacheName, self::$testCachePath);

        $fileServiceStub = $this->createStub(FileSystemService::class);
        $fileServiceStub->method('isWritableDir')
            ->willReturn(true);
        $fileServiceStub->method('fileExists')
            ->willReturn(true);
        $fileServiceStub->method('storeDataToFile')
            ->will($this->throwException(new Exception()));

        $this->expectException(CacheException::class);

        $cache = new SimpleFileCache(self::$testCacheName, self::$testCachePath, $fileServiceStub);
        $cache->set('test', 'test');
    }

    public function testReadCacheItemArrayThrows(): void
    {
        // Ensure cache files...
        new SimpleFileCache(self::$testCacheName, self::$testCachePath);

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

    public function testDelete(): void
    {
        $cache = new SimpleFileCache(self::$testCacheName, self::$testCachePath);
        $cache->delete('non_existent');
        $cache->set('foo', 'bar');
        $this->assertSame('bar', $cache->get('foo'));
        $cache->delete('foo');
        $this->assertNull($cache->get('foo'));
    }

    public function testGetSetDeleteMultiple(): void
    {
        $cache = new SimpleFileCache(self::$testCacheName, self::$testCachePath);
        $values = ['foo' => 'bar', 'test' => 'test'];
        $cache->setMultiple($values);
        $this->assertSame($values, $cache->getMultiple(array_keys($values)));
        $cache->deleteMultiple(array_keys($values));
        $this->assertNotSame($values, $cache->getMultiple(array_keys($values)));
    }

    public function testSetMultipleFails(): void
    {
        // Ensure cache files...
        new SimpleFileCache(self::$testCacheName, self::$testCachePath);

        $fileServiceStub = $this->createStub(FileSystemService::class);
        $fileServiceStub->method('isWritableDir')
            ->willReturn(true);
        $fileServiceStub->method('dirExists')
            ->willReturn(true);
        $fileServiceStub->method('fileExists')
            ->willReturn(true);
        $fileServiceStub->method('storeDataToFile')
            ->willReturn(false);

        $cache = new SimpleFileCache(self::$testCacheName, self::$testCachePath, $fileServiceStub);
        $values = ['foo' => 'bar', 'test' => 'test'];
        $this->assertFalse($cache->setMultiple($values));
    }

    public function testDeleteMultipleFails(): void
    {
        // Ensure cache files...
        new SimpleFileCache(self::$testCacheName, self::$testCachePath);

        $fileServiceStub = $this->createStub(FileSystemService::class);
        $fileServiceStub->method('isWritableDir')
            ->willReturn(true);
        $fileServiceStub->method('dirExists')
            ->willReturn(true);
        $fileServiceStub->method('fileExists')
            ->willReturn(true);
        $fileServiceStub->method('deleteFile')
            ->willReturn(false);

        $cache = new SimpleFileCache(self::$testCacheName, self::$testCachePath, $fileServiceStub);
        $values = ['foo' => 'bar', 'test' => 'test'];
        $this->assertFalse($cache->deleteMultiple($values));
    }

    public function testClear(): void
    {
        $cache = new SimpleFileCache(self::$testCacheName, self::$testCachePath);
        $cache->set('foo', 'bar');
        $this->assertSame('bar', $cache->get('foo'));
        $cache->clear();
        $this->assertNull($cache->get('foo'));
    }

    public function testGetExpired(): void
    {
        $cache = new SimpleFileCache(self::$testCacheName, self::$testCachePath);
        $cache->set('foo', 'bar', -1);
        $this->assertNull($cache->get('foo'));
    }

    public function testStoreGetObject(): void
    {
        $cache = new SimpleFileCache(self::$testCacheName, self::$testCachePath);
        $sampleObj = new CacheItem('sample');
        $cache->set('sampleObj', $sampleObj);
        $cachedObj = $cache->get('sampleObj');
        $this->assertInstanceOf(CacheItem::class, $cachedObj);
        $this->assertSame($sampleObj->getValue(), $cachedObj->getValue());
    }

    public function testIsInvalidOrExpiredCacheItemArray(): void
    {
        $cache = new SimpleFileCache(self::$testCacheName, self::$testCachePath);
        $this->assertTrue($cache->isInvalidOrExpiredCacheItemArray(self::$testItemArray['oldVersion']));
        $this->assertFalse($cache->isInvalidOrExpiredCacheItemArray(self::$testItemArray['testKey']));
    }
}
