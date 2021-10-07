<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Doctrine\DBAL\Exception\DriverException;
use Throwable;

// todo create a marker interface
// deprecated
final class DriverError extends DriverException
{
    public static function withException(Throwable $exception): self
    {
        return new self($exception, null);
    }
}
