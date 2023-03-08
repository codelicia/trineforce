<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use Codelicia\Soql\DriverError;
use Codelicia\Soql\SoqlDriver;
use Codelicia\Soql\SoqlError;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;

final class SoqlDriverTest extends TestCase
{
    #[Test]
    public function it_should_fail_if_null_password_or_username_is_provided() : void
    {
        $driver = new SoqlDriver();

        $this->expectException(InvariantViolationException::class);
        $driver->connect([]);
    }

    #[Test]
    public function it_should_return_driver_details() : void
    {
        $driver = new SoqlDriver();

        self::assertSame('soql', $driver->getName());
        self::assertNull($driver->getDatabase($this->createMock(Connection::class)));
        self::assertInstanceOf(DriverError::class, $driver->convert(new SoqlError('soql_exception'), null));
        self::assertInstanceOf(MySQLPlatform::class, $driver->getDatabasePlatform());
    }
}
