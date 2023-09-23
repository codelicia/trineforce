<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use Codelicia\Soql\Driver\Result;
use Codelicia\Soql\Factory\AuthorizedClientFactory;
use Codelicia\Soql\SoqlConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SoqlConnectionTest extends TestCase
{
    private AuthorizedClientFactory|MockObject $authorizedClientFactory;

    protected function setUp(): void
    {
        $this->authorizedClientFactory = $this->createMock(AuthorizedClientFactory::class);
    }

    #[Test]
    public function query(): void
    {
        $sut       = new SoqlConnection($this->authorizedClientFactory);
        $statement = $sut->query('SELECT Id From Contact');

        self::assertInstanceOf(Result::class, $statement);
    }

    #[Test]
    public function quote(): void
    {
        $sut = new SoqlConnection($this->authorizedClientFactory);
        self::assertSame(
            '\'\"Sams\\\' son is in singing a sunny song\"\'',
            $sut->quote('"Sams\' son is in singing a sunny song"'),
        );
    }

    #[Test]
    public function asserts_on_default_values(): void
    {
        $sut = new SoqlConnection($this->authorizedClientFactory);

        self::assertTrue($sut->beginTransaction());
        self::assertTrue($sut->commit());
        self::assertTrue($sut->rollBack());
    }
}
