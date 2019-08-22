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
        // TODO: improve exception to more specific exceptions
        return new \Doctrine\DBAL\Exception\DriverException($message, $exception);
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase(Connection $conn)
    {
        $params = $conn->getParams();

        return $params['path'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
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
