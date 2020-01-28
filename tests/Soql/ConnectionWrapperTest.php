<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use Codelicia\Soql\ConnectionWrapper;
use Codelicia\Soql\Factory\AuthorizedClientFactory;
use Codelicia\Soql\QueryBuilder;
use Codelicia\Soql\SoqlDriver;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use function file_get_contents;

final class ConnectionWrapperTest extends TestCase
{
    /** @test */
    public function query_builder() : void
    {
        $connection = new ConnectionWrapper([], new SoqlDriver());

        self::assertInstanceOf(QueryBuilder::class, $connection->createQueryBuilder());
    }

    /** @test */
    public function transactional_state() : void
    {
        $connection = new ConnectionWrapper([], new SoqlDriver());

        self::assertFalse($connection->isTransactionActive());

        $connection->beginTransaction();
        self::assertTrue($connection->isTransactionActive());

        $connection->rollBack();
        self::assertFalse($connection->isTransactionActive());
    }

    /** @test */
    public function transactional_should_throws_exception_when_error_occurs() : void
    {
        $connection = new ConnectionWrapper([
            'salesforceInstance' => 'it_is_key_to_have_it',
            'user' => 'foo',
            'password' => 'foo',
            'consumerKey' => 'foo',
            'consumerSecret' => 'foo',
            'authorizedClientFactory' => $this->httpResponseMock(file_get_contents(__DIR__ . '/../fixtures/composite_error.json')),
        ], new SoqlDriver());

        self::assertFalse($connection->isTransactionActive());

        $connection->beginTransaction();
        $connection->update('Foo', ['Name' => 'New-Name'], ['Id' => '123']);

        $this->expectExceptionMessage('Transaction failed with messages: '
        . '[["duplicate value found: ExternalId__c duplicates value on record with id: 0010E00000eg3gH"],'
        . '["The transaction was rolled back since another operation in the same transaction failed."]]');

        $connection->commit();
    }

    private function httpResponseMock(string $responseString) : AuthorizedClientFactory
    {
        $client   = $this->createMock(ClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $stream    = $this->createMock(StreamInterface::class);

        $client->expects(self::once())->method('send')->willReturn($response);

        $response->expects(self::exactly(2))->method('getBody')->willReturn($stream);

        $stream->expects(self::once())->method('rewind');
        $stream->expects(self::once())->method('getContents')->willReturn($responseString);

        return new class($client) implements AuthorizedClientFactory {
            private ClientInterface $client;

            public function __construct(ClientInterface $client)
            {
                $this->client = $client;
            }

            public function __invoke() : ClientInterface
            {
                return $this->client;
            }
        };
    }


}
