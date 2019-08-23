<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use GuzzleHttp\Client;
use IteratorAggregate;
use stdClass;
use const PREG_OFFSET_CAPTURE;
use function array_combine;
use function count;
use function feof;
use function fread;
use function get_resource_type;
use function implode;
use function is_numeric;
use function is_resource;
use function is_string;
use function json_decode;
use function preg_match;
use function preg_quote;
use function sprintf;
use function str_repeat;
use function str_replace;
use function substr;

class SoqlStatement implements IteratorAggregate, Statement
{
    /** @var string[] */
    protected static $_paramTypeMap = [
        ParameterType::STRING => 's',
        ParameterType::BINARY => 's',
        ParameterType::BOOLEAN => 'i',
        ParameterType::NULL => 's',
        ParameterType::INTEGER => 'i',
        ParameterType::LARGE_OBJECT => 'b',
    ];

    /** @var Client */
    protected $conn;

    /** @var array */
    protected $statement;

    /** @var string[]|bool|null */
    protected $_columnNames;

    /** @var mixed[] */
    protected $_bindedValues;

    /** @var string */
    protected $types;

    /** @var int */
    protected $_defaultFetchMode = FetchMode::MIXED;

    /** @var bool */
    private $result = false;

    /** @var array<int, string> */
    private $paramMap;

    public function __construct(Client $conn, $prepareString)
    {
        $this->conn                         = $conn;
        [$this->statement, $this->paramMap] = self::convertPositionalToNamedPlaceholders($prepareString);
    }

