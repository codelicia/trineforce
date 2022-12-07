<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Doctrine\DBAL\Driver\Exception;

// todo use a marker interface
class SoqlError extends \Exception implements Exception
{
    public static function fromPayloadWithClientException(Payload $payload): self
    {
        return new self($payload->getErrorMessage(), (int) $payload->getErrorCode());
    }

    public function getSQLState(): void
    {
        // todo
    }
}
