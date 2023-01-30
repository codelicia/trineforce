<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Doctrine\DBAL\ParameterType;

use function implode;
use function is_array;
use function is_object;
use function is_string;
use function method_exists;
use function sprintf;

final class BoundValuesSeparator
{
    private function __construct()
    {
    }

    /** @return list<string> */
    public static function separateBoundValues(array $boundValues, array $types): array
    {
        $values = [];

        foreach ($boundValues as $parameter => $value) {
            $parameter = sprintf(':%s', $parameter);
            if (! isset($types[$parameter])) {
                $types[$parameter] = ParameterType::STRING;
            }

            if (is_object($value) && method_exists($value, '__toString')) {
                $value = (string) $value;
            }

            if (is_array($value)) {
                $value = implode("', '", $value);
            }

            $values[$parameter] = is_string($value) ? sprintf("'%s'", $value) : $value;
        }

        return $values;
    }
}
