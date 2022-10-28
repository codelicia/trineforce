<?php

declare(strict_types=1);

namespace Codelicia\Soql\Driver;

use PDOException;
use RuntimeException;

final class Exception extends RuntimeException
{
    public static function new(PDOException $exception): self
    {
        return new self($exception->getMessage(), $exception->getCode(), $exception);
    }
}
