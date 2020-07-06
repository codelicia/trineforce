<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use RuntimeException;

use function json_encode;
use function sprintf;

final class OperationFailed extends RuntimeException implements SoqlException
{
    /** @param mixed[] $payload */
    public static function updateFailed(array $payload): self
    {
        return new self(sprintf('Update failed with payload %s', json_encode($payload)));
    }

    /** @param mixed[] $payload */
    public static function insertFailed(array $payload): self
    {
        return new self(sprintf('Insert failed with payload %s', json_encode($payload)));
    }

    /** @param mixed[] $errors */
    public static function transactionFailed(array $errors): self
    {
        return new self(sprintf('Transaction failed with messages: %s', json_encode($errors)));
    }
}
