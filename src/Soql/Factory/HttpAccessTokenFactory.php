<?php

/** @noinspection PhpPropertyOnlyWrittenInspection */

declare(strict_types=1);

namespace Codelicia\Soql\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class HttpAccessTokenFactory implements AccessTokenFactory
{
    private string|null $accessToken;

    public function __construct(
        private readonly string $salesforceInstance,
        private readonly string $apiVersion,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
        private readonly string $username,
        private readonly string $password,
    ) {
        $this->accessToken = null;
    }

    public function __invoke(ClientInterface|null $client = null): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $client = $client ?: new Client(['base_uri' => $this->salesforceInstance]);

        $options = [
            'form_params' => [
                'grant_type'    => 'password',
                'client_id'     => $this->consumerKey,
                'client_secret' => $this->consumerSecret,
                'username'      => $this->username,
                'password'      => $this->password,
            ],
        ];

        $response     = $client->request('POST', '/services/oauth2/token', $options);
        $authResponse = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        return $this->accessToken = $authResponse['access_token'];
    }
}
