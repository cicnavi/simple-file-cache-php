<?php

declare(strict_types=1);

namespace Cicnavi\Tests\SimpleFileCache\Services;

use Cicnavi\SimpleFileCache\Services\FileSystemService;
use Cicnavi\Tests\SimpleFileCache\Tools;
use Exception;
use PHPUnit\Framework\TestCase;

class FileSystemServiceTest extends TestCase
{
    protected static FileSystemService $fileSystemService;

    protected static string $testPath;

    protected static string $testFileName = 'sample-file.tmp';

    protected static array $testData = ['test' => 'data'];

    public static function setUpBeforeClass(): void
    {
        self::$testPath = dirname(__DIR__, 2) . '/tmp/filesystem-test';
        self::$fileSystemService = new FileSystemService();
    }

    public static function tearDownAfterClass(): void
    {
        Tools::rmdirRecursive(self::$testPath);
    }

    public function tearDown(): void
    {
        // Used to disable throwing exceptions for file_get_contents stub.
        // See testGetDataFromFileThrowsOnFileGetContentsError and tests/data/stubs/FileSystemServiceFunctions.phpstub
        $_ENV = [];
    }

    public function testCreateDir(): void
    {
        $this->assertFalse(is_dir(self::$testPath));
        self::$fileSystemService->createDir(self::$testPath);
        $this->assertTrue(is_dir(self::$testPath));
    }

    /**
     * @depends testCreateDir
     */
    public function testCreateExistingDir(): void
    {
        $this->expectException(Exception::class);
        self::$fileSystemService->createDir(self::$testPath);
    }

    /**
     * @depends testCreateDir
     */
    public function testIsWritableDir(): void
    {
        $this->assertFalse(self::$fileSystemService->isWritableDir('invalid-path'));
        $this->assertTrue(self::$fileSystemService->isWritableDir(self::$testPath));
    }

    /**
     * @throws Exception
     *
     * @depends testCreateDir
     */
    public function testCreateFile(): void
    {
        $filePath = self::$testPath . '/' . self::$testFileName;
        $this->assertFalse(self::$fileSystemService->fileExists($filePath));
        self::$fileSystemService->createFile($filePath);
        $this->assertTrue(self::$fileSystemService->fileExists($filePath));
    }

    public function testCreateExistingFile(): void
    {
        $filePath = self::$testPath . '/' . self::$testFileName;
        $this->expectException(Exception::class);
        self::$fileSystemService->createFile($filePath);
    }

    public function testStoreDataToNonExistentFile(): void
    {
        $this->expectException(Exception::class);
        self::$fileSystemService->storeDataToFile(self::$testPath . '/invalid-file.json', json_encode([]));
    }

    public function testGetDataFromNonExistentFileThrows(): void
    {
        $this->expectException(Exception::class);
        self::$fileSystemService->getDataFromFile(self::$testPath . '/invalid-file.json');
    }

    /**
     * @throws \Cicnavi\SimpleFileCache\Exceptions\CacheException
     * @depends testCreateDir
     * @depends testCreateFile
     */
    public function testGetDataFromFileThrowsOnFileGetContentsError(): void
    {
        $filePath = self::$testPath . '/' . self::$testFileName;
        $this->expectException(Exception::class);
        self::$fileSystemService->getDataFromFile($filePath, __NAMESPACE__ . '\FileSystemServiceTest::fileGetContents');
    }

    /**
     * @depends testCreateDir
     * @depends testCreateFile
     */
    public function testStoreDataToFile(): void
    {
        $filePath = self::$testPath . '/' . self::$testFileName;
        $data = json_decode(self::$fileSystemService->getDataFromFile($filePath), true);
        $this->assertNotSame(self::$testData, $data);
        self::$fileSystemService->storeDataToFile($filePath, json_encode($data));
        $data = json_decode(self::$fileSystemService->getDataFromFile($filePath), true);
        $this->assertNotSame(self::$testData, $data);
    }

    /**
     * @depends testCreateFile
     */
    public function testIsWritableFile(): void
    {
        $filePath = self::$testPath . '/' . self::$testFileName;
        $this->assertTrue(self::$fileSystemService->isWritableFile($filePath));

        $this->assertFalse(self::$fileSystemService->isWritableFile(self::$testPath . '/invalid'));
    }

    /**
     * @depends testCreateFile
     */
    public function testIsReadableFile(): void
    {
        $filePath = self::$testPath . '/' . self::$testFileName;
        $this->assertTrue(self::$fileSystemService->isReadableFile($filePath));

        $this->assertFalse(self::$fileSystemService->isReadableFile(self::$testPath . '/invalid'));
    }

    /**
     * @depends testCreateDir
     */
    public function testRmDirRecursive(): void
    {
        $sampleDir = self::$testPath . '/recursive/dir/test';
        self::$fileSystemService->createDir($sampleDir);
        $this->assertTrue(self::$fileSystemService->dirExists($sampleDir));
        $fileName = $sampleDir . '/foo';
        touch($fileName);
        $this->assertTrue(self::$fileSystemService->fileExists($fileName));
        self::$fileSystemService->rmDirRecursive(self::$testPath . '/recursive');
        $this->assertFalse(self::$fileSystemService->fileExists($fileName));
        $this->assertFalse(self::$fileSystemService->dirExists($sampleDir));
    }

    /**
     * @throws Exception
     *
     * @depends testCreateDir
     */
    public function testDeleteNonExistentFileThrows(): void
    {
        $this->expectException(Exception::class);
        self::$fileSystemService->deleteFile(self::$testPath . '/' . 'non-existent');
    }

    /**
     * @throws Exception
     *
     * @depends testCreateDir
     */
    public function testDelete(): void
    {
        $filePath = self::$testPath . '/' . 'to-be-deleted.json';
        self::$fileSystemService->createFile($filePath);
        $this->assertTrue(self::$fileSystemService->fileExists($filePath));
        self::$fileSystemService->deleteFile($filePath);
        $this->assertFalse(self::$fileSystemService->fileExists($filePath));
    }

    public function testgetDataFromFileThrowsForInvalidCallback(): void
    {
        $this->expectException(Exception::class);
        self::$fileSystemService->getDataFromFile('some-file', 'invalid-callback');
    }

    /**
     * Mock function for file_get_contents to simulate exception throw.
     * @param string $filePath
     * @return bool
     */
    public static function fileGetContents(string $filePath): bool
    {
        return false;
    }
}
