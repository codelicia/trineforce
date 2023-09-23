<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use Codelicia\Soql\ConnectionWrapper;
use Codelicia\Soql\Factory\Http\RequestThrottler;
use Codelicia\Soql\SoqlDriver;
use CodeliciaTest\Soql\Stubs\ConnectionWrapperFactory;
use Doctrine\DBAL\Logging\SQLLogger;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
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
        $this->client   = $this->createMock(ClientInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->stream   = $this->createMock(StreamInterface::class);

        $this->response
            ->method('getHeaderLine')
            ->with(RequestThrottler::HEADER)
            ->willReturn('api-usage=1/100');

        $this->connection = ConnectionWrapperFactory::create($this->client);
    }

    #[Test]
    public function it_is_using_the_right_api_version(): void
    {
        $this->client->expects(self::once())->method('send')
            ->with(self::callback(static function (Request $request): bool {
                self::assertSame('/services/data/api-version-456/sobjects/User', $request->getUri()->getPath());

                return true;
            }))
            ->willReturn($this->response);

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

    #[Test]
    public function insert_with_no_transaction(): void
    {
        $this->client->expects(self::once())->method('send')
            ->with(self::assertHttpHeaderIsPropagated())
            ->willReturn($this->response);

        $this->response->expects(self::exactly(2))->method('getBody')->willReturn($this->stream);

        $this->stream->expects(self::once())->method('rewind');
        $this->stream->expects(self::once())->method('getContents')
            ->willReturn(file_get_contents(__DIR__ . '/../fixtures/generic_success.json'));

        $result = $this->connection->insert(
            'User',
            ['Name' => 'Pay as you go Opportunity'],
            ['Id' => 123],
            ['X-Unit-Testing' => 'Yes'],
        );

        self::assertSame(1, $result);
    }

    #[Test]
    public function update_with_no_transaction(): void
    {
        $this->client->expects(self::once())->method('send')
            ->with(self::assertHttpHeaderIsPropagated())
            ->willReturn($this->response);

        $this->response->expects(self::once())->method('getBody')->willReturn($this->stream);
        $this->response->expects(self::once())->method('getStatusCode')->willReturn(204);

        $this->stream->expects(self::once())->method('rewind');

        $result = $this->connection->update(
            'User',
            ['Name' => 'Pay as you go Opportunity'],
            ['Id' => 123],
            [],
            ['X-Unit-Testing' => 'Yes'],
        );

        self::assertSame(1, $result);
    }

    #[Test]
    public function it_should_deal_with_decoding_empty_string(): void
    {
        $this->connection->getConfiguration()->setSQLLogger(new class () implements SQLLogger {
            public function startQuery($sql, array|null $params = null, array|null $types = null)
            {
            }

            public function stopQuery()
            {
            }
        });

        $this->client->expects(self::once())->method('send')
            ->with(self::assertHttpHeaderIsPropagated())
            ->willReturn($this->response);

        $this->response->expects(self::exactly(2))->method('getBody')->willReturn($this->stream);

        $this->stream->expects(self::once())->method('getContents')->willReturn('');
        $this->stream->expects(self::once())->method('rewind');

        $result = $this->connection->delete(
            'User',
            ['Id' => 123],
            ['ref' => '1234'],
            ['X-Unit-Testing' => 'Yes'],
        );

        self::assertSame(1, $result);
    }

    #[Test]
    public function delete_with_no_transaction(): void
    {
        $this->client->expects(self::once())->method('send')
            ->with(self::callback(static function (Request $req): bool {
                self::assertSame(['X-Unit-Testing' => ['Yes']], $req->getHeaders());
                self::assertSame('DELETE', $req->getMethod());
                self::assertSame(
                    '/services/data/api-version-456/sobjects/User/123',
                    $req->getUri()->getPath(),
                );

                return true;
            }))
            ->willReturn($this->response);

        $this->response->expects(self::once())->method('getBody')->willReturn($this->stream);

        $this->stream->expects(self::once())->method('rewind');

        $this->connection->delete(
            'User',
            ['Id' => 123],
            ['ref' => '1234'],
            ['X-Unit-Testing' => 'Yes'],
        );
    }

    #[Test]
    public function transactional_state(): void
    {
        $connection = new ConnectionWrapper([], new SoqlDriver());

        self::assertFalse($connection->isTransactionActive());

        $connection->beginTransaction();
        self::assertTrue($connection->isTransactionActive());

        $connection->rollBack();
        self::assertFalse($connection->isTransactionActive());
    }

    #[Test]
    public function transactional_should_throws_exception_when_error_occurs(): void
    {
        $this->client->expects(self::once())->method('send')
            ->with(self::callback(static function (Request $request): bool {
                self::assertSame('/services/data/api-version-456/composite', $request->getUri()->getPath());

                return true;
            }))
            ->willReturn($this->response);

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

    #[Test]
    public function upsert_with_no_transaction(): void
    {
        $this->client->expects(self::once())->method('send')
            ->with(self::callback(static function (Request $req): bool {
                self::assertSame(['X-Unit-Testing' => ['Yes']], $req->getHeaders());
                self::assertSame('PATCH', $req->getMethod());
                self::assertSame(
                    '/services/data/api-version-456/sobjects/User/ExternalId__c/12345',
                    $req->getUri()->getPath(),
                );

                return true;
            }))
            ->willReturn($this->response);

        $this->response->expects(self::once())->method('getBody')->willReturn($this->stream);
        $this->response->expects(self::once())->method('getStatusCode')->willReturn(201);

        $this->stream->expects(self::once())->method('rewind');

        $this->connection->upsert(
            'User',
            'ExternalId__c',
            '12345',
            ['Name' => 'Pay as you go Opportunity'],
            [],
            ['X-Unit-Testing' => 'Yes'],
        );
    }

    private static function assertHttpHeaderIsPropagated(): Callback
    {
        return self::callback(static function (Request $request): bool {
            self::assertSame(['X-Unit-Testing' => ['Yes']], $request->getHeaders());

            return true;
        });
    }
}
