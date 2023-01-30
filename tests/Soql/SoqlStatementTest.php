<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use Codelicia\Soql;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

final class SoqlStatementTest extends TestCase
{
    /** @test */
    public function it_should_convert_positional_to_named_placeholders() : void
    {
        $result = new Soql\SoqlStatement(
            new Client(),
            'SELECT Id FROM Contact WHERE Name = ? AND Surname = ?'
        );

        self::assertSame(
            'SELECT Id FROM Contact WHERE Name = :param1 AND Surname = :param2',
            $result->execute()->getSql()
        );

        self::assertSame(
            "SELECT Id FROM Contact WHERE Name = 'name' AND Surname = 'malukenho'",
            $result->execute(['param1' => 'name', 'param2' => 'malukenho'])->getSql()
        );
    }

    /** @test */
    public function it_should_bind_named_values() : void
    {
        $client = new Client();
        $sql    = 'SELECT Id FROM Contact WHERE Name = :name AND Surname = :surname';
        $sut    = new Soql\SoqlStatement($client, $sql);

        $sut->bindValue('name', 'John');
        $sut->bindValue('surname', 'Smith');

        self::assertSame(
            "SELECT Id FROM Contact WHERE Name = 'John' AND Surname = 'Smith'",
            $sut->execute()->getSql()
        );
    }

    /** @test */
    public function it_should_bind_named_params() : void
    {
        $sql    = 'SELECT Id FROM Contact WHERE Name = :name AND Surname = :surname';
        $sut    = new Soql\SoqlStatement(new Client(), $sql);

        $name = 'John';
        $sut->bindParam('name', $name);

        $surname = 'Smith';
        $sut->bindParam('surname', $surname);

        self::assertSame(
            "SELECT Id FROM Contact WHERE Name = 'John' AND Surname = 'Smith'",
            $sut->execute()->getSql()
        );
    }
}
