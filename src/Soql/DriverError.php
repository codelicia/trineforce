<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Exception;
use Throwable;

class DriverError extends Exception
{
    private Throwable $driverException;

    /** {@inheritDoc} */
    public function __construct($message, Throwable $driverException)
    {
        $exception = null;

        if ($driverException instanceof Exception) {
            $exception = $driverException;
        }

        parent::__construct(message: $message, previous: $exception);

        $this->driverException = $driverException;
    }

    public function getErrorCode(): int | null | string
    {
        return $this->driverException->getErrorCode();
    }
}
