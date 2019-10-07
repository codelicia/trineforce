<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use function count;
use function key;
use function sprintf;

class ConnectionWrapper extends Connection
{
    public function createQueryBuilder() : QueryBuilder
    {
        return new QueryBuilder($this);
    }

    /** {@inheritDoc} */
    public function delete($tableExpression, array $identifier, array $types = []) : void
    {
        if (empty($identifier)) {
            throw InvalidArgumentException::fromEmptyCriteria();
        }

        $this->connect();

        /** @var SoqlConnection $connection */
        $connection = $this->_conn;
        $http       = $connection->getHttpClient();

        if (count($identifier) > 1) {
            throw InvalidArgumentException::notSupported('It should have only one DELETE criteria.');
        }

        $param = $identifier['Id'] ?? (key($identifier) . '/' . $identifier[key($identifier)]);

        $http->request('DELETE', sprintf('/services/data/v20.0/sobjects/%s/%s', $tableExpression, $param));
    }
}
