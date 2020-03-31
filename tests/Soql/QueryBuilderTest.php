<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use BadMethodCallException;
use Codelicia\Soql\QueryBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    /** @test */
    public function join() : void
    {
        $connection   = $this->createMock(Connection::class);
        $connection->expects(self::atLeastOnce())
            ->method('getDatabasePlatform')
            ->willReturn(new MySqlPlatform());

        $queryBuilder = new QueryBuilder($connection);

        self::assertSame(
            'SELECT Id, (SELECT Name FROM Contact) FROM Account',
            $queryBuilder->select('Id')
                ->from('Account')
                ->join('Contact', ['Name'])
                ->getSQL()
        );

        self::assertSame(
            'SELECT Id, (SELECT Name FROM Contact LIMIT 1) FROM Account LIMIT 1',
            $queryBuilder->select('Id')
                ->from('Account')
                ->join('Contact', ['Name'], '', 'LIMIT 1')
                ->setMaxResults(1)
                ->getSQL()
        );

        self::assertSame(
            'SELECT Id, (SELECT Name FROM Contact WHERE Id = :id) FROM Account LIMIT 1',
            $queryBuilder->select('Id')
                ->from('Account')
                ->join('Contact', ['Name'], 'Id = :id')
                ->setParameter('id', '123')
                ->setMaxResults(1)
                ->getSQL()
        );
    }

    /** @test */
    public function it_should_urlencode_string_parameters() : void
    {
        $phone = '+(000) 0000-0000';
        $queryBuilder = (new QueryBuilder($this->createMock(Connection::class)))
            ->setParameter('phone', $phone);

        self::assertSame(urlencode($phone), $queryBuilder->getParameter('phone'));
    }

    /** @test */
    public function it_should_not_urlencode_non_string_parameters() : void
    {
        $queryBuilder = (new QueryBuilder($this->createMock(Connection::class)))
            ->setParameter('age', 12);

        self::assertSame(12, $queryBuilder->getParameter('age'));
    }

    /** @test */
    public function it_should_deny_left_join_method_call() : void
    {
        $queryBuilder = new QueryBuilder($this->createMock(Connection::class));

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('"Codelicia\Soql\QueryBuilder::leftJoin" method call is not allowed, use "join" instead.');
        $queryBuilder->leftJoin('tableAlias', 'join', 'alas');
    }

    /** @test */
    public function it_should_deny_right_join_method_call() : void
    {
        $queryBuilder = new QueryBuilder($this->createMock(Connection::class));

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('"Codelicia\Soql\QueryBuilder::rightJoin" method call is not allowed, use "join" instead.');
        $queryBuilder->rightJoin('tableAlias', 'join', 'alas');
    }

    /** @test */
    public function it_should_deny_inner_join_method_call() : void
    {
        $queryBuilder = new QueryBuilder($this->createMock(Connection::class));

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('"Codelicia\Soql\QueryBuilder::innerJoin" method call is not allowed, use "join" instead.');
        $queryBuilder->innerJoin('tableAlias', 'join', 'alas');
    }
}
