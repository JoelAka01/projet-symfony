<?php

declare(strict_types=1);

namespace App\Service\Serp;

use App\Dto\SerpResultDto;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ZenserpProvider implements SerpProviderInterface
{
    private const SEARCH_ENDPOINT = 'https://app.zenserp.com/api/v2/search';
    private const SUGGEST_ENDPOINT = 'https://app.zenserp.com/api/v2/suggest';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $zenserpApiKey = null,
    ) {}

    public function search(string $query, string $country, string $language): SerpResultDto
    {
        $data = $this->request(self::SEARCH_ENDPOINT, [
            'q' => $query,
            'gl' => strtolower($country),
            'hl' => strtolower($language),
            'num' => 10,
        ]);

        $organicResults = $this->organicResults($data);
        $peopleAlsoAsk = $this->peopleAlsoAsk($data);
        $relatedSearches = $this->relatedSearches($data);
        $features = [
            'featured_snippets' => $this->listFromKeys($data, ['featured_snippets', 'answer_box']),
            'paa' => $peopleAlsoAsk,
            'related_searches' => $relatedSearches,
            'images' => $this->listFromKeys($data, ['images', 'image_results']),
            'videos' => $this->listFromKeys($data, ['videos', 'video_results']),
        ];

        return new SerpResultDto($organicResults, $peopleAlsoAsk, $relatedSearches, $features, $data);
    }

    public function suggest(string $query, string $country, string $language): array
    {
        $data = $this->request(self::SUGGEST_ENDPOINT, [
            'q' => $query,
            'gl' => strtolower($country),
            'hl' => strtolower($language),
        ]);

        $suggestions = $data['suggestions'] ?? $data['suggest'] ?? $data['results'] ?? [];
        if (!is_array($suggestions)) {
            return [];
        }

        $normalized = [];
        foreach ($suggestions as $suggestion) {
            if (is_string($suggestion) && '' !== trim($suggestion)) {
                $normalized[] = trim($suggestion);
                continue;
            }

            if (is_array($suggestion)) {
                $value = $suggestion['value'] ?? $suggestion['term'] ?? $suggestion['query'] ?? $suggestion['text'] ?? null;
                if (is_scalar($value) && '' !== trim((string) $value)) {
                    $normalized[] = trim((string) $value);
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<string, string|int> $query
     *
     * @return array<string, mixed>
     */
    private function request(string $endpoint, array $query): array
    {
        $apiKey = $this->apiKey();
        $lastException = null;

        for ($attempt = 1; $attempt <= 2; ++$attempt) {
            try {
                $response = $this->httpClient->request('GET', $endpoint, [
                    'query' => ['apikey' => $apiKey] + $query,
                    'timeout' => 30,
                    'max_duration' => 45,
                ]);
                $statusCode = $response->getStatusCode();
                $body = $response->getContent(false);
                if ($statusCode >= 400) {
                    $exception = new \RuntimeException(sprintf('Zenserp returned HTTP %d: %s', $statusCode, mb_substr(strip_tags($body), 0, 500)));
                    // Do not retry on client errors (4xx) — they won't resolve on retry
                    if ($statusCode < 500) {
                        throw $exception;
                    }
                    throw $exception;
                }

                $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    throw new \UnexpectedValueException('Zenserp response was not a JSON object.');
                }

                return $decoded;
            } catch (\Throwable $exception) {
                if ($attempt < 2 && !str_contains($exception->getMessage(), 'HTTP 4')) {
                    $lastException = $exception;
                    usleep(250000);
                    continue;
                }

                throw $exception;
            }
        }

        throw $lastException;
    }

    private function apiKey(): string
    {
        $apiKey = $this->zenserpApiKey ?? '';
        if ('' === trim($apiKey)) {
            throw new \RuntimeException('ZENSERP_API_KEY is not configured.');
        }

        return trim($apiKey);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private function organicResults(array $data): array
    {
        $items = $this->listFromKeys($data, ['organic', 'organic_results']);
        $results = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $url = $item['url'] ?? $item['link'] ?? null;
            $title = $item['title'] ?? null;
            if (!is_scalar($url) || !is_scalar($title)) {
                continue;
            }

            $results[] = [
                'position' => is_numeric($item['position'] ?? null) ? (int) $item['position'] : $index + 1,
                'url' => (string) $url,
                'title' => (string) $title,
                'snippet' => is_scalar($item['description'] ?? $item['snippet'] ?? null) ? (string) ($item['description'] ?? $item['snippet']) : null,
            ];
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private function peopleAlsoAsk(array $data): array
    {
        $items = $this->listFromKeys($data, ['people_also_ask', 'questions', 'paa']);
        $questions = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $questions[] = ['question' => $item, 'source' => 'paa'];
                continue;
            }

            if (is_array($item)) {
                $question = $item['question'] ?? $item['title'] ?? $item['text'] ?? null;
                if (is_scalar($question) && '' !== trim((string) $question)) {
                    $questions[] = [
                        'question' => trim((string) $question),
                        'answer' => is_scalar($item['answer'] ?? $item['snippet'] ?? null) ? (string) ($item['answer'] ?? $item['snippet']) : null,
                        'source' => 'paa',
                    ];
                }
            }
        }

        return $questions;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function relatedSearches(array $data): array
    {
        $items = $this->listFromKeys($data, ['related_searches', 'related', 'related_queries']);
        $searches = [];
        foreach ($items as $item) {
            if (is_string($item) && '' !== trim($item)) {
                $searches[] = trim($item);
                continue;
            }

            if (is_array($item)) {
                $value = $item['query'] ?? $item['title'] ?? $item['text'] ?? null;
                if (is_scalar($value) && '' !== trim((string) $value)) {
                    $searches[] = trim((string) $value);
                }
            }
        }

        return array_values(array_unique($searches));
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $keys
     *
     * @return list<mixed>
     */
    private function listFromKeys(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            if (is_array($value)) {
                if (array_is_list($value)) {
                    return $value;
                }

                return [$value];
            }
        }

        return [];
    }
}
