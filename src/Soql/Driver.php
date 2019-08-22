<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Doctrine\DBAL\Driver\Connection;

class Driver extends AbstractSoqlDriver
{
    public function connect(array $params, $username = null, $password = null, array $driverOptions = []) : Connection
    {
        return new SoqlConnection($params, (string) $username, (string) $password);
    }

    public function getName() : string
    {
        return 'soql';
    }
}
