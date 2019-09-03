<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use function array_map as map;

final class Payload
{
    /** @var bool */
    private $success;

    /** @var int */
    private $totalSize;

    /** @var mixed[][] */
    private $values;

    /** @var string|null */
    private $errorMessage;

    /** @var string|null */
    private $errorCode;

    /** @param mixed[] $values */
    public function __construct(
        bool $success,
        int $totalSize,
        array $values,
        ?string $errorMessage = null,
        ?string $errorCode = null
    ) {
        $this->success      = $success;
        $this->totalSize    = $totalSize;
        $this->values       = $values;
        $this->errorMessage = $errorMessage;
        $this->errorCode    = $errorCode;
    }

    /** @param mixed[] $values */
    public static function withValues(iterable $values) : self
    {
        return new self(
            $values['done'],
            $values['totalSize'],
            map([self::class, 'removeRecordMetadata'], $values['records'])
        );
    }

    /** @param mixed[] $values */
    public static function withErrors(iterable $values) : self
    {
        return new self(
            false,
            0,
            [],
            $values['message'],
            $values['errorCode']
        );
    }

    /**
     * @param mixed[][] $row
     *
     * @return mixed[][]
     */
    private static function removeRecordMetadata(array $row) : array
    {
        unset($row['attributes']);

        return $row;
    }

    public function totalSize() : int
    {
        return $this->totalSize;
    }

    /** @return mixed[][] */
    public function getResults() : array
    {
        return $this->values;
    }

    public function success() : bool
    {
        return $this->success;
    }

    public function getErrorMessage() : ?string
    {
        return $this->errorMessage;
    }

    public function getErrorCode() : ?string
    {
        return $this->errorCode;
    }
}
