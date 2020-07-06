<?php

declare(strict_types=1);

namespace Codelicia\Soql\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

use function json_decode;

final class HttpAccessTokenFactory implements AccessTokenFactory
{
    private string $salesforceInstance;

    private string $consumerKey;

    private string $consumerSecret;

    private string $username;

    private string $password;

    private ?string $accessToken;

    public function __construct(
        string $salesforceInstance,
        string $consumerKey,
        string $consumerSecret,
        string $username,
        string $password
    ) {
        $this->salesforceInstance = $salesforceInstance;
        $this->consumerKey        = $consumerKey;
        $this->consumerSecret     = $consumerSecret;
        $this->username           = $username;
        $this->password           = $password;
        $this->accessToken        = null;
    }

    public function __invoke(?ClientInterface $client = null): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $client = $client ?: new Client(['base_uri' => $this->salesforceInstance]);

        $options = [
            'form_params' => [
                'grant_type' => 'password',
                'client_id' => $this->consumerKey,
                'client_secret' => $this->consumerSecret,
                'username' => $this->username,
                'password' => $this->password,
            ],
        ];

        $response     = $client->request('POST', '/services/oauth2/token', $options);
        $authResponse = json_decode($response->getBody()->getContents(), true);

        return $this->accessToken = $authResponse['access_token'];
    }
}
