<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\KeywordClusterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: KeywordClusterRepository::class)]
#[ORM\Table(name: 'keyword_clusters')]
#[ORM\Index(name: 'idx_keyword_clusters_project', columns: ['project_id'])]
class KeywordCluster
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'keywordClusters')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name = '';

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $intent = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $mainTopic = null;

    /** @var Collection<int, Keyword> */
    #[ORM\OneToMany(mappedBy: 'keywordCluster', targetEntity: Keyword::class)]
    private Collection $keywords;

    /** @var Collection<int, Article> */
    #[ORM\OneToMany(mappedBy: 'keywordCluster', targetEntity: Article::class)]
    private Collection $articles;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->keywords = new ArrayCollection();
        $this->articles = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getIntent(): ?string
    {
        return $this->intent;
    }

    public function setIntent(?string $intent): static
    {
        $this->intent = $intent;

        return $this;
    }

    public function getMainTopic(): ?string
    {
        return $this->mainTopic;
    }

    public function setMainTopic(?string $mainTopic): static
    {
        $this->mainTopic = $mainTopic;

        return $this;
    }

    /**
     * @return Collection<int, Keyword>
     */
    public function getKeywords(): Collection
    {
        return $this->keywords;
    }

    public function addKeyword(Keyword $keyword): static
    {
        if (!$this->keywords->contains($keyword)) {
            $this->keywords->add($keyword);
            $keyword->setKeywordCluster($this);
        }

        return $this;
    }

    public function removeKeyword(Keyword $keyword): static
    {
        if ($this->keywords->removeElement($keyword)) {
            // set the owning side to null (unless already changed)
            if ($keyword->getKeywordCluster() === $this) {
                $keyword->setKeywordCluster(null);
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
            $article->setKeywordCluster($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): static
    {
        if ($this->articles->removeElement($article)) {
            // set the owning side to null (unless already changed)
            if ($article->getKeywordCluster() === $this) {
                $article->setKeywordCluster(null);
            }
        }

        return $this;
    }
}
