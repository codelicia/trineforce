<?php

declare(strict_types=1);

namespace Codelicia\Soql\Factory;

use GuzzleHttp\ClientInterface;

interface AccessTokenFactory
{
    public function __invoke(?ClientInterface $client = null) : string;
}
