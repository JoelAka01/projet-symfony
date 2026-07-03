<?php

declare(strict_types=1);

namespace App\Service\Cost;

use App\Dto\SerpResultDto;
use App\Entity\Project;
use App\Entity\SerpCache;
use App\Enum\PipelineQualityMode;
use App\Repository\SerpCacheRepository;
use App\Service\Serp\SerpProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

final class CachedSerpService
{
    private const PROVIDER_SEARCH = 'zenserp.search';
    private const PROVIDER_SUGGEST = 'zenserp.suggest';

    public function __construct(
        private readonly SerpProviderInterface $serpProvider,
        private readonly SerpCacheRepository $serpCacheRepository,
        private readonly ApiCostGuard $apiCostGuard,
        private readonly ApiUsageLogger $usageLogger,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function search(Project $project, string $keyword, string $country, string $language, PipelineQualityMode $mode): SerpResultDto
    {
        $cached = $this->reusableCache($keyword, $country, $language, self::PROVIDER_SEARCH, $mode);
        if (null !== $cached) {
            $this->usageLogger->log($project, 'zenserp', 'serp_search', cacheHit: true, savedCostEstimate: 0.01);

            return $this->serpResultFromArray($cached->getRawData());
        }

        if (!$this->apiCostGuard->shouldCallExternalApi($project, 'serp_search', [
            'mode' => $mode,
            'operation_type' => 'serp',
            'estimated_cost' => 0.01,
        ])) {
            throw new \RuntimeException('SERP search blocked by cost guard and no reusable cache was available.');
        }

        $result = $this->serpProvider->search($keyword, $country, $language);
        $this->saveCache($keyword, $country, $language, self::PROVIDER_SEARCH, $result->toArray());
        $this->usageLogger->log($project, 'zenserp', 'serp_search', estimatedCost: 0.01);

        return $result;
    }

    /** @return list<string> */
    public function suggest(Project $project, string $keyword, string $country, string $language, PipelineQualityMode $mode): array
    {
        $cached = $this->reusableCache($keyword, $country, $language, self::PROVIDER_SUGGEST, $mode);
        if (null !== $cached) {
            $this->usageLogger->log($project, 'zenserp', 'serp_suggest', cacheHit: true, savedCostEstimate: 0.003);

            return $this->suggestionsFromArray($cached->getRawData());
        }

        if (!$this->apiCostGuard->shouldCallExternalApi($project, 'serp_suggest', [
            'mode' => $mode,
            'operation_type' => 'serp',
            'estimated_cost' => 0.003,
        ])) {
            return [];
        }

        $suggestions = $this->serpProvider->suggest($keyword, $country, $language);
        $this->saveCache($keyword, $country, $language, self::PROVIDER_SUGGEST, ['suggestions' => $suggestions]);
        $this->usageLogger->log($project, 'zenserp', 'serp_suggest', estimatedCost: 0.003);

        return $suggestions;
    }

    private function reusableCache(string $keyword, string $country, string $language, string $provider, PipelineQualityMode $mode): ?SerpCache
    {
        $fresh = $this->serpCacheRepository->findFresh($keyword, $country, $language, $provider);
        if (null !== $fresh) {
            return $fresh;
        }

        if (PipelineQualityMode::QUALITY !== $mode) {
            return $this->serpCacheRepository->findAny($keyword, $country, $language, $provider);
        }

        return null;
    }

    /** @param array<string, mixed> $rawData */
    private function saveCache(string $keyword, string $country, string $language, string $provider, array $rawData): void
    {
        $cache = $this->serpCacheRepository->findAny($keyword, $country, $language, $provider) ?? new SerpCache();
        $cache
            ->setKeyword($keyword)
            ->setCountry($country)
            ->setLanguage($language)
            ->setProvider($provider)
            ->setRawData($rawData);

        $this->entityManager->persist($cache);
    }

    /** @param array<string, mixed> $data */
    private function serpResultFromArray(array $data): SerpResultDto
    {
        return new SerpResultDto(
            $this->listOfObjects($data['organic_results'] ?? []),
            $this->listOfObjects($data['people_also_ask'] ?? []),
            $this->listOfStrings($data['related_searches'] ?? []),
            $this->object($data['features'] ?? []),
            $this->object($data['raw_response'] ?? $data),
        );
    }

    /** @param array<string, mixed> $data */
    private function suggestionsFromArray(array $data): array
    {
        return $this->listOfStrings($data['suggestions'] ?? []);
    }

    /** @return list<array<string, mixed>> */
    private function listOfObjects(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /** @return list<string> */
    private function listOfStrings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_scalar($item) && '' !== trim((string) $item)) {
                $items[] = trim((string) $item);
            }
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function object(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
