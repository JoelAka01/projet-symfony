<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\AiCacheRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiCacheRepository::class)]
#[ORM\Table(name: 'ai_caches')]
#[ORM\UniqueConstraint(name: 'uniq_ai_caches_operation_input_model', columns: ['operation', 'input_hash', 'model'])]
#[ORM\Index(name: 'idx_ai_caches_operation', columns: ['operation'])]
class AiCache
{
    use UuidPrimaryKeyTrait;

    #[ORM\Column(length: 120)]
    private string $operation = '';

    #[ORM\Column(length: 64)]
    private string $inputHash = '';

    #[ORM\Column(length: 120)]
    private string $model = '';

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $responseJson = [];

    #[ORM\Column]
    private int $tokensSavedEstimate = 0;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): self
    {
        $this->operation = $operation;

        return $this;
    }

    public function getInputHash(): string
    {
        return $this->inputHash;
    }

    public function setInputHash(string $inputHash): self
    {
        $this->inputHash = $inputHash;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getResponseJson(): array
    {
        return $this->responseJson;
    }

    /** @param array<string, mixed> $responseJson */
    public function setResponseJson(array $responseJson): self
    {
        $this->responseJson = $responseJson;
        $this->createdAt = new \DateTimeImmutable();

        return $this;
    }

    public function getTokensSavedEstimate(): int
    {
        return $this->tokensSavedEstimate;
    }

    public function setTokensSavedEstimate(int $tokensSavedEstimate): self
    {
        $this->tokensSavedEstimate = max(0, $tokensSavedEstimate);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
