<?php

declare(strict_types=1);

namespace Codelicia\Soql\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use function sprintf;

final class HttpAuthorizedClientFactory implements AuthorizedClientFactory
{
    /** @var AccessTokenFactory */
    private $accessTokenFactory;

    /** @var string */
    private $salesforceInstance;

    public function __construct(AccessTokenFactory $accessTokenFactory, string $salesforceInstance)
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
                'Sforce-Auto-Assign' => 'FALSE',
            ],
        ]);
    }
}
