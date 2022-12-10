<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql\Stubs;

use Codelicia\Soql\ConnectionWrapper;
use Codelicia\Soql\SoqlDriver;
use Doctrine\DBAL\Exception;
use GuzzleHttp\ClientInterface;

final class ConnectionWrapperFactory
{
    /** @throws Exception */
    public static function create(ClientInterface $client): ConnectionWrapper
    {
        return new ConnectionWrapper(
            [
                'user'                    => 'foo',
                'salesforceInstance'      => 'it_is_key_to_have_it',
                'password'                => 'foo',
                'consumerSecret'          => 'foo',
                'consumerKey'             => 'foo',
                'authorizedClientFactory' => new AuthorizedClientFactory($client),
                'apiVersion'              => 'api-version-456',
            ],
            new SoqlDriver(),
        );
    }
}
