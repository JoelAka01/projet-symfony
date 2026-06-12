<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Service\Billing\ClientIpHasher;
use PHPUnit\Framework\TestCase;

final class ClientIpHasherTest extends TestCase
{
    public function testItCreatesStableNonReversibleIpIdentifiers(): void
    {
        $hasher = new ClientIpHasher('test-secret');

        $first = $hasher->hash('203.0.113.10');
        $same = $hasher->hash('203.0.113.10');
        $other = $hasher->hash('203.0.113.11');

        self::assertNotNull($first);
        self::assertSame($first, $same);
        self::assertNotSame('203.0.113.10', $first);
        self::assertNotSame($first, $other);
        self::assertNull($hasher->hash(null));
    }
}
