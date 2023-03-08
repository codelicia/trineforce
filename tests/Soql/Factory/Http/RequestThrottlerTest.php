<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql\Factory\Http;

use Codelicia\Soql\ConnectionWrapper;
use Codelicia\Soql\Factory\Http\Exception\RequestThrottlingException;
use Codelicia\Soql\Factory\Http\RequestThrottler;
use CodeliciaTest\Soql\Stubs;
use Doctrine\DBAL\Exception;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class RequestThrottlerTest extends TestCase
{
    private ConnectionWrapper $connection;
    private ClientInterface $client;
    private ResponseInterface $response;
    private StreamInterface $stream;

    /** @throws Exception */
    protected function setUp(): void
    {
        $this->client     = $this->createMock(ClientInterface::class);
        $this->response   = $this->createMock(ResponseInterface::class);
        $this->stream     = $this->createMock(StreamInterface::class);
        $this->connection = Stubs\ConnectionWrapperFactory::create($this->client);
    }

    #[Test]
    public function it_should_be_able_to_resolve_throttling_header(): void
    {
        $this->client->method('send')->with(self::anything())->willReturn($this->response);

        $this->response->method('getBody')->willReturn($this->stream);
        $this->response
            ->expects(self::once())
            ->method('getHeaderLine')
            ->with(RequestThrottler::HEADER)
            ->willReturn('api-usage=1/100');

        $this->stream->method('rewind');
        $this->stream->method('getContents')->willReturn('{"success": true}');

        $this->connection->insert('User', ['Name' => 'Pay as you go Opportunity'], ['Id' => 123]);
    }

    #[Test]
    public function it_should_be_able_to_resolve_overflowed_throttling(): void
    {
        $this->client->expects(self::once())->method('send')->with(self::anything())->willReturn($this->response);

        $this->response
            ->expects(self::once())
            ->method('getHeaderLine')
            ->with(RequestThrottler::HEADER)
            ->willReturn('api-usage=100/100');

        $this->expectException(RequestThrottlingException::class);
        $this->expectExceptionMessage('Failed because you\'ve used "100" from a total of "100" requests.');

        $this->connection->insert('User', ['Name' => 'Pay as you go Opportunity'], ['Id' => 123]);
    }
}
