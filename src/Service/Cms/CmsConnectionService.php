<?php

declare(strict_types=1);

namespace App\Service\Cms;

use App\Dto\Cms\CmsConnectionTestResult;
use App\Entity\CmsConnection;
use App\Enum\CmsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class CmsConnectionService
{
    public function __construct(
        private readonly CmsClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function test(CmsConnection $connection): CmsConnectionTestResult
    {
        try {
            $result = $this->clientRegistry->for($connection->getProvider())->testConnection($connection);
            $this->storeDiscoveredSettings($connection, $result);
            $connection
                ->setLastTestedAt(new \DateTimeImmutable())
                ->setLastError(null)
                ->setIsActive(true);
            $this->entityManager->flush();

            return $result;
        } catch (\Throwable $exception) {
            $connection
                ->setLastTestedAt(new \DateTimeImmutable())
                ->setLastError($this->limit($exception->getMessage(), 2000))
                ->setIsActive(false);
            $this->entityManager->flush();

            $this->logger->error('CMS connection test failed.', [
                'connection_id' => $connection->getId(),
                'provider' => $connection->getProvider()->value,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    private function limit(string $value, int $maxLength): string
    {
        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }

    private function storeDiscoveredSettings(
        CmsConnection $connection,
        CmsConnectionTestResult $result,
    ): void {
        if (CmsProvider::SHOPIFY !== $connection->getProvider()) {
            return;
        }

        $settings = $connection->getSettings() ?? [];
        foreach (['blog_id', 'blog_title', 'available_blogs', 'shop', 'shop_domain'] as $name) {
            if (array_key_exists($name, $result->details)) {
                $settings[$name] = $result->details[$name];
            }
        }

        $connection->setSettings($settings);
    }
}
