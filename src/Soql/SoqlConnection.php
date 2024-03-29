<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Codelicia\Soql\Driver\Result;
use Codelicia\Soql\Factory\AuthorizedClientFactory;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use GuzzleHttp\ClientInterface;
use PDO;

use function addslashes;

class SoqlConnection implements ServerInfoAwareConnection
{
    public function __construct(private AuthorizedClientFactory $authorizedClientFactory)
    {
    }

    /** {@inheritDoc} */
    public function prepare(string $sql): SoqlStatement
    {
        return new SoqlStatement($this->getNativeConnection(), $sql);
    }

    public function getNativeConnection(): ClientInterface
    {
        return $this->authorizedClientFactory->__invoke();
    }

    /** {@inheritDoc} */
    public function query(string $sql): ResultInterface
    {
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return new Result($stmt);
    }

    /** {@inheritDoc} */
    public function quote($input, $type = PDO::PARAM_STR): string
    {
        return "'" . addslashes($input) . "'";
    }

    /** {@inheritDoc} */
    public function exec(string $sql): int
    {
        // TODO: Look in the payload
        if ($this->connection->query($sql) === false) {
            throw new SoqlError($this->connection->error, $this->connection->sqlstate, $this->connection->errno);
        }

        return $this->connection->affected_rows;
    }

    /** {@inheritDoc} */
    public function lastInsertId($name = null): string
    {
        return $this->connection->insert_id;
    }

    /** {@inheritDoc} */
    public function beginTransaction(): bool
    {
        return true;
    }

    /** {@inheritDoc} */
    public function commit(): bool
    {
        return true;
    }

    /** {@inheritDoc} */
    public function rollBack(): bool
    {
        return true;
    }

    public function getServerVersion(): string
    {
        return $this->getNativeConnection()->getConfig('apiVersion');
    }
}
