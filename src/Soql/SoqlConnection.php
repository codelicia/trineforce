<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Codelicia\Soql\Factory\AuthorizedClientFactoryInterface;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\ParameterType;
use function addslashes;
use function func_get_args;

class SoqlConnection implements Connection
{
    /** @var AuthorizedClientFactoryInterface */
    private $authorizedClientFactory;

    public function __construct(AuthorizedClientFactoryInterface $authorizedClientFactory)
    {
        $this->authorizedClientFactory = $authorizedClientFactory;
    }

    /** {@inheritDoc} */
    public function prepare($prepareString) : SoqlStatement
    {
        return new SoqlStatement($this->authorizedClientFactory->__invoke(), $prepareString);
    }

    /** {@inheritDoc} */
    public function query() : SoqlStatement
    {
        $args = func_get_args();
        $sql  = $args[0];
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt;
    }

    /** {@inheritDoc} */
    public function quote($input, $type = ParameterType::STRING) : string
    {
        return "'" . addslashes($input) . "'";
    }

    /** {@inheritDoc} */
    public function exec($statement) : int
    {
        // TODO: Look in the payload
        if ($this->connection->query($statement) === false) {
            throw new SoqlError($this->connection->error, $this->connection->sqlstate, $this->connection->errno);
        }

        return $this->connection->affected_rows;
    }

    /** {@inheritDoc} */
    public function lastInsertId($name = null) : string
    {
        return $this->connection->insert_id;
    }

    /** {@inheritDoc} */
    public function beginTransaction() : bool
    {
        return true;
    }

    /** {@inheritDoc} */
    public function commit() : bool
    {
        return true;
    }

    /** {@inheritDoc} */
    public function rollBack() : bool
    {
        return true;
    }

    /** {@inheritdoc} */
    public function errorCode()
    {
        return $this->connection->errno;
    }

    /** {@inheritdoc} */
    public function errorInfo() : array
    {
        return $this->connection->error;
    }
}
