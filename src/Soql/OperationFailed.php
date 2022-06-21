<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use RuntimeException;

use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

final class OperationFailed extends RuntimeException implements SoqlException
{
    /** @param mixed[] $payload */
    public static function updateFailed(array $payload): self
    {
        return new self(sprintf('Update failed with payload %s', json_encode($payload, JSON_THROW_ON_ERROR)));
    }

    /** @param mixed[] $payload */
    public static function insertFailed(array $payload): self
    {
        return new self(sprintf('Insert failed with payload %s', json_encode($payload, JSON_THROW_ON_ERROR)));
    }

    /** @param mixed[] $errors */
    public static function transactionFailed(array $errors): self
    {
        return new self(sprintf('Transaction failed with messages: %s', json_encode($errors, JSON_THROW_ON_ERROR)));
    }

    /** @param mixed[] $payload */
    public static function upsertFailed(array $payload): self
    {
        return new self(sprintf('Upsert failed with payload %s', json_encode($payload)));
    }
}
