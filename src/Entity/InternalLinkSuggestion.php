<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Enum\InternalLinkSuggestionStatus;
use App\Repository\InternalLinkSuggestionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InternalLinkSuggestionRepository::class)]
#[ORM\Table(name: 'internal_link_suggestions')]
#[ORM\Index(name: 'idx_internal_link_suggestions_article', columns: ['source_article_id'])]
#[ORM\Index(name: 'idx_internal_link_suggestions_target', columns: ['target_page_id'])]
#[ORM\Index(name: 'idx_internal_link_suggestions_status', columns: ['status'])]
class InternalLinkSuggestion
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'source_article_id', nullable: false, onDelete: 'CASCADE')]
    private ?Article $sourceArticle = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SitePage $targetPage = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $anchor = '';

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    #[ORM\Column(enumType: InternalLinkSuggestionStatus::class)]
    private InternalLinkSuggestionStatus $status = InternalLinkSuggestionStatus::SUGGESTED;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
    }

    public function getSourceArticle(): ?Article
    {
        return $this->sourceArticle;
    }

    public function setSourceArticle(?Article $sourceArticle): self
    {
        $this->sourceArticle = $sourceArticle;
        $this->touch();

        return $this;
    }

    public function getTargetPage(): ?SitePage
    {
        return $this->targetPage;
    }

    public function setTargetPage(?SitePage $targetPage): self
    {
        $this->targetPage = $targetPage;
        $this->touch();

        return $this;
    }

    public function getAnchor(): string
    {
        return $this->anchor;
    }

    public function setAnchor(string $anchor): self
    {
        $this->anchor = mb_substr(trim($anchor), 0, 180);
        $this->touch();

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = null === $position ? null : max(0, $position);
        $this->touch();

        return $this;
    }

    public function getStatus(): InternalLinkSuggestionStatus
    {
        return $this->status;
    }

    public function setStatus(InternalLinkSuggestionStatus $status): self
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }
}
