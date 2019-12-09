<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\DriverException;
use Exception;

class DriverError extends DBALException
{
    private DriverException $driverException;

    /** {@inheritDoc} */
    public function __construct($message, DriverException $driverException)
    {
        $exception = null;

        if ($driverException instanceof Exception) {
            $exception = $driverException;
        }

        parent::__construct($message, 0, $exception);

        $this->driverException = $driverException;
    }

    /** @return int|string|null */
    public function getErrorCode()
    {
        return $this->driverException->getErrorCode();
    }
}
