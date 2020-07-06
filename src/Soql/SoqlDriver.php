<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Assert\Assertion;
use Assert\AssertionFailedException;
use Codelicia\Soql\Factory\AuthorizedClientFactory;
use Codelicia\Soql\Factory\HttpAccessTokenFactory;
use Codelicia\Soql\Factory\HttpAuthorizedClientFactory;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Platforms\MySqlPlatform;

use function array_key_exists;

class SoqlDriver implements Driver, ExceptionConverterDriver
{
    /**
     * {@inheritDoc}
     *
     * @throws AssertionFailedException
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = []): Connection
    {
        Assertion::notNull($username);
        Assertion::notNull($password);

        return new SoqlConnection($this->getAuthorizedClientFactory($username, $password, $params));
    }

    public function getName(): string
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
    public function getDatabasePlatform(): MySqlPlatform
    {
        return new MySqlPlatform();
    }

    /** {@inheritdoc} */
    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        // TODO: implements specific schema manager
    }

    /** @param string[] $params */
    private function getAuthorizedClientFactory(
        string $username,
        string $password,
        array $params
    ): AuthorizedClientFactory {
        Assertion::keyExists($params, 'salesforceInstance');
        Assertion::keyExists($params, 'consumerKey');
        Assertion::keyExists($params, 'consumerSecret');

        if (array_key_exists('authorizedClientFactory', $params)) {
            Assertion::isInstanceOf($params['authorizedClientFactory'], AuthorizedClientFactory::class);

            return $params['authorizedClientFactory'];
        }

        return new HttpAuthorizedClientFactory(new HttpAccessTokenFactory(
            $params['salesforceInstance'],
            $params['consumerKey'],
            $params['consumerSecret'],
            $username,
            $password
        ), $params['salesforceInstance']);
    }
}
