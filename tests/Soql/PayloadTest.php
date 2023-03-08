<?php

declare(strict_types=1);

namespace CodeliciaTest\Soql;

use Codelicia\Soql\Payload;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PayloadTest extends TestCase
{
    #[Test]
    #[DataProvider('provideValidPayload')]
    public function create_payload_with_data(iterable $data): void
    {
        $payload = Payload::withValues($data);

        self::assertTrue($payload->success());
        self::assertSame(1, $payload->totalSize());
        self::assertSame([['Id' => '0062X00000vLZDVQA4', 'Name' => 'Pay as you go Opportunity']], $payload->getResults());
        self::assertNull($payload->getErrorMessage());
        self::assertNull($payload->getErrorCode());
    }

    #[Test]
    #[DataProvider('provideErrorPayload')]
    public function create_error_payload_with_data(iterable $data): void
    {
        $payload = Payload::withErrors($data);

        self::assertFalse($payload->success());
        self::assertSame(0, $payload->totalSize());
        self::assertSame([], $payload->getResults());
        self::assertSame('sObject type \'Opportunitay\' is not supported.', $payload->getErrorMessage());
        self::assertSame('INVALID_TYPE', $payload->getErrorCode());
    }

    public static function provideValidPayload(): Generator
    {
        yield [
            'valid opportunity payload' =>  [
                'totalSize' => 1,
                'done' => true,
                'records' => [
                    [
                        'attributes' => [
                            'type' => 'Opportunity',
                            'url' => '/services/data/v20.0/sobjects/Opportunity/0062X00000vLZDVQA4',
                        ],
                        'Id' => '0062X00000vLZDVQA4',
                        'Name' => 'Pay as you go Opportunity',
                    ],
                ],
            ],
        ];
    }

    public static function provideErrorPayload(): Generator
    {
        yield [
            [
                'message' => 'sObject type \'Opportunitay\' is not supported.',
                'errorCode' => 'INVALID_TYPE',
            ],
        ];
    }
}
