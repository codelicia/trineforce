<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql\Factory;

use Codelicia\Soql\Factory\HttpAccessTokenFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class HttpAccessTokenFactoryTest extends TestCase
{
    /** @test */
    public function it_should_reuse_access_token_after_authentication() : void
    {
        $factory = new HttpAccessTokenFactory(
            'salesforceInstance',
            'consumerKey',
            'consumerSecret',
            'username',
            'password'
        );

        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('request')->willReturn(
            new Response(200, [], '{"access_token": "s3Gr3D0"}')
        );

        $factory->__invoke($client);
        $factory->__invoke($client);
        $factory->__invoke($client);
        $factory->__invoke($client);
        $factory->__invoke($client);
    }
}
