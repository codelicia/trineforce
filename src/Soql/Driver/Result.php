<?php

declare(strict_types=1);

namespace Codelicia\Soql\Driver;

use Codelicia\Soql\SoqlStatement;
use Doctrine\DBAL\Driver\Result as DriverResultInterface;
use PDO;
use PDOException;

final readonly class Result implements DriverResultInterface
{
    public function __construct(private SoqlStatement $statement)
    {
    }

    /** {@inheritDoc} */
    public function fetchNumeric()
    {
        return $this->fetch(PDO::FETCH_NUM);
    }

    /** {@inheritDoc} */
    public function fetchAssociative()
    {
        return $this->fetch(PDO::FETCH_ASSOC);
    }

    /** {@inheritDoc} */
    public function fetchOne()
    {
        return $this->fetch(PDO::FETCH_COLUMN);
    }

    /** {@inheritDoc} */
    public function fetchAllNumeric(): array
    {
        return $this->fetchAll(PDO::FETCH_NUM);
    }

    /** {@inheritDoc} */
    public function fetchAllAssociative(): array
    {
        return $this->fetchAll(PDO::FETCH_ASSOC);
    }

    /** {@inheritDoc} */
    public function fetchFirstColumn(): array
    {
        return $this->fetchAll(PDO::FETCH_COLUMN);
    }

    public function rowCount(): int
    {
        try {
            return $this->statement->rowCount();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function columnCount(): int
    {
        try {
            return $this->statement->columnCount();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function free(): void
    {
        // left intentionally blank
    }

    private function fetchAll()
    {
        try {
            $data = $this->statement->fetchAll()->getResults();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }

        return $data;
    }

    private function fetch()
    {
        try {
            return $this->statement->fetch();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function getSql(): string
    {
        return $this->statement->getSql();
    }
}
