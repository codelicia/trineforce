<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use Codelicia\Soql;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

final class SoqlStatementTest extends TestCase
{
    /** @test */
    public function it_should_convert_positional_to_named_placeholders(): void
    {
        $result = Soql\SoqlStatement::convertPositionalToNamedPlaceholders(
            'SELECT Id FROM Contact WHERE Name = ? AND Surname = ?'
        );

        self::assertSame(
            [
                'SELECT Id FROM Contact WHERE Name = :param1 AND Surname = :param2',
                [1 => ':param1', 2 => ':param2'],
            ],
            $result
        );
    }

    /** @test */
    public function it_should_not_be_able_to_bind_named_values(): void
    {
        $client = new Client();
        $sql = 'SELECT Id FROM Contact WHERE Name = :name AND Surname = :surname';
        $sut = new Soql\SoqlStatement($client, $sql);

        $this->expectException(Soql\SoqlError::class);
        $this->expectExceptionMessage('SOQL does not support named parameters to queries, use question mark (?) placeholders instead');

        $sut->bindValue(':name', 'John');
    }

    /** @test */
    public function it_should_not_be_able_to_bind_named_params(): void
    {
        $client = new Client();
        $sql = 'SELECT Id FROM Contact WHERE Name = :name AND Sirname = :surname';
        $sut = new Soql\SoqlStatement($client, $sql);

        $this->expectException(Soql\SoqlError::class);
        $this->expectExceptionMessage('SOQL does not support named parameters to queries, use question mark (?) placeholders instead');

        $name = 'John';
        $sut->bindParam(':name', $name);
    }

    /** @test */
    public function it_should_be_able_to_execute_a_query_correctly(): void
    {
        $client = new Client();
        $sql = 'SELECT Id FROM Contact WHERE Name = :name AND Surname = :surname';
        $sut = new Soql\SoqlStatement($client, $sql);

        $result = $sut->execute([':name' => 'John', 'surname' => 'Smith']);

        self::assertTrue($result);
    }
}
