<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use IteratorAggregate;
use const PREG_OFFSET_CAPTURE;
use function count;
use function get_resource_type;
use function implode;
use function is_numeric;
use function is_resource;
use function is_string;
use function json_decode;
use function preg_match;
use function preg_quote;
use function sprintf;
use function str_replace;
use function substr;

class SoqlStatement implements IteratorAggregate, Statement
{
    /** @var string[] */
    protected static $paramTypeMap = [
        ParameterType::STRING => 's',
        ParameterType::BINARY => 's',
        ParameterType::BOOLEAN => 'i',
        ParameterType::NULL => 's',
        ParameterType::INTEGER => 'i',
        ParameterType::LARGE_OBJECT => 'b',
    ];

    /** @var Payload|null */
    protected $payload;

    /** @var ClientInterface */
    protected $connection;

    /** @var string */
    protected $statement;

    /** @var mixed[] */
    protected $bindedValues;

    /** @var string */
    protected $types;

    /** @var int */
    protected $defaultFetchMode = FetchMode::MIXED;

    /** @var array<int, string> */
    private $paramMap;

    public function __construct(ClientInterface $connection, string $prepareString)
    {
        $this->connection                   = $connection;
        [$this->statement, $this->paramMap] = self::convertPositionalToNamedPlaceholders($prepareString);
    }

    /** @return string[]|mixed[][] */
    public static function convertPositionalToNamedPlaceholders(string $statement) : array
    {
        $fragmentOffset          = $tokenOffset = 0;
        $fragments               = $paramMap = [];
        $currentLiteralDelimiter = null;

        do {
            if (! $currentLiteralDelimiter) {
                $result = self::findPlaceholderOrOpeningQuote(
                    $statement,
                    $tokenOffset,
                    $fragmentOffset,
                    $fragments,
                    $currentLiteralDelimiter,
                    $paramMap
                );
            } else {
                $result = self::findClosingQuote($statement, $tokenOffset, $currentLiteralDelimiter);
            }
        } while ($result);

        if ($currentLiteralDelimiter) {
            throw new SoqlError(sprintf(
                'The statement contains non-terminated string literal starting at offset %d',
                $tokenOffset - 1
            ));
        }

        $fragments[] = substr($statement, $fragmentOffset);
        $statement   = implode('', $fragments);

        return [$statement, $paramMap];
    }

    /**
     * @param string[] $fragments
     * @param string[] $paramMap
     */
    private static function findPlaceholderOrOpeningQuote(
        string $statement,
        int &$tokenOffset,
        int &$fragmentOffset,
        array &$fragments,
        ?string &$currentLiteralDelimiter,
        array &$paramMap
    ) : bool {
        $token = self::findToken($statement, $tokenOffset, '/[?\'"]/');

        if (! $token) {
            return false;
        }

        if ($token === '?') {
            $position            = count($paramMap) + 1;
            $param               = ':param' . $position;
            $fragments[]         = substr($statement, $fragmentOffset, $tokenOffset - $fragmentOffset);
            $fragments[]         = $param;
            $paramMap[$position] = $param;
            $tokenOffset        += 1;
            $fragmentOffset      = $tokenOffset;

            return true;
        }

        $currentLiteralDelimiter = $token;
        ++$tokenOffset;

        return true;
    }

    private static function findToken(string $statement, int &$offset, string $regex) : ?string
    {
        if (preg_match($regex, $statement, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $offset = $matches[0][1];
            return $matches[0][0];
        }

        return null;
    }

    private static function findClosingQuote(
        string $statement,
        int &$tokenOffset,
        ?string &$currentLiteralDelimiter
    ) : bool {
        $token = self::findToken(
            $statement,
            $tokenOffset,
            '/' . preg_quote($currentLiteralDelimiter, '/') . '/'
        );

        if (! $token) {
            return false;
        }

        ++$tokenOffset;

        return true;
    }

    /** {@inheritDoc} */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null) : bool
    {
        return $this->bindValue($column, $variable, $type);
    }

