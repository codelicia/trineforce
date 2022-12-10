<?php

declare(strict_types=1);

namespace Codelicia\Soql\Factory\Http;

use Codelicia\Soql\Factory\Http\Exception\RequestThrottlingException;

use function explode;
use function trim;

final class RequestThrottler
{
    public const HEADER = 'Sforce-Limit-Info';

    public function __construct(public readonly int $used, public readonly int $total)
    {
        if ($this->used >= $this->total) {
            throw RequestThrottlingException::create($this->used, $this->total);
        }
    }

    public static function of(string $line): self
    {
        [, $apiStatistic] = explode('=', trim($line));

        [$used, $total] = explode('/', $apiStatistic);

        return new self((int) $used, (int) $total);
    }
}
