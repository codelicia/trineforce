<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use Codelicia\Soql\DriverError;
use Codelicia\Soql\SoqlError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function random_int;

use const PHP_INT_MAX;

final class DriverErrorTest extends TestCase
{
    #[Test]
    public function it_should_retain_exception_instance(): void
    {
        $expectedMessage   = 'The Driver Has Some Error';
        $expectedErrorCode = random_int(1, PHP_INT_MAX);

        $stackedException = new SoqlError($expectedMessage, $expectedErrorCode);

        $exception = DriverError::withException($stackedException);

        self::assertSame('An exception occurred in the driver: ' . $expectedMessage, $exception->getMessage());
        self::assertSame($expectedErrorCode, $exception->getCode());
    }
}
