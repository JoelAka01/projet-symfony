<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Enum\SubscriptionPlan;
use App\Enum\SubscriptionStatus;
use App\Repository\SubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
#[ORM\Index(name: 'idx_subscriptions_user_status', columns: ['user_id', 'status'])]
#[ORM\Index(name: 'idx_subscriptions_ends_at', columns: ['ends_at'])]
class Subscription
{
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(enumType: SubscriptionPlan::class)]
    private SubscriptionPlan $plan = SubscriptionPlan::STARTER;

    #[ORM\Column(enumType: SubscriptionStatus::class)]
    private SubscriptionStatus $status = SubscriptionStatus::ACTIVE;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $monthlyPriceCents = 0;

    #[ORM\Column]
    #[Assert\Positive]
    private int $monthlyCreditLimit = 1;

    #[ORM\Column]
    #[Assert\Positive]
    private int $weeklyAnalysisLimit = 1;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->initializeUuid();
        $now = new \DateTimeImmutable();
        $this->startsAt = $now;
        $this->endsAt = $now->modify('+1 month');
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

    public function getPlan(): SubscriptionPlan
    {
        return $this->plan;
    }

    public function setPlan(SubscriptionPlan $plan): self
    {
        $this->plan = $plan;

        return $this;
    }

    public function getStatus(): SubscriptionStatus
    {
        return $this->status;
    }

    public function setStatus(SubscriptionStatus $status): self
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getMonthlyPriceCents(): int
    {
        return $this->monthlyPriceCents;
    }

    public function setMonthlyPriceCents(int $monthlyPriceCents): self
    {
        $this->monthlyPriceCents = max(0, $monthlyPriceCents);

        return $this;
    }

    public function getMonthlyCreditLimit(): int
    {
        return $this->monthlyCreditLimit;
    }

    public function setMonthlyCreditLimit(int $monthlyCreditLimit): self
    {
        $this->monthlyCreditLimit = max(1, $monthlyCreditLimit);

        return $this;
    }

    public function getWeeklyAnalysisLimit(): int
    {
        return $this->weeklyAnalysisLimit;
    }

    public function setWeeklyAnalysisLimit(int $weeklyAnalysisLimit): self
    {
        $this->weeklyAnalysisLimit = max(1, $weeklyAnalysisLimit);

        return $this;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): self
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isActiveAt(\DateTimeImmutable $at): bool
    {
        return SubscriptionStatus::ACTIVE === $this->status
            && $this->startsAt <= $at
            && $this->endsAt > $at;
    }
}
