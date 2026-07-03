<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Enum\SitePageType;
use App\Repository\SitePageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SitePageRepository::class)]
#[ORM\Table(name: 'site_pages')]
#[ORM\UniqueConstraint(name: 'uniq_site_pages_project_url', columns: ['project_id', 'url'])]
#[ORM\Index(name: 'idx_site_pages_project_active', columns: ['project_id', 'is_active'])]
#[ORM\Index(name: 'idx_site_pages_type_priority', columns: ['page_type', 'business_priority'])]
class SitePage
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 1000)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 1000)]
    private string $url = '';

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private string $title = '';

    #[ORM\Column(enumType: SitePageType::class)]
    private SitePageType $pageType = SitePageType::OTHER;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $targetKeyword = null;

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 0, max: 100)]
    private int $businessPriority = 50;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $anchorSuggestions = [];

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;
        $this->touch();

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = mb_substr(trim($url), 0, 1000);
        $this->touch();

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = mb_substr(trim($title), 0, 500);
        $this->touch();

        return $this;
    }

    public function getPageType(): SitePageType
    {
        return $this->pageType;
    }

    public function setPageType(SitePageType $pageType): self
    {
        $this->pageType = $pageType;
        $this->touch();

        return $this;
    }

    public function getTargetKeyword(): ?string
    {
        return $this->targetKeyword;
    }

    public function setTargetKeyword(?string $targetKeyword): self
    {
        $this->targetKeyword = $this->optionalString($targetKeyword, 500);
        $this->touch();

        return $this;
    }

    public function getBusinessPriority(): int
    {
        return $this->businessPriority;
    }

    public function setBusinessPriority(int $businessPriority): self
    {
        $this->businessPriority = max(0, min(100, $businessPriority));
        $this->touch();

        return $this;
    }

    /** @return list<string> */
    public function getAnchorSuggestions(): array
    {
        return $this->anchorSuggestions;
    }

    /** @param list<string> $anchorSuggestions */
    public function setAnchorSuggestions(array $anchorSuggestions): self
    {
        $anchors = [];
        foreach ($anchorSuggestions as $anchor) {
            $value = $this->optionalString($anchor, 120);
            if (null !== $value) {
                $anchors[] = $value;
            }
        }

        $this->anchorSuggestions = array_values(array_unique($anchors));
        $this->touch();

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        $this->touch();

        return $this;
    }

    private function optionalString(?string $value, int $maxLength): ?string
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        return mb_substr(trim($value), 0, $maxLength);
    }
}
