<?php

declare(strict_types=1);

namespace Codelicia\Soql\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

use function sprintf;

final class HttpAuthorizedClientFactory implements AuthorizedClientFactory
{
    public function __construct(
        private AccessTokenFactory $accessTokenFactory,
        private string $salesforceInstance,
        private string $apiVersion,
    ) {
    }

    public function __invoke(): ClientInterface
    {
        return new Client([
            'base_uri' => $this->salesforceInstance,
            'apiVersion' => $this->apiVersion,
            'headers' => [
                'Authorization'      => sprintf('Bearer %s', $this->accessTokenFactory->__invoke()),
                'X-PrettyPrint'      => '1',
                'Content-Type'       => 'application/json',
                'Sforce-Auto-Assign' => 'FALSE',
            ],
        ]);
    }
}
