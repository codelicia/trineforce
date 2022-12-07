<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql\Stubs;

use Codelicia\Soql\Factory;
use GuzzleHttp\ClientInterface;

final class AuthorizedClientFactory implements Factory\AuthorizedClientFactory
{
    public function __construct(private readonly ClientInterface $client)
    {
    }

    public function __invoke(): ClientInterface
    {
        return $this->client;
    }
}
