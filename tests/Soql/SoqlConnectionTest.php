<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use Codelicia\Soql\Factory\AuthorizedClientFactory;
use Codelicia\Soql\SoqlConnection;
use Codelicia\Soql\SoqlStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SoqlConnectionTest extends TestCase
{
    /** @var AuthorizedClientFactory|MockObject */
    private $authorizedClientFactory;

    protected function setUp(): void
    {
        $this->authorizedClientFactory = $this->createMock(AuthorizedClientFactory::class);
    }

    /** @test */
    public function query(): void
    {
        $sut = new SoqlConnection($this->authorizedClientFactory);
        $statement = $sut->query('SELECT Id From Contact');

        self::assertInstanceOf(SoqlStatement::class, $statement);
    }

    /** @test */
    public function quote(): void
    {
        $sut = new SoqlConnection($this->authorizedClientFactory);
        self::assertSame(
            '\'\"Sams\\\' son is in singing a sunny song\"\'',
            $sut->quote('"Sams\' son is in singing a sunny song"')
        );
    }

    /** @test */
    public function asserts_on_default_values(): void
    {
        $sut = new SoqlConnection($this->authorizedClientFactory);

        self::assertTrue($sut->beginTransaction());
        self::assertTrue($sut->commit());
        self::assertTrue($sut->rollBack());
    }
}