    public static function convertPositionalToNamedPlaceholders($statement)
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
            throw new SoqlException(sprintf(
                'The statement contains non-terminated string literal starting at offset %d',
                $tokenOffset - 1
            ));
        }

        $fragments[] = substr($statement, $fragmentOffset);
        $statement   = implode('', $fragments);

        return [$statement, $paramMap];
    }

    private static function findPlaceholderOrOpeningQuote(
        $statement,
        &$tokenOffset,
        &$fragmentOffset,
        &$fragments,
        &$currentLiteralDelimiter,
        &$paramMap
    ) {
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

    private static function findToken($statement, &$offset, $regex)
    {
        if (preg_match($regex, $statement, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $offset = $matches[0][1];
            return $matches[0][0];
        }

        return null;
    }

    private static function findClosingQuote(
        $statement,
        &$tokenOffset,
        &$currentLiteralDelimiter
    ) {
        $token = self::findToken(
            $statement,
            $tokenOffset,
            '/' . preg_quote($currentLiteralDelimiter, '/') . '/'
        );

        if (! $token) {
            return false;
        }

        $currentLiteralDelimiter = false;
        ++$tokenOffset;

        return true;
    }

    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null) : bool
    {
        return $this->bindValue($column, $variable, $type);
    }

    public function bindValue($param, $value, $type = ParameterType::STRING) : bool
    {
        if (! is_numeric($param)) {
            throw new SoqlException(
                'sqlsrv does not support named parameters to queries, use question mark (?) placeholders instead.'
            );
        }

        $this->_bindedValues[$param] = $value;
        $this->types[$param]         = $type;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if ($this->_bindedValues !== null) {
            [$types, $values, $streams] = $this->separateBoundValues();

            // TODO: Use proper types
            $e = [];
            foreach ($values as $v) {
                $e[] = is_string($v) ? "'$v'" : $v;
            }
            $this->statement = str_replace($this->paramMap, $e, $this->statement);

            $this->sendLongData($streams);
        }

//        if (! $this->_stmt->execute()) {
//            throw new SoqlException($this->_stmt->error, $this->_stmt->sqlstate, $this->_stmt->errno);
//        }
//
//        if ($this->_columnNames === null) {
//            $meta = $this->_stmt->result_metadata();
//            if ($meta !== false) {
//                $columnNames = [];
//                foreach ($meta->fetch_fields() as $col) {
//                    $columnNames[] = $col->name;
//                }
//                $meta->free();
//
//                $this->_columnNames = $columnNames;
//            } else {
//                $this->_columnNames = false;
//            }
//        }
//
//        if ($this->_columnNames !== false) {
//            // Store result of every execution which has it. Otherwise it will be impossible
//            // to execute a new statement in case if the previous one has non-fetched rows
//            $this->_stmt->store_result();
//
//            $this->_rowBindedValues = array_fill(0, count($this->_columnNames), null);
//
//            $refs = [];
//            foreach ($this->_rowBindedValues as $key => &$value) {
//                $refs[$key] =& $value;
//            }
//
//            if (! $this->_stmt->bind_result(...$refs)) {
//                throw new SoqlException($this->_stmt->error, $this->_stmt->sqlstate, $this->_stmt->errno);
//            }
//        }
        $this->result = true;

        return true;
    }

    /**
     * @return array<int, array<int|string, mixed>|string>
     * @throws InvalidArgumentException
     */
    private function separateBoundValues() : array
    {
        $streams = $values = [];
        $types   = $this->types;

        foreach ($this->_bindedValues as $parameter => $value) {
            if (! isset($types[$parameter - 1])) {
                $types[$parameter - 1] = static::$_paramTypeMap[ParameterType::STRING];
            }

            if ($types[$parameter - 1] === static::$_paramTypeMap[ParameterType::LARGE_OBJECT]) {
                if (is_resource($value)) {
                    if (get_resource_type($value) !== 'stream') {
                        throw new InvalidArgumentException('Resources passed with the LARGE_OBJECT parameter type must be stream resources.');
                    }
                    $streams[$parameter] = $value;
                    $values[$parameter]  = null;
                    continue;
                }

                $types[$parameter - 1] = static::$_paramTypeMap[ParameterType::STRING];
            }

            $values[$parameter] = $value;
        }

        return [$types, $values, $streams];
    }

    /** @throws SoqlException */
    private function sendLongData($streams) : void
    {
        foreach ($streams as $paramNr => $stream) {
            while (! feof($stream)) {
                $chunk = fread($stream, 8192);

                if ($chunk === false) {
                    throw new SoqlException("Failed reading the stream resource for parameter offset ${paramNr}.");
                }

                if (! $this->statement->send_long_data($paramNr - 1, $chunk)) {
                    throw new SoqlException($this->statement->error, $this->statement->sqlstate, $this->statement->errno);
                }
            }
        }
    }

    /** @return mixed[]|false */
    private function _fetch()
    {
        // TODO: how to deal with different versions? Maybe `driverOptions`?
        $request = $this->conn->get('/services/data/v20.0/query?q=' . $this->statement);

        return json_decode($request->getBody()->getContents(), true);
    }

    /** {@inheritdoc} */
    public function fetch($fetchMode = null, $cursorOrientation = null, $cursorOffset = 0)
    {
        $this->result = true;
        if (! $this->result) {
            return false;
        }

        $fetchMode = $fetchMode ?: $this->_defaultFetchMode;

        if ($fetchMode === FetchMode::COLUMN) {
            return $this->fetchColumn();
        }

        $values = $this->_fetch();
        if ($values === null) {
            return false;
        }

        if ($values === false) {
            throw new SoqlException($this->statement->error, $this->statement->sqlstate, $this->statement->errno);
        }

        // TODO: that one is a completely mess
        switch ($fetchMode) {
            case FetchMode::NUMERIC:
                return $values;

            case FetchMode::ASSOCIATIVE:
                return $values;
//                return array_combine($this->_columnNames, $values);

            case FetchMode::MIXED:
                $ret  = array_combine($this->_columnNames, $values);
                $ret += $values;

                return $ret;

            case FetchMode::STANDARD_OBJECT:
                $assoc = array_combine($this->_columnNames, $values);
                $ret   = new stdClass();

                foreach ($assoc as $column => $value) {
                    $ret->$column = $value;
                }

                return $ret;

            default:
                throw new SoqlException(sprintf("Unknown fetch type '%s'", $fetchMode));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        $rows = [];

        if ($fetchMode !== FetchMode::COLUMN) {
            // TODO: I dunno if it makes sense to use `while` here,
            //     and after all it is just an http request
            return $this->fetch($fetchMode);
//            var_dump($this->fetch());
//            while (($row = $this->fetch($fetchMode)) !== false) {
//                $rows[] = $row;
//            }
        }

        while (($row = $this->fetchColumn()) !== false) {
            $rows[] = $row;
        }

        return $rows;
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
        return $this->statement->errno;
    }

    /** {@inheritdoc} */
    public function errorInfo()
    {
        return $this->statement->error;
    }

    /** {@inheritdoc} */
    public function closeCursor()
    {
        $this->statement->free_result();
        $this->result = false;

        return true;
    }

    /** {@inheritdoc} */
    public function rowCount()
    {
        if ($this->_columnNames === false) {
            return $this->statement->affected_rows;
        }

        return $this->statement->num_rows;
    }

    /** {@inheritdoc} */
    public function columnCount()
    {
        return $this->statement->field_count;
    }

    /** {@inheritdoc} */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->_defaultFetchMode = $fetchMode;

        return true;
    }

    /** {@inheritdoc} */
    public function getIterator()
    {
        return new StatementIterator($this);
    }
}
