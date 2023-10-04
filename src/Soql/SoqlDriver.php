<?php

declare(strict_types=1);

namespace Codelicia\Soql;

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
use function array_merge_recursive;
use function Psl\invariant;

readonly class SoqlDriver implements Driver, ExceptionConverter
{
    /** {@inheritDoc} */
    public function connect(array $params): Connection
    {
        // Configuration can also be passed as driverOptions. Needed for doctrine-bundle compatibility.
        if (array_key_exists('driverOptions', $params)) {
            $params += $params['driverOptions'];
        }

        return new SoqlConnection($this->getAuthorizedClientFactory($params));
    }

    public function getName(): string
    {
        return 'soql';
    }

    /** {@inheritDoc} */
    public function convert(Exception $exception, Query|null $query): DriverException
    {
        // fixme
        return DriverError::withException($exception);
    }

    /** {@inheritDoc} */
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        return null;
    }

    /** {@inheritDoc} */
    public function getDatabasePlatform(): MySQLPlatform
    {
        return new MySQLPlatform();
    }

    /** {@inheritDoc} */
    public function getExceptionConverter(): ExceptionConverter
    {
        return $this;
    }

    /** {@inheritDoc} */
    public function getSchemaManager(\Doctrine\DBAL\Connection $conn, AbstractPlatform $platform)
    {
        // TODO: implements specific schema manager
    }

    /** @param string[] $params */
    private function getAuthorizedClientFactory(array $params): AuthorizedClientFactory
    {
        // @fixme(malukenho): workaround to make sure we can work with symfony framework + doctrine bundle config
        $params = array_merge_recursive([], $params, $params['driverOptions'] ?? []);

        invariant(array_key_exists('user', $params), 'Missing "user" key.');
        invariant(array_key_exists('password', $params), 'Missing "password" key.');
        invariant(array_key_exists('salesforceInstance', $params), 'Missing "salesforceInstance" key.');
        invariant(array_key_exists('apiVersion', $params), 'Missing "apiVersion" key.');
        invariant(array_key_exists('consumerKey', $params), 'Missing "consumerKey" key.');
        invariant(array_key_exists('consumerSecret', $params), 'Missing "consumerSecret" key.');

        if (array_key_exists('authorizedClientFactory', $params)) {
            invariant(
                $params['authorizedClientFactory'] instanceof AuthorizedClientFactory,
                '$params.authorizedClientFactory must be an instance of AuthorizedClientFactory.',
            );

            return $params['authorizedClientFactory'];
        }

        return new HttpAuthorizedClientFactory(
            new HttpAccessTokenFactory(
                $params['salesforceInstance'],
                $params['apiVersion'],
                $params['consumerKey'],
                $params['consumerSecret'],
                $params['user'],
                $params['password'],
            ),
            $params['salesforceInstance'],
            $params['apiVersion'],
        );
    }
}
