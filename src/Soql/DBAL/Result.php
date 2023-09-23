<?php

declare(strict_types=1);

namespace Codelicia\Soql\DBAL;

use Codelicia\Soql\Driver\Result as SoqlDriverResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Result as DBALResult;

use function Psl\invariant;

final class Result extends DBALResult
{
    private DriverResult $storedResult;

    public function __construct(DriverResult $result, Connection $connection)
    {
        $this->storedResult = $result;

        parent::__construct($result, $connection);
    }

    public function getDriverResult(): SoqlDriverResult
    {
        invariant($this->storedResult instanceof SoqlDriverResult, 'storedResult holds an wrong type class.');

        return $this->storedResult;
    }
}
