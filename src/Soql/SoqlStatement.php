<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Codelicia\Soql\Driver\Result;
use Doctrine\DBAL\Driver\OCI8\ConvertPositionalToNamedPlaceholders;
use Doctrine\DBAL\Driver\OCI8\Exception\UnknownParameterIndex;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\SQL\Parser;
use GuzzleHttp\ClientInterface;

use function array_keys;
use function is_int;
use function str_replace;

class SoqlStatement implements Statement
{
    protected Payload|null $payload = null;

    protected string $statement;

    /** @var mixed[] */
    protected array $boundValues;

    /** @var string[] */
    protected array $types;

    /** @var array<int, string> */
    private array $paramMap;

    private FetchDataUtility $fetchUtility;

    public function __construct(protected ClientInterface $connection, string $query)
    {
        $parser  = new Parser(false);
        $visitor = new ConvertPositionalToNamedPlaceholders();

        $parser->parse($query, $visitor);

        $this->fetchUtility = new FetchDataUtility();
        $this->paramMap     = $visitor->getParameterMap();
        $this->statement    = $visitor->getSQL();
        $this->boundValues  = [];
        $this->types        = [];
    }

    /** {@inheritdoc} */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        return $this->bindParam($param, $value, $type);
    }

    /** {@inheritdoc} */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null)
    {
        if (is_int($param)) {
            if (! isset($this->paramMap[$param])) {
                // todo create this exception
                throw UnknownParameterIndex::new($param);
            }

            $param = $this->paramMap[$param];
        }

        $this->boundValues[$param] =& $variable;
        $this->types[$param]       = $type;

        return true;
    }

    /** {@inheritdoc} */
    public function execute($params = null): \Doctrine\DBAL\Driver\Result
    {
        if ($params !== null) {
            foreach ($params as $key => $val) {
                $this->bindValue($key, $val);
            }
        }

        if ($this->boundValues !== []) {
            $values = BoundValuesSeparator::separateBoundValues(
                $this->boundValues,
                $this->types,
            );

            $this->statement = str_replace(array_keys($values), $values, $this->statement);
        }

        return new Result($this);
    }

    public function fetchAll(): Payload
    {
        $payload = $this->fetchUtility->fetchAll($this->connection, $this->statement);
        if ($payload->success() === false) {
            throw SoqlError::fromPayloadWithClientException($this->payload);
        }

        return $payload;
    }

    public function fetch(): array
    {
        return $this->fetchUtility->fetch($this->connection, $this->statement);
    }

    public function getSql(): string
    {
        return $this->statement;
    }
}
