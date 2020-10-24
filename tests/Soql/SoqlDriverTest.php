<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use Codelicia\Soql\DriverError;
use Codelicia\Soql\SoqlDriver;
use Codelicia\Soql\SoqlError;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SoqlDriverTest extends TestCase
{
    /** @test */
    public function it_should_fail_if_null_password_or_username_is_provided(): void
    {
        $driver = new SoqlDriver();

        $this->expectException(InvalidArgumentException::class);
        $driver->connect([], null, null, ['foo' => 'bar']);
    }

    /** @test */
    public function it_should_return_driver_details(): void
    {
        $driver = new SoqlDriver();

        self::assertSame('soql', $driver->getName());
        self::assertNull($driver->getDatabase($this->createMock(Connection::class)));
        self::assertInstanceOf(DriverError::class, $driver->convertException('exception', new SoqlError('soql_exception')));
        self::assertInstanceOf(MySqlPlatform::class, $driver->getDatabasePlatform());
    }
}
