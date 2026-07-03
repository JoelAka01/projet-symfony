<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class SerpResultDto
{
    /**
     * @param list<array<string, mixed>> $organicResults
     * @param list<array<string, mixed>> $peopleAlsoAsk
     * @param list<string>               $relatedSearches
     * @param array<string, mixed>       $features
     * @param array<string, mixed>       $rawResponse
     */
    public function __construct(
        public array $organicResults,
        public array $peopleAlsoAsk,
        public array $relatedSearches,
        public array $features,
        public array $rawResponse,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'organic_results' => $this->organicResults,
            'people_also_ask' => $this->peopleAlsoAsk,
            'related_searches' => $this->relatedSearches,
            'features' => $this->features,
            'raw_response' => $this->rawResponse,
        ];
    }
}
