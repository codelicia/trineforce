<?php

declare(strict_types=1);

namespace Codelicia\Soql\Factory;

use GuzzleHttp\ClientInterface;

interface AuthorizedClientFactoryInterface
{
    public function __invoke() : ClientInterface;
}
