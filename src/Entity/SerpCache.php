<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\SerpCacheRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SerpCacheRepository::class)]
#[ORM\Table(name: 'serp_caches')]
#[ORM\UniqueConstraint(name: 'uniq_serp_caches_lookup', columns: ['keyword', 'country', 'language', 'provider'])]
#[ORM\Index(name: 'idx_serp_caches_expires', columns: ['expires_at'])]
class SerpCache
{
    use UuidPrimaryKeyTrait;

    #[ORM\Column(length: 500)]
    private string $keyword = '';

    #[ORM\Column(length: 10)]
    private string $country = 'FR';

    #[ORM\Column(length: 10)]
    private string $language = 'fr';

    #[ORM\Column(length: 80)]
    private string $provider = '';

    #[ORM\Column(length: 64)]
    private string $resultHash = '';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $rawData = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify('+30 days');
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function setKeyword(string $keyword): self
    {
        $this->keyword = mb_strtolower(trim($keyword));

        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = strtoupper(trim($country));

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = strtolower(trim($language));

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = trim($provider);

        return $this;
    }

    public function getResultHash(): string
    {
        return $this->resultHash;
    }

    public function setResultHash(string $resultHash): self
    {
        $this->resultHash = $resultHash;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /** @param array<string, mixed> $rawData */
    public function setRawData(array $rawData): self
    {
        $this->rawData = $rawData;
        $this->resultHash = hash('sha256', json_encode($rawData, JSON_THROW_ON_ERROR));
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify('+30 days');

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isFresh(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return $this->expiresAt > $now;
    }
}
