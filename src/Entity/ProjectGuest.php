<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProjectGuestAccess;
use App\Repository\ProjectGuestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectGuestRepository::class)]
#[ORM\Table(name: 'project_guests')]
class ProjectGuest
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'projectGuests')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'projectGuestMemberships')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(enumType: ProjectGuestAccess::class)]
    private ProjectGuestAccess $access = ProjectGuestAccess::CONTENT;

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getAccess(): ProjectGuestAccess
    {
        return $this->access;
    }

    public function setAccess(ProjectGuestAccess $access): self
    {
        $this->access = $access;

        return $this;
    }
}
