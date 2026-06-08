<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['api:read', 'article:read']],
    denormalizationContext: ['groups' => ['article:write']],
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
    ]
)]
#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'articles')]
#[ORM\Index(name: 'idx_articles_status', columns: ['status'])]
class Article extends ContentItem
{
    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['article:read', 'article:write'])]
    private ?KeywordCluster $keywordCluster = null;

    #[ORM\ManyToOne(inversedBy: 'primaryArticles')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['article:read', 'article:write'])]
    private ?Keyword $primaryKeyword = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    #[Groups(['article:read', 'article:write'])]
    private ?string $slug = null;

    #[ORM\Column(enumType: ArticleStatus::class)]
    #[Groups(['article:read', 'article:write'])]
    private ArticleStatus $status = ArticleStatus::DRAFT;

    #[ORM\Column(nullable: true)]
    #[Groups(['article:read', 'article:write'])]
    private ?int $wordCount = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['article:read'])]
    private ?int $seoScore = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['article:read'])]
    private ?int $geoScore = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['article:read', 'article:write'])]
    private ?string $contentMarkdown = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['article:read'])]
    private ?string $contentHtml = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $faqJson = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $internalLinksJson = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $externalSourcesJson = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?int $cannibalizationScore = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $generatedByProvider = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $generatedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    /** @var Collection<int, ArticleImage> */
    #[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleImage::class, orphanRemoval: true)]
    private Collection $images;

    /** @var Collection<int, CmsPublication> */
    #[ORM\OneToMany(mappedBy: 'article', targetEntity: CmsPublication::class, orphanRemoval: true)]
    private Collection $publications;

    /** @var Collection<int, Keyword> */
    #[ORM\ManyToMany(targetEntity: Keyword::class, inversedBy: 'articles')]
    #[ORM\JoinTable(name: 'article_keywords')]
    private Collection $targetKeywords;

    public function __construct()
    {
        parent::__construct();
        $this->images = new ArrayCollection();
        $this->publications = new ArrayCollection();
        $this->targetKeywords = new ArrayCollection();
    }

    public function getKeywordCluster(): ?KeywordCluster
    {
        return $this->keywordCluster;
    }

    public function setKeywordCluster(?KeywordCluster $keywordCluster): self
    {
        $this->keywordCluster = $keywordCluster;

        return $this;
    }

    public function getPrimaryKeyword(): ?Keyword
    {
        return $this->primaryKeyword;
    }

    public function setPrimaryKeyword(?Keyword $primaryKeyword): self
    {
        $this->primaryKeyword = $primaryKeyword;

        return $this;
    }

    public function getStatus(): ArticleStatus
    {
        return $this->status;
    }

    public function setStatus(ArticleStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    /** @return Collection<int, Keyword> */
    public function getTargetKeywords(): Collection
    {
        return $this->targetKeywords;
    }

    public function addTargetKeyword(Keyword $keyword): self
    {
        if (!$this->targetKeywords->contains($keyword)) {
            $this->targetKeywords->add($keyword);
        }

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getWordCount(): ?int
    {
        return $this->wordCount;
    }

    public function setWordCount(?int $wordCount): static
    {
        $this->wordCount = $wordCount;

        return $this;
    }

    public function getSeoScore(): ?int
    {
        return $this->seoScore;
    }

    public function setSeoScore(?int $seoScore): static
    {
        $this->seoScore = $seoScore;

        return $this;
    }

    public function getGeoScore(): ?int
    {
        return $this->geoScore;
    }

    public function setGeoScore(?int $geoScore): static
    {
        $this->geoScore = $geoScore;

        return $this;
    }

    public function getContentMarkdown(): ?string
    {
        return $this->contentMarkdown;
    }

    public function setContentMarkdown(?string $contentMarkdown): static
    {
        $this->contentMarkdown = $contentMarkdown;

        return $this;
    }

    public function getContentHtml(): ?string
    {
        return $this->contentHtml;
    }

    public function setContentHtml(?string $contentHtml): static
    {
        $this->contentHtml = $contentHtml;

        return $this;
    }

    public function getFaqJson(): ?array
    {
        return $this->faqJson;
    }

    public function setFaqJson(?array $faqJson): static
    {
        $this->faqJson = $faqJson;

        return $this;
    }

    public function getInternalLinksJson(): ?array
    {
        return $this->internalLinksJson;
    }

    public function setInternalLinksJson(?array $internalLinksJson): static
    {
        $this->internalLinksJson = $internalLinksJson;

        return $this;
    }

    public function getExternalSourcesJson(): ?array
    {
        return $this->externalSourcesJson;
    }

    public function setExternalSourcesJson(?array $externalSourcesJson): static
    {
        $this->externalSourcesJson = $externalSourcesJson;

        return $this;
    }

    public function getCannibalizationScore(): ?int
    {
        return $this->cannibalizationScore;
    }

    public function setCannibalizationScore(?int $cannibalizationScore): static
    {
        $this->cannibalizationScore = $cannibalizationScore;

        return $this;
    }

    public function getGeneratedByProvider(): ?string
    {
        return $this->generatedByProvider;
    }

    public function setGeneratedByProvider(?string $generatedByProvider): static
    {
        $this->generatedByProvider = $generatedByProvider;

        return $this;
    }

    public function getGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(?\DateTimeImmutable $generatedAt): static
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    /**
     * @return Collection<int, ArticleImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ArticleImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setArticle($this);
        }

        return $this;
    }

    public function removeImage(ArticleImage $image): static
    {
        if ($this->images->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getArticle() === $this) {
                $image->setArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CmsPublication>
     */
    public function getPublications(): Collection
    {
        return $this->publications;
    }

    public function addPublication(CmsPublication $publication): static
    {
        if (!$this->publications->contains($publication)) {
            $this->publications->add($publication);
            $publication->setArticle($this);
        }

        return $this;
    }

    public function removePublication(CmsPublication $publication): static
    {
        if ($this->publications->removeElement($publication)) {
            // set the owning side to null (unless already changed)
            if ($publication->getArticle() === $this) {
                $publication->setArticle(null);
            }
        }

        return $this;
    }

    public function removeTargetKeyword(Keyword $targetKeyword): static
    {
        $this->targetKeywords->removeElement($targetKeyword);

        return $this;
    }
}
