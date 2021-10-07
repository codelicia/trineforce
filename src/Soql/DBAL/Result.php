<?php

declare(strict_types=1);

namespace Codelicia\Soql\DBAL;

use Codelicia\Soql\Driver\Result as SoqlDriverResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Result as DBALResult;

use function assert;

class Result extends DBALResult
{
    private DriverResult $result;

    public function __construct(DriverResult $result, Connection $connection)
    {
        parent::__construct($result, $connection);

        $this->result = $result;
    }

    public function getDriverResult(): SoqlDriverResult
    {
        assert($this->result instanceof SoqlDriverResult);

        return $this->result;
    }
}
