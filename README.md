![Build](https://github.com/cicnavi/simple-file-cache-php/workflows/Build/badge.svg)

# cicnavi/simple-file-cache-php
[PSR-16](https://www.php-fig.org/psr/psr-16/) simple cache provider based on files.

## Installation
```shell script
composer require cicnavi/simple-file-cache-php
```

## Usage
Use class Cicnavi\SimpleFileCache\SimpleFileCache to instantiate a cache instance.
It can accept following arguments:
* $cacheName (optional/recommended, string) - cache name used to separate cache domains,
'simple-file-cache' being default
* $storagePath (optional, string) - path to writable folder which will be used to store cache files.
If not provided, default system 'tmp' folder will be used.  
* $fileSystemService (optional, Cicnavi\SimpleFileCache\Services\Interfaces\FileSystemServiceInterface) - 
FileSystemServiceInterface instance used to communicate with the filesystem
(Cicnavi\SimpleFileCache\Services\FileSystemService being default)

Using different cache names is a recommended way of separating cache domains.
If you don't specify the cache name, the default will be used. Keep in mind that the PSR-16 includes method clear(),
which wipes out the entire cache for a particular domain (particular cache name).

### Example
```php
use Cicnavi\SimpleFileCache\SimpleFileCache;

$cache = new SimpleFileCache('some-cache-name', '/some/writable/storage/folder');
// Alternatively, instantiate it using defaults...
// $cache = new SimpleFileCache('some-cache-name'); // Use specific cache name, but use default system 'tmp' folder 
// $cache = new SimpleFileCache(); // Use default cache name and default system 'tmp' folder

$somethingImportant = 'This string was fetched from API using HTTP request, which is expensive. I\'ll store it 
in cache for later use so I don\'t have to make another HTTP request for the same thing';

// Use any of the PSR-16 metods to work with cache...:
$cache->set('somethingImportant', $somethingImportant);

//... later
$somethingImportant = $cache->get('somethingImportant');
```

## Tests
This will run phpunit, psalm and phpcs:
```bash
$ composer run-script test
```

## Licence
MIT