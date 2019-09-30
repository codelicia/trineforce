<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Doctrine\DBAL\Connection;

class ConnectionWrapper extends Connection
{
    public function createQueryBuilder() : QueryBuilder
    {
        return new QueryBuilder($this);
    }
}
