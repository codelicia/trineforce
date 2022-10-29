<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Codelicia\Soql\DBAL\Result;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use JsonException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Webmozart\Assert\Assert;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function count;
use function in_array;
use function json_decode;
use function json_encode;
use function key;
use function sprintf;
use function uniqid;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

class ConnectionWrapper extends Connection
{
    private const SERVICE_OBJECT_URL             = '/services/data/%s/sobjects/%s';
    private const SERVICE_OBJECT_ID_URL          = '/services/data/%s/sobjects/%s/%s';
    private const SERVICE_OBJECT_EXTERNAL_ID_URL = '/services/data/%s/sobjects/%s/%s/%s';
    private const SERVICE_COMPOSITE_URL          = '/services/data/%s/composite';

    private int $transactionalLevel = 0;

    /** @var mixed[] */
    private array $batchList = [];

    public function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this);
    }

    public function executeQuery(
        string $sql,
        array $params = [],
        $types = [],
        QueryCacheProfile|null $qcp = null,
    ): Result {
        $statement = new SoqlStatement($this->getHttpClient(), $sql);

        return new Result($statement->execute($params), $this);
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, string> $headers contains the headers that will be used when sending the request.
     */
    public function delete($tableExpression, array $identifier, array $types = [], array $headers = []): int
    {
        if (empty($identifier)) {
            throw InvalidArgumentException::fromEmptyCriteria();
        }

        if (count($identifier) > 1) {
            throw InvalidArgumentException::notSupported('It should have only one DELETE criteria.');
        }

        if ($this->isTransactionActive()) {
            throw InvalidArgumentException::notSupported('DELETE is not supported under transaction.');
        }

        $param = $identifier['Id'] ?? (key($identifier) . '/' . $identifier[key($identifier)]);
        $this->send(new Request(
            'DELETE',
            sprintf(self::SERVICE_OBJECT_ID_URL, $this->apiVersion(), $tableExpression, $param),
            $headers,
        ));

        return 1;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, string> $headers contains the headers that will be used when sending the request.
     *
     * @throws JsonException
     */
    public function insert($tableExpression, array $data, array $refs = [], array $headers = []): int
    {
        $request = new Request(
            'POST',
            sprintf(self::SERVICE_OBJECT_URL, $this->apiVersion(), $tableExpression),
            $headers,
            json_encode($data, JSON_THROW_ON_ERROR),
        );

        if ($this->isTransactionActive()) {
            $this->addToBatchList($request, $refs);

            return 1;
        }

        $response     = $this->send($request);
        $responseBody = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        if ($responseBody['success'] !== true) {
            throw OperationFailed::insertFailed($data);
        }

        return 1;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, string> $headers contains the headers that will be used when sending the request.
     *
     * @throws JsonException
     */
    public function update(
        $tableExpression,
        array $data,
        array $identifier = [],
        array $refs = [],
        array $headers = [],
    ): int {
        Assert::keyExists($identifier, 'Id');

        $param = $identifier['Id'] ?? (key($identifier) . '/' . $identifier[key($identifier)]);

        $request = new Request(
            'PATCH',
            sprintf(self::SERVICE_OBJECT_ID_URL, $this->apiVersion(), $tableExpression, $param),
            $headers,
            json_encode($data, JSON_THROW_ON_ERROR),
        );

        if ($this->isTransactionActive()) {
            $this->addToBatchList($request, $refs);

            return 1;
        }

        $response = $this->send($request);

        if ($response->getStatusCode() !== 204) {
            throw OperationFailed::updateFailed($data);
        }

        return 1;
    }

    /**
     * @param string                $externalIdName  Name of the external id of the object in Salesforce
     * @param string                $externalIdValue Value of the external id of the object in Salesforce
     * @param array<string, string> $headers         contains the headers that will be used when sending the request.
     *
     * @throws JsonException
     */
    public function upsert(
        string $tableExpression,
        string $externalIdName,
        string $externalIdValue,
        array $data,
        array $refs = [],
        array $headers = [],
    ): int {
        $uri = sprintf(
            self::SERVICE_OBJECT_EXTERNAL_ID_URL,
            $this->apiVersion(),
            $tableExpression,
            $externalIdName,
            $externalIdValue,
        );

        $request = new Request(
            'PATCH',
            $uri,
            $headers,
            json_encode($data, JSON_THROW_ON_ERROR),
        );

        if ($this->isTransactionActive()) {
            $this->addToBatchList($request, $refs);

            return 1;
        }

        $response = $this->send($request);

        if (! in_array($response->getStatusCode(), [200, 201, 204])) {
            throw OperationFailed::upsertFailed($data);
        }

        return 1;
    }

    /**
     * @param mixed[] $refs
     *
     * @throws JsonException
     */
    private function addToBatchList(Request $request, array $refs): void
    {
        $command = [
            'body' => json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR),
            'method' => $request->getMethod(),
            'url' => (string) $request->getUri(),
        ];

        if ($refs !== []) {
            Assert::keyExists($refs, 'referenceId');

            $command = array_merge($command, ['referenceId' => $refs['referenceId']]);
        }

        $this->batchList[] = $command;
    }

    public function beginTransaction(): bool
    {
        ++$this->transactionalLevel;

        return true;
    }

    public function isTransactionActive(): bool
    {
        return $this->transactionalLevel > 0;
    }

    /**
     * @throws ConnectionException
     * @throws JsonException
     */
    public function commit(): bool
    {
        if ($this->transactionalLevel === 0) {
            throw ConnectionException::noActiveTransaction();
        }

        --$this->transactionalLevel;

        if ($this->transactionalLevel !== 0) {
            return true;
        }

        $response = $this->send(new Request(
            'POST',
            sprintf(self::SERVICE_COMPOSITE_URL, $this->apiVersion()),
            [],
            json_encode($this->compositeList(), JSON_THROW_ON_ERROR),
        ));

        $responseBody = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        $this->resetBatchListAndTransactionLevel();

        $errors = array_filter(array_map(static function ($payload): array {
            if (isset($payload['body'][0]['errorCode'])) {
                return [$payload['body'][0]['message']];
            }

            return [];
        }, $responseBody['compositeResponse']));

        if ($errors !== []) {
            throw OperationFailed::transactionFailed($errors);
        }

        return true;
    }

    public function rollBack(): bool
    {
        $this->resetBatchListAndTransactionLevel();

        return true;
    }

    private function resetBatchListAndTransactionLevel(): void
    {
        $this->transactionalLevel = 0;
        $this->batchList          = [];
    }

    /** @return mixed[] */
    private function compositeList(): array
    {
        return [
            'allOrNone'        => true,
            'compositeRequest' => array_map(static function (array $subRequest): array {
                return array_key_exists('referenceId', $subRequest)
                    ? $subRequest
                    : array_merge($subRequest, [
                        'referenceId' => uniqid('referenceId', false),
                        'httpHeaders' => ['Sforce-Auto-Assign' => 'FALSE'],
                    ]);
            }, $this->batchList),
        ];
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    private function send(RequestInterface $request): ResponseInterface
    {
        $requestId = uniqid('requestId', false);
        $logger    = $this->_config->getSQLLogger();
        $http      = $this->getHttpClient();

        if ($logger) {
            $contents = $request->getBody()->getContents();
            $logger->startQuery(json_encode([
                'request' => [
                    'requestId' => $requestId,
                    'method'    => $request->getMethod(),
                    'uri'       => (string) $request->getUri(),
                    'header'    => $request->getHeaders(),
                    'body'      => json_decode($contents === '' ? 'null' : $contents, true, 512, JSON_THROW_ON_ERROR),
                ],
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        }

        $request->getBody()->rewind();

        $response = $http->send($request);

        if ($logger) {
            $contents = $response->getBody()->getContents();
            $logger->startQuery(json_encode([
                'response' => [
                    'requestId'  => $requestId,
                    'statusCode' => $response->getStatusCode(),
                    'header'     => $response->getHeaders(),
                    'body'       => json_decode($contents === '' ? 'null' : $contents, true, 512, JSON_THROW_ON_ERROR),
                ],
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            $logger->stopQuery();
        }

        $response->getBody()->rewind();

        return $response;
    }

    private function apiVersion(): string
    {
        return $this->getParams()['apiVersion'];
    }

    /** @throws Exception */
    private function getHttpClient(): ClientInterface
    {
        $this->connect();

        return $this->_conn->getNativeConnection();
    }
}
