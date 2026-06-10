<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\KeywordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['api:read', 'keyword:read']],
    denormalizationContext: ['groups' => ['keyword:write']],
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
    ],
)]
#[ORM\Entity(repositoryClass: KeywordRepository::class)]
#[ORM\Table(name: 'keywords')]
#[ORM\Index(name: 'idx_keywords_project_term', columns: ['project_id', 'term'])]
#[ORM\Index(name: 'idx_keywords_cluster', columns: ['keyword_cluster_id'])]
class Keyword
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'keywords')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['keyword:read', 'keyword:write'])]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: 'keywords')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['keyword:read', 'keyword:write'])]
    private ?KeywordCluster $keywordCluster = null;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    #[Groups(['keyword:read', 'keyword:write'])]
    private string $term = '';

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['keyword:read', 'keyword:write'])]
    private ?int $searchVolume = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['keyword:read', 'keyword:write'])]
    private ?int $difficulty = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['keyword:read', 'keyword:write'])]
    private ?string $cpc = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    #[Groups(['keyword:read', 'keyword:write'])]
    private ?string $intent = null;

    #[ORM\Column]
    #[Groups(['keyword:read', 'keyword:write'])]
    private bool $isFanoutKeyword = false;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    #[Groups(['keyword:read', 'keyword:write'])]
    private ?string $source = null;

    /** @var Collection<int, KeywordRanking> */
    #[ORM\OneToMany(mappedBy: 'keyword', targetEntity: KeywordRanking::class, orphanRemoval: true)]
    private Collection $rankings;

    /** @var Collection<int, Article> */
    #[ORM\OneToMany(mappedBy: 'primaryKeyword', targetEntity: Article::class)]
    private Collection $primaryArticles;

    /** @var Collection<int, Article> */
    #[ORM\ManyToMany(targetEntity: Article::class, mappedBy: 'targetKeywords')]
    private Collection $articles;

    /** @var Collection<int, GeoPrompt> */
    #[ORM\OneToMany(mappedBy: 'keyword', targetEntity: GeoPrompt::class)]
    private Collection $geoPrompts;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->rankings = new ArrayCollection();
        $this->primaryArticles = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->geoPrompts = new ArrayCollection();
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
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

    public function getTerm(): string
    {
        return $this->term;
    }

    public function setTerm(string $term): self
    {
        $this->term = $term;

        return $this;
    }

    public function getIntent(): ?string
    {
        return $this->intent;
    }

    public function setIntent(?string $intent): self
    {
        $this->intent = $intent;

        return $this;
    }

    public function getSearchVolume(): ?int
    {
        return $this->searchVolume;
    }

    public function setSearchVolume(?int $searchVolume): static
    {
        $this->searchVolume = $searchVolume;

        return $this;
    }

    public function getDifficulty(): ?int
    {
        return $this->difficulty;
    }

    public function setDifficulty(?int $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function getCpc(): ?string
    {
        return $this->cpc;
    }

    public function setCpc(?string $cpc): static
    {
        $this->cpc = $cpc;

        return $this;
    }

    public function isFanoutKeyword(): ?bool
    {
        return $this->isFanoutKeyword;
    }

    public function setIsFanoutKeyword(bool $isFanoutKeyword): static
    {
        $this->isFanoutKeyword = $isFanoutKeyword;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    /**
     * @return Collection<int, KeywordRanking>
     */
    public function getRankings(): Collection
    {
        return $this->rankings;
    }

    public function addRanking(KeywordRanking $ranking): static
    {
        if (!$this->rankings->contains($ranking)) {
            $this->rankings->add($ranking);
            $ranking->setKeyword($this);
        }

        return $this;
    }

    public function removeRanking(KeywordRanking $ranking): static
    {
        if ($this->rankings->removeElement($ranking)) {
            // set the owning side to null (unless already changed)
            if ($ranking->getKeyword() === $this) {
                $ranking->setKeyword(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getPrimaryArticles(): Collection
    {
        return $this->primaryArticles;
    }

    public function addPrimaryArticle(Article $primaryArticle): static
    {
        if (!$this->primaryArticles->contains($primaryArticle)) {
            $this->primaryArticles->add($primaryArticle);
            $primaryArticle->setPrimaryKeyword($this);
        }

        return $this;
    }

    public function removePrimaryArticle(Article $primaryArticle): static
    {
        if ($this->primaryArticles->removeElement($primaryArticle)) {
            // set the owning side to null (unless already changed)
            if ($primaryArticle->getPrimaryKeyword() === $this) {
                $primaryArticle->setPrimaryKeyword(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->addTargetKeyword($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): static
    {
        if ($this->articles->removeElement($article)) {
            $article->removeTargetKeyword($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, GeoPrompt>
     */
    public function getGeoPrompts(): Collection
    {
        return $this->geoPrompts;
    }

    public function addGeoPrompt(GeoPrompt $geoPrompt): static
    {
        if (!$this->geoPrompts->contains($geoPrompt)) {
            $this->geoPrompts->add($geoPrompt);
            $geoPrompt->setKeyword($this);
        }

        return $this;
    }

    public function removeGeoPrompt(GeoPrompt $geoPrompt): static
    {
        if ($this->geoPrompts->removeElement($geoPrompt)) {
            // set the owning side to null (unless already changed)
            if ($geoPrompt->getKeyword() === $this) {
                $geoPrompt->setKeyword(null);
            }
        }

        return $this;
    }
}
