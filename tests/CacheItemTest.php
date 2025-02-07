<?php

declare(strict_types=1);

namespace Cicnavi\Tests\SimpleFileCache;

use Cicnavi\SimpleFileCache\CacheItem;
use Cicnavi\SimpleFileCache\Exceptions\InvalidArgumentException;
use Cicnavi\SimpleFileCache\SimpleFileCache;
use DateInterval;
use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheItem::class)]
#[UsesClass(SimpleFileCache::class)]
class CacheItemTest extends TestCase
{
    protected static array $testValues;
    protected static array $testTtls;
    protected static array $testItemArray;
    protected static array $testInvalidItemArrays;
    protected static string $testTtlInvalid = 'invalid-ttl';

    public static function setUpBeforeClass(): void
    {
        self::$testValues = [
            'null' => null,
            'int' => 123,
            'string' => 'sample',
            'float' => 12.34,
            'bool' => true,
            'array' => ['foo', 'bar'],
            'object' => new DateTime(),
        ];

        // 10 min
        $ttl = 10 * 60;
        $ttlDateInterval = 'PT10M';
        self::$testTtls = [
            'null' => null,
            'future' => $ttl,
            'dateInterval' => new DateInterval($ttlDateInterval),
            'past' => $ttl * -1,
        ];

        self::$testItemArray = [
            CacheItem::ARRAY_KEY_VALUE => 'value',
            CacheItem::ARRAY_KEY_VALUE_TYPE => 'string',
            CacheItem::ARRAY_KEY_EXPIRES_AT => null,
            CacheItem::ARRAY_KEY_CREATED_AT => time(),
            CacheItem::ARRAY_KEY_VERSION => CacheItem::VERSION,
        ];

        self::$testInvalidItemArrays = [
            'incomplete' => [
                CacheItem::ARRAY_KEY_VALUE => 'value',
                CacheItem::ARRAY_KEY_VALUE_TYPE => 'string',
                CacheItem::ARRAY_KEY_EXPIRES_AT => null,
                CacheItem::ARRAY_KEY_CREATED_AT => time(),
                //CacheItem::ARRAY_KEY_VERSION => CacheItem::VERSION,
            ],
            'expires-at' => [
                CacheItem::ARRAY_KEY_VALUE => 'value',
                CacheItem::ARRAY_KEY_VALUE_TYPE => 'string',
                CacheItem::ARRAY_KEY_EXPIRES_AT => 'invalid',
                CacheItem::ARRAY_KEY_CREATED_AT => time(),
                CacheItem::ARRAY_KEY_VERSION => CacheItem::VERSION,
            ],
            'version' => [
                CacheItem::ARRAY_KEY_VALUE => 'value',
                CacheItem::ARRAY_KEY_VALUE_TYPE => 'string',
                CacheItem::ARRAY_KEY_EXPIRES_AT => null,
                CacheItem::ARRAY_KEY_CREATED_AT => time(),
                CacheItem::ARRAY_KEY_VERSION => -1,
            ]
        ];
    }

    public function testConstruct(): void
    {
        foreach (self::$testValues as $value) {
            $this->assertInstanceOf(CacheItem::class, new CacheItem($value));
        }

        foreach (self::$testTtls as $ttl) {
            $this->assertInstanceOf(CacheItem::class, new CacheItem('foo', $ttl));
        }
    }

    public function testConstructWithObjectValue(): void
    {
        $this->assertInstanceOf(CacheItem::class, new CacheItem(new DateTime()));
    }

    public function testConstructWithInvalidValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CacheItem(STDOUT); // resource can't be cached
    }

    public function testConstructFromItemArray(): void
    {
        $itemArray = self::$testItemArray;
        $this->assertInstanceOf(CacheItem::class, CacheItem::fromItemArray($itemArray));

        $itemArray[CacheItem::ARRAY_KEY_VALUE] = serialize(self::$testValues['object']);
        $itemArray[CacheItem::ARRAY_KEY_VALUE_TYPE] = gettype(self::$testValues['object']);
        $this->assertInstanceOf(CacheItem::class, CacheItem::fromItemArray($itemArray));

        $itemArray[CacheItem::ARRAY_KEY_EXPIRES_AT] = self::$testTtls['future'];
        $this->assertInstanceOf(CacheItem::class, CacheItem::fromItemArray($itemArray));
    }

    public function testExpiration(): void
    {
        $this->assertFalse((new CacheItem('foo', self::$testTtls['null']))->isExpired());
        $this->assertFalse((new CacheItem('foo', self::$testTtls['future']))->isExpired());
        $this->assertFalse((new CacheItem('foo', self::$testTtls['dateInterval']))->isExpired());

        $this->assertTrue((new CacheItem('foo', self::$testTtls['past']))->isExpired());
    }

    public function testGetValues(): void
    {
        foreach (self::$testValues as $key => $value) {
            if ($key == 'object') {
                $this->assertEquals($value, (new CacheItem($value))->getValue(), "$key");
            } else {
                $this->assertSame($value, (new CacheItem($value))->getValue(), "$key");
            }
        }
    }

    public function testExpiredReturnsDefault(): void
    {
        $value = 'foo';
        $default = 'default';
        $item = new CacheItem($value, self::$testTtls['past']);
        $this->assertSame($default, $item->getValue($default));
    }

    public function testIsValidItemArray(): void
    {
        foreach (self::$testInvalidItemArrays as $key => $itemArray) {
            $this->assertFalse(CacheItem::isValidItemArray($itemArray), "$key");
        }

        $this->assertTrue(CacheItem::isValidItemArray(self::$testItemArray));
    }

    public function testValidateArray(): void
    {
        CacheItem::validateItemArray(self::$testItemArray);

        $this->expectException(InvalidArgumentException::class);

        CacheItem::validateItemArray(self::$testInvalidItemArrays['incomplete']);
    }

    public function testGetItemArray(): void
    {
        $item = CacheItem::fromItemArray(self::$testItemArray);

        $this->assertSame(
            self::$testItemArray[CacheItem::ARRAY_KEY_VALUE],
            $item->getItemArray()[CacheItem::ARRAY_KEY_VALUE]
        );
        $this->assertSame(
            self::$testItemArray[CacheItem::ARRAY_KEY_VALUE_TYPE],
            $item->getItemArray()[CacheItem::ARRAY_KEY_VALUE_TYPE]
        );
        $this->assertSame(
            self::$testItemArray[CacheItem::ARRAY_KEY_EXPIRES_AT],
            $item->getItemArray()[CacheItem::ARRAY_KEY_EXPIRES_AT]
        );
        $this->assertSame(
            self::$testItemArray[CacheItem::ARRAY_KEY_VERSION],
            $item->getItemArray()[CacheItem::ARRAY_KEY_VERSION]
        );
    }

    public function testJsonSerialize(): void
    {
        $item = new CacheItem('sample');
        $this->assertSame($item->getItemArray(), $item->jsonSerialize());
    }
}
