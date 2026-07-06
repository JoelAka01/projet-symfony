<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Immutable result of an automatic language detection on a website URL.
 */
final readonly class LanguageDetectionResult
{
    public function __construct(
        public ?string $language,
        public ?string $country,
        public int $confidence,
        public string $detectionMethod,
    ) {}

    public function isConfident(): bool
    {
        return $this->confidence >= 60 && null !== $this->language;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'language' => $this->language,
            'country' => $this->country,
            'confidence' => $this->confidence,
            'detection_method' => $this->detectionMethod,
        ];
    }
}
