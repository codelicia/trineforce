<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use BadMethodCallException;
use Codelicia\Soql\ConnectionWrapper;
use Codelicia\Soql\QueryBuilder;
use Codelicia\Soql\SoqlDriver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use PHPUnit\Framework\TestCase;

final class ConnectionWrapperTest extends TestCase
{
    /** @test */
    public function query_builder() : void
    {
        $connection = new ConnectionWrapper([], new SoqlDriver());

        self::assertInstanceOf(QueryBuilder::class, $connection->createQueryBuilder());
    }

    /** @test */
    public function transactional_state() : void
    {
        $connection = new ConnectionWrapper([], new SoqlDriver());

        self::assertFalse($connection->isTransactionActive());

        $connection->beginTransaction();
        self::assertTrue($connection->isTransactionActive());

        $connection->rollBack();
        self::assertFalse($connection->isTransactionActive());
    }
}
