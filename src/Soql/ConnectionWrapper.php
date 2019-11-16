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

class ConnectionWrapper extends Connection
{
    private const SERVICE_OBJECT_URL    = '/services/data/v43.0/sobjects/%s';
    private const SERVICE_OBJECT_ID_URL = '/services/data/v43.0/sobjects/%s/%s';
    private const SERVICE_COMPOSITE_URL = '/services/data/v43.0/composite';

    /** @var int */
    private $transactionalLevel = 0;

    /** @var mixed[] */
    private $batchList = [];

    public function createQueryBuilder() : QueryBuilder
    {
        return new QueryBuilder($this);
    }

    /** {@inheritDoc} */
    public function delete($tableExpression, array $identifier, array $types = []) : void
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
            sprintf(self::SERVICE_OBJECT_ID_URL, $tableExpression, $param)
        ));
    }

    /** {@inheritDoc} */
    public function insert($tableExpression, array $data, array $refs = [])
    {
        $request = new Request(
            'POST',
            sprintf(self::SERVICE_OBJECT_URL, $tableExpression),
            [],
            json_encode($data)
        );

        if ($this->isTransactionActive()) {
            $this->addToBatchList($request, $refs);

            return;
        }

        $response     = $this->send($request);
        $responseBody = json_decode($response->getBody()->getContents(), true);

        if ($responseBody['success'] !== true) {
            throw OperationFailed::insertFailed($data);
        }
    }

    /** {@inheritDoc} */
    public function update($tableExpression, array $data, array $identifier = [], array $refs = [])
    {
        $param = $identifier['Id'] ?? (key($identifier) . '/' . $identifier[key($identifier)]);

        $request = new Request(
            'PATCH',
            sprintf(self::SERVICE_OBJECT_ID_URL, $tableExpression, $param),
            [],
            json_encode($data)
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
    private function addToBatchList(Request $request, array $refs) : void
    {
        $command = [
            'body' => $request->getBody()->getContents(),
            'method' => $request->getMethod(),
            'url' => $request->getUri(),
        ];

        if ($refs !== []) {
            Assert::keyExists($refs, 'referenceId');

            $command = array_merge($command, ['referenceId' => $refs['referenceId']]);
        }

        $this->batchList[] = $command;
    }

    public function beginTransaction() : void
    {
        ++$this->transactionalLevel;
    }

    public function isTransactionActive() : bool
    {
        return $this->transactionalLevel > 0;
    }

    public function commit() : void
    {
        if ($this->transactionalLevel === 0) {
            throw ConnectionException::noActiveTransaction();
        }

        --$this->transactionalLevel;

        if ($this->transactionalLevel !== 0) {
            return;
        }

        $response = $this->send(new Request(
            'POST',
            self::SERVICE_COMPOSITE_URL,
            [],
            json_encode($this->compositeList())
        ));

        $responseBody = json_decode($response->getBody()->getContents(), true);

        $this->resetBatchListAndTransactionLevel();

        $errors = array_filter(array_map(static function ($payload) : array {
            if (isset($payload['body'][0]['errorCode'])) {
                return [$payload['body'][0]['message']];
            }

            return [];
        }, $responseBody['compositeResponse']));

        if ($errors !== []) {
            throw OperationFailed::transactionFailed($errors);
        }
    }

    public function rollBack() : void
    {
        $this->resetBatchListAndTransactionLevel();
    }

    private function resetBatchListAndTransactionLevel() : void
    {
        $this->transactionalLevel = 0;
        $this->batchList          = [];
    }

    /** @return mixed[] */
    private function compositeList() : array
    {
        return [
            'allOrNone'        => true,
            'compositeRequest' => array_map(static function (array $subRequest) : array {
                return array_key_exists('referenceId', $subRequest)
                    ? $subRequest
                    : array_merge($subRequest, [
                        'referenceId' => uniqid('referenceId', false),
                        'httpHeaders' => ['Sforce-Auto-Assign' => 'FALSE'],
                    ]);
            }, $this->batchList),
        ];
    }

    private function send(RequestInterface $request) : ResponseInterface
    {
        $logger = $this->_config->getSQLLogger();
        $http   = $this->getHttpClient();

        if ($logger) {
            $logger->startQuery(json_encode([
                'request' => [
                    'method' => $request->getMethod(),
                    'uri'    => (string) $request->getUri(),
                    'header' => $request->getHeaders(),
                    'body'   => json_decode($request->getBody()->getContents()),
                ],
            ], JSON_PRETTY_PRINT));
        }

        $request->getBody()->rewind();

        $response = $http->send($request);

        if ($logger) {
            $logger->startQuery(json_encode([
                'response' => [
                    'statusCode' => $response->getStatusCode(),
                    'header'     => $response->getHeaders(),
                    'body'       => json_decode($response->getBody()->getContents()),
                ],
            ], JSON_PRETTY_PRINT));
            $logger->stopQuery();
        }

        $response->getBody()->rewind();

        return $response;
    }

    private function getHttpClient() : ClientInterface
    {
        $this->connect();

        return $this->_conn->getHttpClient();
    }
}
