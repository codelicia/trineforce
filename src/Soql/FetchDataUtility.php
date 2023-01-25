<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Codelicia\Soql\Factory\Http\RequestThrottler;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;

use function current;
use function json_decode;
use function Psl\invariant;
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

        $response = $client->request('GET', sprintf('/services/data/%s/query?q=%s', $apiVersion, $statement));

        RequestThrottler::of($response->getHeaderLine(RequestThrottler::HEADER));

        return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function fetch(ClientInterface $client, string $statement): array
    {
        $result = $this->fetchAll($client, $statement)
            ->getResults();

        invariant(! empty($result), '$result should not be empty.');

        return current($result);
    }
}
