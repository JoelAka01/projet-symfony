<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;

final class AccountTokenService
{
    private const TOKEN_BYTES = 32;

    public function issueEmailVerificationToken(User $user): string
    {
        $token = $this->generateToken();

        $user
            ->setEmailVerificationTokenHash($this->hashToken($token))
            ->setEmailVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'))
            ->touch();

        return $token;
    }

    public function issuePasswordResetToken(User $user): string
    {
        $token = $this->generateToken();

        $user
            ->setPasswordResetTokenHash($this->hashToken($token))
            ->setPasswordResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'))
            ->touch();

        return $token;
    }

    public function isEmailVerificationTokenValid(User $user, string $token): bool
    {
        return $this->isTokenValid(
            $user->getEmailVerificationTokenHash(),
            $user->getEmailVerificationTokenExpiresAt(),
            $token,
        );
    }

    public function isPasswordResetTokenValid(User $user, string $token): bool
    {
        return $this->isTokenValid(
            $user->getPasswordResetTokenHash(),
            $user->getPasswordResetTokenExpiresAt(),
            $token,
        );
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');
    }

    private function isTokenValid(?string $expectedHash, ?\DateTimeImmutable $expiresAt, string $token): bool
    {
        if (null === $expectedHash || null === $expiresAt) {
            return false;
        }

        if ($expiresAt < new \DateTimeImmutable()) {
            return false;
        }

        return hash_equals($expectedHash, $this->hashToken($token));
    }
}
