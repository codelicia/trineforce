<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;

use function assert;
use function current;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

final class FetchDataUtility
{
    public function fetchAll(ClientInterface $client, string $statement): Payload
    {
        try {
            $values = $this->doFetch($client, $statement);
        } catch (ClientException $exception) {
            return Payload::fromClientException($exception);
        }

        return Payload::withValues($values);
    }

    private function doFetch(ClientInterface $client, string $statement)
    {
        $apiVersion = $client->getConfig('apiVersion');

        $request = $client->request('GET', sprintf('/services/data/%s/query?q=%s', $apiVersion, $statement));

        return json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function fetch(ClientInterface $client, string $statement): array
    {
        $result = $this->fetchAll($client, $statement)
            ->getResults();

        assert(! empty($result));

        return current($result);
    }
}
