<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use Codelicia\Soql\DriverError;
use Codelicia\Soql\SoqlError;
use PHPUnit\Framework\TestCase;
use function random_int;
use const PHP_INT_MAX;

final class DriverErrorTest extends TestCase
{
    /** @test */
    public function it_should_retain_exception_instance() : void
    {
        $expectedMessage   = 'The Driver Has Some Error';
        $expectedErrorCode = random_int(1, PHP_INT_MAX);

        $stackedException = new SoqlError('Wrong Message', null, $expectedErrorCode);

        $exception = new DriverError($expectedMessage, $stackedException);

        self::assertSame($expectedMessage, $exception->getMessage());
        self::assertSame($expectedErrorCode, $exception->getErrorCode());
        self::assertSame(0, $exception->getCode());
    }
}
