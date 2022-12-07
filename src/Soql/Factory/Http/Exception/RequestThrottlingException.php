<?php

declare(strict_types=1);

namespace Codelicia\Soql\Factory\Http\Exception;

use RuntimeException;

use function sprintf;

final class RequestThrottlingException extends RuntimeException
{
    public static function create(int $used, int $total): self
    {
        return new self(sprintf('Failed because you\'ve used "%d" from a total of "%d" requests.', $used, $total));
    }
}
