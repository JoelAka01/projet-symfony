<?php

declare(strict_types=1);

namespace App\Service\Cms;

use App\Dto\Cms\CmsPublishResult;
use App\Entity\Article;
use App\Entity\CmsConnection;
use App\Entity\CmsPublication;
use App\Enum\ArticleStatus;
use App\Exception\CmsIntegrationException;
use App\Repository\CmsPublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class CmsPublishingService
{
    public function __construct(
        private readonly CmsClientRegistry $clientRegistry,
        private readonly CmsPublicationRepository $publicationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function publish(Article $article, CmsConnection $connection, bool $publish): CmsPublishResult
    {
        if ($article->getProject() !== $connection->getProject()) {
            throw new CmsIntegrationException('The article and CMS connection do not belong to the same project.');
        }

        if (!$connection->isActive()) {
            throw new CmsIntegrationException('Test and reactivate this CMS connection before publishing.');
        }

        if ('' === trim((string) $article->getContentHtml())) {
            throw new CmsIntegrationException('The article has no HTML content to publish.');
        }

        $publication = $this->publicationRepository->findOneForArticleAndConnection($article, $connection);
        if (!$publication instanceof CmsPublication) {
            $publication = new CmsPublication();
            $publication
                ->setArticle($article)
                ->setCmsConnection($connection);
            $this->entityManager->persist($publication);
        }

        try {
            $result = $this->clientRegistry
                ->for($connection->getProvider())
                ->publishArticle($connection, $article, $publication, $publish);

            $now = new \DateTimeImmutable();
            $publication
                ->setExternalPostId($result->externalId)
                ->setExternalUrl($result->externalUrl)
                ->setErrorMessage(null)
                ->setStatus($publish ? ArticleStatus::PUBLISHED : ArticleStatus::DRAFT)
                ->setPublishedAt($publish ? $now : null);

            if ($publish) {
                $article
                    ->setStatus(ArticleStatus::PUBLISHED)
                    ->setPublishedAt($now);
            } else {
                $article->setStatus(ArticleStatus::GENERATED);
            }

            $this->entityManager->flush();

            return $result;
        } catch (\Throwable $exception) {
            $publication
                ->setStatus(ArticleStatus::FAILED)
                ->setErrorMessage($this->limit($exception->getMessage(), 4000));
            $this->entityManager->flush();

            $this->logger->error('CMS article publication failed.', [
                'article_id' => $article->getId(),
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
}
