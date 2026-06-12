<?php

declare(strict_types=1);

namespace App\Service\Billing;

final class ClientIpHasher
{
    public function __construct(private readonly string $appSecret) {}

    public function hash(?string $ipAddress): ?string
    {
        $ipAddress = trim((string) $ipAddress);
        if ('' === $ipAddress) {
            return null;
        }

        return hash_hmac('sha256', $ipAddress, $this->appSecret);
    }
}
