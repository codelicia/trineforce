<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use BadMethodCallException;
use Codelicia\Soql\ConnectionWrapper;
use Codelicia\Soql\Factory\Http\RequestThrottler;
use Codelicia\Soql\QueryBuilder;
use Codelicia\Soql\SoqlConnection;
use Codelicia\Soql\SoqlDriver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

use function urlencode;

final class QueryBuilderTest extends TestCase
{
    #[Test]
    public function join(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::atLeastOnce())
            ->method('getDatabasePlatform')
            ->willReturn(new MySQLPlatform());

        $queryBuilder = new QueryBuilder($connection);

        self::assertSame(
            'SELECT Id, (SELECT Name FROM Contact) FROM Account',
            $queryBuilder->select('Id')
                ->from('Account')
                ->join('Contact', ['Name'])
                ->getSQL(),
        );

        self::assertSame(
            'SELECT Id, (SELECT Name FROM Contact LIMIT 1) FROM Account LIMIT 1',
            $queryBuilder->select('Id')
                ->from('Account')
                ->join('Contact', ['Name'], '', 'LIMIT 1')
                ->setMaxResults(1)
                ->getSQL(),
        );

        self::assertSame(
            'SELECT Id, (SELECT Name FROM Contact WHERE Id = :id) FROM Account LIMIT 1',
            $queryBuilder->select('Id')
                ->from('Account')
                ->join('Contact', ['Name'], 'Id = :id')
                ->setParameter('id', '123')
                ->setMaxResults(1)
                ->getSQL(),
        );
    }

    #[Test]
    public function execute_should_bind_values_to_query(): void
    {
        $connection = new ConnectionWrapper([], $soqlDriver = $this->createMock(SoqlDriver::class));
        $soqlDriver->expects(self::atLeastOnce())
            ->method('getDatabasePlatform')
            ->willReturn(new MySQLPlatform());

        $soqlDriver->expects(self::atLeastOnce())
            ->method('connect')
            ->willReturn($this->createMock(SoqlConnection::class));

        $queryBuilder = new QueryBuilder($connection);

        self::assertSame(
            'SELECT Id, (SELECT Name FROM Contact WHERE Id = 123) FROM Account LIMIT 1',
            $queryBuilder->select('Id')
                ->from('Account')
                ->join('Contact', ['Name'], 'Id = :id')
                ->setParameter('id', 123)
                ->setMaxResults(1)
                ->executeQuery()
                ->getDriverResult()
                ->getSql(),
        );
    }

    #[Test]
    public function execute_should_bind_values_to_query_within_the_inner_join(): void
    {
        $connection = new ConnectionWrapper([], $soqlDriver = $this->createMock(SoqlDriver::class));
        $soqlDriver->expects(self::atLeastOnce())
            ->method('getDatabasePlatform')
            ->willReturn(new MySQLPlatform());

        $soqlDriver->expects(self::atLeastOnce())
            ->method('connect')
            ->willReturn($this->createMock(SoqlConnection::class));

        $queryBuilder = new QueryBuilder($connection);

        self::assertSame(
            'SELECT Id, (SELECT Name FROM Contact WHERE Id = \'123\') FROM Account LIMIT 1',
            $queryBuilder->select('Id')
                ->from('Account')
                ->join('Contact', ['Name'], 'Id = :id')
                ->setParameter('id', '123')
                ->setMaxResults(1)
                ->executeQuery()
                ->getDriverResult()
                ->getSql(),
        );
    }

    #[Test]
    public function fetch_should_bind_values(): void
    {
        $connection = new ConnectionWrapper([], $soqlDriver = $this->createMock(SoqlDriver::class));

        $soqlDriver->expects(self::atLeastOnce())
            ->method('getDatabasePlatform')
            ->willReturn(new MySQLPlatform());

        $soqlDriver->expects(self::atLeastOnce())
            ->method('connect')
            ->willReturn($client = $this->createMock(SoqlConnection::class));

        $client->expects(self::once())
            ->method('getNativeConnection')
            ->willReturn($httpClient = $this->createMock(Client::class));

        $httpClient->expects(self::once())
            ->method('getConfig')
            ->with('apiVersion')
            ->willReturn('v80.0');

        $httpClient->expects(self::once())
            ->method('request')
            ->with('GET', '/services/data/v80.0/query?q=SELECT Id, (SELECT Name FROM Contact WHERE Id = \'123\') FROM Account LIMIT 1')
            ->willReturn($response = $this->createMock(ResponseInterface::class));

        $response
            ->method('getHeaderLine')
            ->with(RequestThrottler::HEADER)
            ->willReturn('api-usage=1/100');

        $response->expects(self::once())
            ->method('getBody')
            ->willReturn($stream = $this->createMock(StreamInterface::class));

        $stream->expects(self::once())
            ->method('getContents')
            ->willReturn(/** @lang JSON */ '{
  "done": true,
  "totalSize": 123,
  "records": [1,2,3]
}');

        $queryBuilder = new QueryBuilder($connection);

        self::assertSame(
            [1, 2, 3],
            $queryBuilder->select('Id')
                ->from('Account')
                ->join('Contact', ['Name'], 'Id = :id')
                ->setParameter('id', '123')
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAllAssociative(),
        );
    }

    #[Test]
    public function it_should_url_encode_string_parameters(): void
    {
        $phone        = '+(000) 0000-0000';
        $queryBuilder = (new QueryBuilder($this->createMock(Connection::class)))
            ->setParameter('phone', $phone);

        self::assertSame(urlencode($phone), $queryBuilder->getParameter('phone'));
    }

    #[Test]
    public function it_should_not_url_encode_non_string_parameters(): void
    {
        $queryBuilder = (new QueryBuilder($this->createMock(Connection::class)))
            ->setParameter('age', 12);

        self::assertSame(12, $queryBuilder->getParameter('age'));
    }

    #[Test]
    public function it_should_deny_left_join_method_call(): void
    {
        $queryBuilder = new QueryBuilder($this->createMock(Connection::class));

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('"Codelicia\Soql\QueryBuilder::leftJoin" method call is not allowed, use "join" instead.');
        $queryBuilder->leftJoin('tableAlias', 'join', 'alas');
    }

    #[Test]
    public function it_should_deny_right_join_method_call(): void
    {
        $queryBuilder = new QueryBuilder($this->createMock(Connection::class));

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('"Codelicia\Soql\QueryBuilder::rightJoin" method call is not allowed, use "join" instead.');
        $queryBuilder->rightJoin('tableAlias', 'join', 'alas');
    }

    #[Test]
    public function it_should_deny_inner_join_method_call(): void
    {
        $queryBuilder = new QueryBuilder($this->createMock(Connection::class));

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('"Codelicia\Soql\QueryBuilder::innerJoin" method call is not allowed, use "join" instead.');
        $queryBuilder->innerJoin('tableAlias', 'join', 'alas');
    }
}
