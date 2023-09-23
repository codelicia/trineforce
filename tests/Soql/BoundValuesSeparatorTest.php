<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use Codelicia\Soql\BoundValuesSeparator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BoundValuesSeparatorTest extends TestCase
{
    #[Test]
    public function separate_bound_values(): void
    {
        $boundValues = [
            'name' => 'John',
            'age' => 30,
            'hobbies' => ['reading', 'swimming'],
            'description' => null,
        ];

        $expected = [
            ':name' => "'John'",
            ':age' => 30,
            ':hobbies' => "'reading', 'swimming'",
            ':description' => null,
        ];

        $result = BoundValuesSeparator::separateBoundValues($boundValues);

        self::assertSame($expected, $result);
    }

    #[Test]
    public function with_objects(): void
    {
        $boundValues = [
            'name' => new class () {
                public function __toString(): string
                {
                    return 'John';
                }
            },
        ];

        $expected = [':name' => "'John'"];

        $result = BoundValuesSeparator::separateBoundValues($boundValues);

        self::assertSame($expected, $result);
    }

    #[Test]
    public function with_missing_type(): void
    {
        $boundValues = ['name' => 'John'];

        $expected = [':name' => "'John'"];

        $result = BoundValuesSeparator::separateBoundValues($boundValues);

        self::assertSame($expected, $result);
    }
}
