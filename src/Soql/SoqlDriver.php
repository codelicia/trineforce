<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Assert\Assertion;
use Assert\AssertionFailedException;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Platforms\MySqlPlatform;

class SoqlDriver implements Driver, ExceptionConverterDriver
{
    /**
     * {@inheritDoc}
     *
     * @throws AssertionFailedException
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = []) : Connection
    {
        Assertion::notNull($username);
        Assertion::notNull($password);

        return new SoqlConnection($params, (string) $username, (string) $password);
    }

    public function getName() : string
    {
        return 'soql';
    }

    /** {@inheritdoc} */
    public function convertException($message, DriverException $exception)
    {
        return new DriverError($message, $exception);
    }

    /** {@inheritdoc} */
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
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
    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        // TODO: implements specific schema manager
    }
}
