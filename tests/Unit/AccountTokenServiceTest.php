<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\User;
use App\Security\AccountTokenService;
use PHPUnit\Framework\TestCase;

final class AccountTokenServiceTest extends TestCase
{
    public function testEmailVerificationTokenIsStoredHashedAndValidated(): void
    {
        $user = new User();
        $service = new AccountTokenService();

        $token = $service->issueEmailVerificationToken($user);

        self::assertNotSame($token, $user->getEmailVerificationTokenHash());
        self::assertTrue($service->isEmailVerificationTokenValid($user, $token));
        self::assertFalse($service->isEmailVerificationTokenValid($user, 'wrong-token'));
    }

    public function testExpiredPasswordResetTokenIsRejected(): void
    {
        $user = new User();
        $service = new AccountTokenService();

        $token = $service->issuePasswordResetToken($user);
        $user->setPasswordResetTokenExpiresAt(new \DateTimeImmutable('-1 minute'));

        self::assertFalse($service->isPasswordResetTokenValid($user, $token));
    }
}
