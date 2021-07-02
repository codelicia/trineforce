<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use Codelicia\Soql\ConnectionWrapper;
use Codelicia\Soql\Factory\AuthorizedClientFactory;
use Codelicia\Soql\QueryBuilder;
use Codelicia\Soql\SoqlDriver;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use function file_get_contents;

final class ConnectionWrapperTest extends TestCase
{
    private ConnectionWrapper $connection;
    private ClientInterface $client;
    private ResponseInterface $response;
    private StreamInterface $stream;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->stream = $this->createMock(StreamInterface::class);

        $this->connection = new ConnectionWrapper([
            'salesforceInstance'      => 'it_is_key_to_have_it',
            'apiVersion'              => 'api-version-456',
            'user'                    => 'foo',
            'password'                => 'foo',
            'consumerKey'             => 'foo',
            'consumerSecret'          => 'foo',
            'authorizedClientFactory' => $this->httpResponseMock(),
        ], new SoqlDriver());
    }

    /** @test */
    public function it_is_using_the_right_api_version(): void
    {
        $this->client->expects(self::once())->method('send')
            ->with(self::callback(static function (Request $request) : bool {
                self::assertSame('/services/data/api-version-456/sobjects/User', $request->getUri()->getPath());

                return true;
            }))
            ->willReturn($this->response);

        $this->response->expects(self::exactly(2))->method('getBody')->willReturn($this->stream);
        $this->response->expects(self::exactly(2))->method('getBody')->willReturn($this->stream);

        $this->stream->expects(self::once())->method('rewind');
        $this->stream->expects(self::once())->method('getContents')
            ->willReturn(file_get_contents(__DIR__ . '/../fixtures/generic_success.json'));

        $this->connection->insert(
            'User',
            ['Name' => 'Pay as you go Opportunity'],
            ['Id' => 123],
        );
    }

    /** @test */
    public function insert_with_no_transaction(): void
    {
        $this->client->expects(self::once())->method('send')
            ->with(self::assertHttpHeaderIsPropagated())
            ->willReturn($this->response);

        $this->response->expects(self::exactly(2))->method('getBody')->willReturn($this->stream);

        $this->stream->expects(self::once())->method('rewind');
        $this->stream->expects(self::once())->method('getContents')
            ->willReturn(file_get_contents(__DIR__ . '/../fixtures/generic_success.json'));

        $this->connection->insert(
            'User',
            ['Name' => 'Pay as you go Opportunity'],
            ['Id' => 123],
            ['X-Unit-Testing' => 'Yes']
        );
    }

    /** @test */
    public function update_with_no_transaction(): void
    {
        $this->client->expects(self::once())->method('send')
            ->with(self::assertHttpHeaderIsPropagated())
            ->willReturn($this->response);

        $this->response->expects(self::once())->method('getBody')->willReturn($this->stream);
        $this->response->expects(self::once())->method('getStatusCode')->willReturn(204);

        $this->stream->expects(self::once())->method('rewind');

        $this->connection->update(
            'User',
            ['Name' => 'Pay as you go Opportunity'],
            ['Id' => 123],
            [],
            ['X-Unit-Testing' => 'Yes']
        );
    }

    /** @test */
    public function delete_with_no_transaction(): void
    {
        $this->client->expects(self::once())->method('send')
            ->with(self::assertHttpHeaderIsPropagated())
            ->willReturn($this->response);

        $this->response->expects(self::once())->method('getBody')->willReturn($this->stream);

        $this->stream->expects(self::once())->method('rewind');

        $this->connection->delete(
            'User',
            ['Id' => 123],
            ['ref' => '1234'],
            ['X-Unit-Testing' => 'Yes']
        );
    }

    /** @test */
    public function transactional_state(): void
    {
        $connection = new ConnectionWrapper([], new SoqlDriver());

        self::assertFalse($connection->isTransactionActive());

        $connection->beginTransaction();
        self::assertTrue($connection->isTransactionActive());

        $connection->rollBack();
        self::assertFalse($connection->isTransactionActive());
    }

    /** @test */
    public function transactional_should_throws_exception_when_error_occurs(): void
    {
        $this->client->expects(self::once())->method('send')->willReturn($this->response);

        $this->response->expects(self::exactly(2))->method('getBody')->willReturn($this->stream);

        $this->stream->expects(self::once())->method('rewind');
        $this->stream->expects(self::once())->method('getContents')
            ->willReturn(file_get_contents(__DIR__ . '/../fixtures/composite_error.json'));

        self::assertFalse($this->connection->isTransactionActive());

        $this->connection->beginTransaction();
        $this->connection->update('Foo', ['Name' => 'New-Name'], ['Id' => '123']);

        $this->expectExceptionMessage('Transaction failed with messages: '
            . '[["duplicate value found: ExternalId__c duplicates value on record with id: 0010E00000eg3gH"],'
            . '["The transaction was rolled back since another operation in the same transaction failed."]]');

        $this->connection->commit();
    }

    private static function assertHttpHeaderIsPropagated(): Callback
    {
        return self::callback(static function (Request $request) : bool {
            self::assertSame(['X-Unit-Testing' => ['Yes']], $request->getHeaders());

            return true;
        });
    }

    private function httpResponseMock(): AuthorizedClientFactory
    {
        return new class($this->client) implements AuthorizedClientFactory {
            private ClientInterface $client;

            public function __construct(ClientInterface $client)
            {
                $this->client = $client;
            }

            public function __invoke(): ClientInterface
            {
                return $this->client;
            }
        };
    }
}