    /** {@inheritDoci} */
    public function bindValue($param, $value, $type = ParameterType::STRING) : bool
    {
        if (! is_numeric($param)) {
            throw new SoqlError(
                'SOQL does not support named parameters to queries, use question mark (?) placeholders instead.'
            );
        }

        $this->bindedValues[$param] = $value;
        $this->types[$param]        = $type;

        return true;
    }

    /** {@inheritdoc} */
    public function execute($params = null) : bool
    {
        if ($this->bindedValues !== null) {
            $values = $this->separateBoundValues();

            $e = [];
            foreach ($values as $v) {
                $e[] = is_string($v) ? sprintf("'%s'", $v) : $v;
            }
            $this->statement = str_replace($this->paramMap, $e, $this->statement);
        }

        return true;
    }

    /**
     * @return string[]
     *
     * @throws InvalidArgumentException
     */
    private function separateBoundValues() : array
    {
        $values = [];
        $types  = $this->types;

        foreach ($this->bindedValues as $parameter => $value) {
            if (! isset($types[$parameter - 1])) {
                $types[$parameter - 1] = static::$paramTypeMap[ParameterType::STRING];
            }

            if ($types[$parameter - 1] === static::$paramTypeMap[ParameterType::LARGE_OBJECT]) {
                if (is_resource($value)) {
                    if (get_resource_type($value) !== 'stream') {
                        throw new InvalidArgumentException(
                            'Resources passed with the LARGE_OBJECT parameter type must be stream resources.'
                        );
                    }
                    $values[$parameter] = null;
                    continue;
                }

                $types[$parameter - 1] = static::$paramTypeMap[ParameterType::STRING];
            }

            $values[$parameter] = $value;
        }

        return $values;
    }

    /** @return mixed[]|false */
    private function doFetch()
    {
        // TODO: how to deal with different versions? Maybe `driverOptions`?
        $request = $this->connection->get('/services/data/v20.0/query?q=' . $this->statement);

        return json_decode($request->getBody()->getContents(), true);
    }

    /** {@inheritdoc} */
    public function fetch($fetchMode = null, $cursorOrientation = null, $cursorOffset = 0)
    {
        // Early return payload
        if ($this->payload !== null) {
            return $this->payload->getResults();
        }

        try {
            $values = $this->doFetch();
        } catch (ClientException $exception) {
            $responseContent = $exception->getResponse()->getBody()->getContents();
            $firstError      = json_decode($responseContent, true)[0];
            $this->payload   = Payload::withErrors($firstError);

            throw new SoqlError($this->payload->getErrorMessage(), $this->payload->getErrorCode());
        }

        $payload = Payload::withValues($values);

        $this->payload = $payload;

        return $this->payload->getResults();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        return $this->fetch($fetchMode);
    }

    /** {@inheritdoc} */
    public function fetchColumn($columnIndex = 0)
    {
        $row = $this->fetch(FetchMode::NUMERIC);

        if ($row === false) {
            return false;
        }

        return $row[$columnIndex] ?? null;
    }

    /** {@inheritdoc} */
    public function errorCode()
    {
        return $this->payload->getErrorCode();
    }

    /** {@inheritdoc} */
    public function errorInfo()
    {
        return $this->payload->getErrorMessage();
    }

    /** {@inheritdoc} */
    public function closeCursor() : bool
    {
        return true;
    }

    /** {@inheritdoc} */
    public function rowCount() : int
    {
        return $this->payload->totalSize();
    }

    /** {@inheritdoc} */
    public function columnCount() : int
    {
        return $this->payload->totalSize();
    }

    /** {@inheritdoc} */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null) : bool
    {
        $this->defaultFetchMode = $fetchMode;

        return true;
    }

    /** {@inheritdoc} */
    public function getIterator() : StatementIterator
    {
        return new StatementIterator($this);
    }
}
