<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use GuzzleHttp\ClientInterface;
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

        $http = $this->getHttpClient();

        if (count($identifier) > 1) {
            throw InvalidArgumentException::notSupported('It should have only one DELETE criteria.');
        }

        if ($this->isTransactionActive()) {
            throw InvalidArgumentException::notSupported('DELETE is not supported under transaction.');
        }

        $param = $identifier['Id'] ?? (key($identifier) . '/' . $identifier[key($identifier)]);

        $http->request('DELETE', sprintf(self::SERVICE_OBJECT_ID_URL, $tableExpression, $param));
    }

    /** {@inheritDoc} */
    public function insert($tableExpression, array $data, array $refs = [])
    {
        $http = $this->getHttpClient();

        $url = sprintf(self::SERVICE_OBJECT_URL, $tableExpression);

        if ($this->isTransactionActive()) {
            $this->addToBatchList('POST', $url, $data, $refs);

            return;
        }

        $response = $http->request('POST', $url, ['body' => json_encode($data)]);

        $responseBody = json_decode($response->getBody()->getContents(), true);

        if ($responseBody['success'] !== true) {
            throw OperationFailed::insertFailed($data);
        }
    }

    /** {@inheritDoc} */
    public function update($tableExpression, array $data, array $identifier = [], array $refs = [])
    {
        $http = $this->getHttpClient();

        $param = $identifier['Id'] ?? (key($identifier) . '/' . $identifier[key($identifier)]);
        $url   = sprintf(self::SERVICE_OBJECT_ID_URL, $tableExpression, $param);

        if ($this->isTransactionActive()) {
            $this->addToBatchList('PATCH', $url, $data, $refs);

            return;
        }

        $response = $http->request('PATCH', $url, ['body' => json_encode($data)]);

        if ($response->getStatusCode() !== 204) {
            throw OperationFailed::updateFailed($data);
        }
    }

    /**
     * @param mixed[] $data
     * @param mixed[] $refs
     */
    private function addToBatchList(string $method, string $url, array $data, array $refs) : void
    {
        $command = ['method' => $method, 'url' => $url, 'body' => $data];

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

        $http = $this->getHttpClient();

        $response = $http->request(
            'POST',
            self::SERVICE_COMPOSITE_URL,
            ['body' => json_encode($this->compositeList())]
        );

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
                    : array_merge($subRequest, ['referenceId' => uniqid('referenceId', false)]);
            }, $this->batchList),
        ];
    }

    private function getHttpClient() : ClientInterface
    {
        $this->connect();

        return $this->_conn->getHttpClient();
    }
}
