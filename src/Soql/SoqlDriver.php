<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Assert\Assertion;
use Codelicia\Soql\Factory\AuthorizedClientFactory;
use Codelicia\Soql\Factory\HttpAccessTokenFactory;
use Codelicia\Soql\Factory\HttpAuthorizedClientFactory;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Query;

use function array_key_exists;

class SoqlDriver implements Driver, ExceptionConverter
{
    /** {@inheritDoc} */
    public function connect(array $params): Connection
    {
        return new SoqlConnection($this->getAuthorizedClientFactory($params));
    }

    public function getName(): string
    {
        return 'soql';
    }

    /** {@inheritdoc} */
    public function convert(Exception $exception, ?Query $query): DriverException
    {
        // fixme
        return DriverError::withException($exception);
    }

    /** {@inheritdoc} */
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        return null;
    }

    /** {@inheritdoc} */
    public function getDatabasePlatform(): MySQLPlatform
    {
        return new MySQLPlatform();
    }

    /** {@inheritdoc} */
    public function getExceptionConverter(): ExceptionConverter
    {
        return $this;
    }

    /** {@inheritdoc} */
    public function getSchemaManager(\Doctrine\DBAL\Connection $conn, AbstractPlatform $platform)
    {
        // TODO: implements specific schema manager
    }

    /** @param string[] $params */
    private function getAuthorizedClientFactory(array $params): AuthorizedClientFactory
    {
        Assertion::keyExists($params, 'user');
        Assertion::keyExists($params, 'password');
        Assertion::keyExists($params, 'salesforceInstance');
        Assertion::keyExists($params, 'apiVersion');
        Assertion::keyExists($params, 'consumerKey');
        Assertion::keyExists($params, 'consumerSecret');

        if (array_key_exists('authorizedClientFactory', $params)) {
            Assertion::isInstanceOf($params['authorizedClientFactory'], AuthorizedClientFactory::class);

            return $params['authorizedClientFactory'];
        }

        return new HttpAuthorizedClientFactory(new HttpAccessTokenFactory(
            $params['salesforceInstance'],
            $params['apiVersion'],
            $params['consumerKey'],
            $params['consumerSecret'],
            $params['user'],
            $params['password']
        ), $params['salesforceInstance']);
    }
}
