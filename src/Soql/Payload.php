<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use function array_map as map;

final class Payload
{
    /** @param mixed[] $values */
    public function __construct(
        private bool $success,
        private int $totalSize,
        private array $values,
        private ?string $errorMessage = null,
        private ?string $errorCode = null
    ) {
    }

    /** @param mixed[] $values */
    public static function withValues(iterable $values): self
    {
        return new self(
            $values['done'],
            $values['totalSize'],
            map([self::class, 'removeRecordMetadata'], $values['records'])
        );
    }

    /** @param mixed[] $values */
    public static function withErrors(iterable $values): self
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
    private static function removeRecordMetadata(array $row): array
    {
        unset($row['attributes']);

        return $row;
    }

    public function totalSize(): int
    {
        return $this->totalSize;
    }

    /** @return mixed[][] */
    public function getResults(): array
    {
        return $this->values;
    }

    public function success(): bool
    {
        return $this->success;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
