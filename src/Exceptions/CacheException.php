<?php

declare(strict_types=1);

namespace Cicnavi\SimpleFileCache\Exceptions;

use Exception;
use Psr\SimpleCache\CacheException as PsrCacheException;

class CacheException extends Exception implements PsrCacheException
{
}
