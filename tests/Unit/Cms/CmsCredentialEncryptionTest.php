<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cms;

use App\Exception\CmsIntegrationException;
use App\Service\Cms\CmsCredentialEncryption;
use PHPUnit\Framework\TestCase;

final class CmsCredentialEncryptionTest extends TestCase
{
    public function testItEncryptsAndDecryptsCredentialsWithoutStoringPlainText(): void
    {
        $encryption = new CmsCredentialEncryption(str_repeat('a', 64));

        $encrypted = $encryption->encrypt('real-secret-token');

        self::assertStringStartsWith('v1.', $encrypted);
        self::assertStringNotContainsString('real-secret-token', $encrypted);
        self::assertSame('real-secret-token', $encryption->decrypt($encrypted));
    }

    public function testItRejectsAnUnconfiguredEncryptionKey(): void
    {
        $encryption = new CmsCredentialEncryption('');

        $this->expectException(CmsIntegrationException::class);
        $this->expectExceptionMessage('not configured on the server');

        $encryption->encrypt('secret');
    }

    public function testItFallsBackToTheApplicationSecret(): void
    {
        $encryption = new CmsCredentialEncryption('', str_repeat('b', 32));

        $encrypted = $encryption->encrypt('cms-secret');

        self::assertSame('cms-secret', $encryption->decrypt($encrypted));
    }
}
