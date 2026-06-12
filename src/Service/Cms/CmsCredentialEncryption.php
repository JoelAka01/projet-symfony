<?php

declare(strict_types=1);

namespace App\Service\Cms;

use App\Exception\CmsIntegrationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CmsCredentialEncryption
{
    public function __construct(
        #[Autowire('%env(string:CMS_CREDENTIAL_ENCRYPTION_KEY)%')]
        private readonly string $encryptionKey,
        #[Autowire('%kernel.secret%')]
        private readonly string $applicationSecret = '',
    ) {}

    public function encrypt(string $plainText): string
    {
        $key = $this->key();
        $iv = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt(
            $plainText,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if (false === $cipherText) {
            throw new CmsIntegrationException('CMS credentials could not be encrypted.');
        }

        return 'v1.' . base64_encode($iv . $tag . $cipherText);
    }

    public function decrypt(?string $encrypted): string
    {
        if (null === $encrypted || '' === $encrypted) {
            throw new CmsIntegrationException('The CMS credential is not configured.');
        }

        if (!str_starts_with($encrypted, 'v1.')) {
            throw new CmsIntegrationException('The stored CMS credential format is unsupported.');
        }

        $payload = base64_decode(substr($encrypted, 3), true);
        if (false === $payload || strlen($payload) < 29) {
            throw new CmsIntegrationException('The stored CMS credential is invalid.');
        }

        $iv = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $cipherText = substr($payload, 28);
        $plainText = openssl_decrypt(
            $cipherText,
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if (false === $plainText) {
            throw new CmsIntegrationException('The CMS credential could not be decrypted because the server encryption secret changed.');
        }

        return $plainText;
    }

    private function key(): string
    {
        $key = trim($this->encryptionKey);
        if (strlen($key) < 32) {
            $key = trim($this->applicationSecret);
        }

        if (strlen($key) < 16) {
            throw new CmsIntegrationException('CMS credential encryption is not configured on the server. Contact the application administrator.');
        }

        return hash('sha256', $key, true);
    }
}
