<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Assert\Assertion;
use Assert\AssertionFailedException;
use Doctrine\DBAL\Driver\Connection;

class Driver extends AbstractSoqlDriver
{
    /** @throws AssertionFailedException */
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
}
