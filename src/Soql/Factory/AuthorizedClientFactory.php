<?php

declare(strict_types=1);

namespace Codelicia\Soql\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use function sprintf;

final class AuthorizedClientFactory implements AuthorizedClientFactoryInterface
{
    /** @var AccessTokenFactoryInterface */
    private $accessTokenFactory;

    /** @var string */
    private $salesforceInstance;

    public function __construct(AccessTokenFactoryInterface $accessTokenFactory, string $salesforceInstance)
    {
        $this->accessTokenFactory = $accessTokenFactory;
        $this->salesforceInstance = $salesforceInstance;
    }

    public function __invoke() : ClientInterface
    {
        return new Client([
            'base_uri' => $this->salesforceInstance,
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $this->accessTokenFactory->__invoke()),
                'X-PrettyPrint' => '1',
                'Content-Type' => 'application/json',
            ],
        ]);
    }
}
