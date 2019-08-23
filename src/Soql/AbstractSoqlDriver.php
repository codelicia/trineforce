<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Platforms\MySqlPlatform;

abstract class AbstractSoqlDriver implements Driver, ExceptionConverterDriver
{
    /** {@inheritdoc} */
    public function convertException($message, DriverException $exception)
    {
        return new DriverError($message, $exception);
    }

    /** {@inheritdoc} */
    public function getDatabase(Connection $conn)
    {
        return null;
    }

    /** {@inheritdoc} */
    public function getDatabasePlatform()
    {
        // TODO: implements specific platform
        return new MySqlPlatform();
    }

    /** {@inheritdoc} */
    public function getSchemaManager(Connection $conn)
    {
        // TODO: implements specific schema manager
    }
}
