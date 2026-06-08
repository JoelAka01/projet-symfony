<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\ContentItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContentItemRepository::class)]
#[ORM\Table(name: 'content_items')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string', length: 20)]
#[ORM\DiscriminatorMap(['article' => Article::class, 'report' => Report::class])]
abstract class ContentItem
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'contentItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?Project $project = null;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    protected string $title = '';

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    protected ?\DateTimeImmutable $publishedAt = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }
}
