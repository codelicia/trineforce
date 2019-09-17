<?php

declare(strict_types=1);

namespace Codelicia\Soql\Factory;

use GuzzleHttp\ClientInterface;

interface AuthorizedClientFactory
{
    public function __invoke() : ClientInterface;
}
