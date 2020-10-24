<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Webmozart\Assert\Assert;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function count;
use function json_decode;
use function json_encode;
use function key;
use function sprintf;
use function uniqid;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

class ConnectionWrapper extends Connection
{
    private const SERVICE_OBJECT_URL    = '/services/data/v43.0/sobjects/%s';
    private const SERVICE_OBJECT_ID_URL = '/services/data/v43.0/sobjects/%s/%s';
    private const SERVICE_COMPOSITE_URL = '/services/data/v43.0/composite';

    private int $transactionalLevel = 0;

    /** @var mixed[] */
    private array $batchList = [];

    public function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this);
    }

    /** {@inheritDoc} */
    public function delete($tableExpression, array $identifier, array $types = []): void
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
            method: 'DELETE',
            uri: sprintf(self::SERVICE_OBJECT_ID_URL, $tableExpression, $param)
        ));
    }

    /** {@inheritDoc} */
    public function insert($tableExpression, array $data, array $refs = [])
    {
        $request = new Request(
            method: 'POST',
            uri: sprintf(self::SERVICE_OBJECT_URL, $tableExpression),
            body: json_encode($data, JSON_THROW_ON_ERROR)
        );

        if ($this->isTransactionActive()) {
            $this->addToBatchList($request, $refs);

            return;
        }

        $response     = $this->send($request);
        $responseBody = json_decode($response->getBody()->getContents(), assoc: true, options: JSON_THROW_ON_ERROR);

        if ($responseBody['success'] !== true) {
            throw OperationFailed::insertFailed($data);
        }
    }

    /** {@inheritDoc} */
    public function update($tableExpression, array $data, array $identifier = [], array $refs = [])
    {
        Assert::keyExists($identifier, key: 'Id');

        $param = $identifier['Id'] ?? (key($identifier) . '/' . $identifier[key($identifier)]);

        $request = new Request(
            method: 'PATCH',
            uri: sprintf(self::SERVICE_OBJECT_ID_URL, $tableExpression, $param),
            body: json_encode($data, options: JSON_THROW_ON_ERROR)
        );

        if ($this->isTransactionActive()) {
            $this->addToBatchList($request, $refs);

            return;
        }

        $response = $this->send($request);

        if ($response->getStatusCode() !== 204) {
            throw OperationFailed::updateFailed($data);
        }
    }

    /**
     * @param mixed[] $refs
     */
    private function addToBatchList(Request $request, array $refs): void
    {
        $command = [
            'body'   => json_decode($request->getBody()->getContents(), assoc: true),
            'method' => $request->getMethod(),
            'url'    => (string) $request->getUri(),
        ];

        if ($refs !== []) {
            Assert::keyExists($refs, key: 'referenceId');

            $command = array_merge($command, ['referenceId' => $refs['referenceId']]);
        }

        $this->batchList[] = $command;
    }

    public function beginTransaction(): void
    {
        ++$this->transactionalLevel;
    }

    public function isTransactionActive(): bool
    {
        return $this->transactionalLevel > 0;
    }

    public function commit(): void
    {
        if ($this->transactionalLevel === 0) {
            throw ConnectionException::noActiveTransaction();
        }

        --$this->transactionalLevel;

        if ($this->transactionalLevel !== 0) {
            return;
        }

        $response = $this->send(new Request(
            method: 'POST',
            uri: self::SERVICE_COMPOSITE_URL,
            body: json_encode($this->compositeList(), options: JSON_THROW_ON_ERROR)
        ));

        $responseBody = json_decode($response->getBody()->getContents(), assoc: true);

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
    }

    public function rollBack(): void
    {
        $this->resetBatchListAndTransactionLevel();
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
                return array_key_exists(key: 'referenceId', search: $subRequest)
                    ? $subRequest
                    : array_merge($subRequest, [
                        'referenceId' => uniqid(prefix: 'referenceId', more_entropy: false),
                        'httpHeaders' => ['Sforce-Auto-Assign' => 'FALSE'],
                    ]);
            }, $this->batchList),
        ];
    }

    private function send(RequestInterface $request): ResponseInterface
    {
        $requestId = uniqid('requestId', false);
        $logger    = $this->_config->getSQLLogger();
        $http      = $this->getHttpClient();

        if ($logger) {
            $logger->startQuery(json_encode([
                'request' => [
                    'requestId' => $requestId,
                    'method'    => $request->getMethod(),
                    'uri'       => (string) $request->getUri(),
                    'header'    => $request->getHeaders(),
                    'body'      => json_decode($request->getBody()->getContents(), true),
                ],
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        }

        $request->getBody()->rewind();

        $response = $http->send($request);

        if ($logger) {
            $logger->startQuery(json_encode([
                'response' => [
                    'requestId'  => $requestId,
                    'statusCode' => $response->getStatusCode(),
                    'header'     => $response->getHeaders(),
                    'body'       => json_decode($response->getBody()->getContents(), true),
                ],
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            $logger->stopQuery();
        }

        $response->getBody()->rewind();

        return $response;
    }

    private function getHttpClient(): ClientInterface
    {
        $this->connect();

        return $this->_conn->getHttpClient();
    }
}
