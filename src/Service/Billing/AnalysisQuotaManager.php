<?php

declare(strict_types=1);

namespace App\Service\Billing;

use App\Entity\AnalysisQuotaUsage;
use App\Entity\Audit;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\AnalysisQuotaStatus;
use App\Exception\AnalysisLimitExceededException;
use App\Repository\AiUsageRepository;
use App\Repository\AnalysisQuotaUsageRepository;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AnalysisQuotaManager
{
    public const FREE_ANALYSIS_LIMIT = 3;

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly AnalysisQuotaUsageRepository $quotaUsageRepository,
        private readonly AiUsageRepository $aiUsageRepository,
        private readonly ClientIpHasher $clientIpHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * @return array{
     *     planName: string,
     *     paid: bool,
     *     allowed: bool,
     *     remainingAnalyses: int,
     *     weeklyLimit: int|null,
     *     usedCredits: int,
     *     creditLimit: int|null,
     *     message: string
     * }
     */
    public function getAllowance(User $user, ?string $clientIp = null): array
    {
        $now = new \DateTimeImmutable();
        $subscription = $this->subscriptionRepository->findActiveForUser($user, $now);

        if (!$subscription instanceof Subscription) {
            $ipHash = $this->clientIpHasher->hash($clientIp);
            $usedByUser = $this->quotaUsageRepository->countFreeForUser($user);
            $usedByIp = null === $ipHash ? 0 : $this->quotaUsageRepository->countFreeForIp($ipHash);
            $used = max($usedByUser, $usedByIp);
            $remaining = max(0, self::FREE_ANALYSIS_LIMIT - $used);

            return [
                'planName' => 'Free',
                'paid' => false,
                'allowed' => $remaining > 0,
                'remainingAnalyses' => $remaining,
                'weeklyLimit' => null,
                'usedCredits' => 0,
                'creditLimit' => null,
                'message' => $remaining > 0
                    ? sprintf('%d free analyses remaining.', $remaining)
                    : 'Your 3 free analyses have been used. Select a plan to continue.',
            ];
        }

        $weekStartedAt = new \DateTimeImmutable('monday this week 00:00:00');
        if ($weekStartedAt < $subscription->getStartsAt()) {
            $weekStartedAt = $subscription->getStartsAt();
        }

        $weeklyUsed = $this->quotaUsageRepository->countForUserSince($user, $weekStartedAt);
        $remainingAnalyses = max(0, $subscription->getWeeklyAnalysisLimit() - $weeklyUsed);
        $actualCredits = $this->aiUsageRepository->sumCreditsForUserBetween(
            $user,
            $subscription->getStartsAt(),
            $subscription->getEndsAt(),
        );
        $reservedCredits = $this->quotaUsageRepository->sumReservedCreditsForUserBetween(
            $user,
            $subscription->getStartsAt(),
            $subscription->getEndsAt(),
        );
        $usedCredits = $actualCredits + $reservedCredits;
        $hasCredits = $usedCredits + PlanCatalog::ESTIMATED_CREDITS_PER_ANALYSIS <= $subscription->getMonthlyCreditLimit();
        $allowed = $remainingAnalyses > 0 && $hasCredits;

        return [
            'planName' => $subscription->getPlan()->label(),
            'paid' => true,
            'allowed' => $allowed,
            'remainingAnalyses' => $remainingAnalyses,
            'weeklyLimit' => $subscription->getWeeklyAnalysisLimit(),
            'usedCredits' => $usedCredits,
            'creditLimit' => $subscription->getMonthlyCreditLimit(),
            'message' => $allowed
                ? sprintf('%d analyses remaining this week.', $remainingAnalyses)
                : ($remainingAnalyses < 1
                    ? 'Your weekly analysis limit has been reached.'
                    : 'Your monthly AI credit allowance has been reached.'),
        ];
    }

    public function reserve(Audit $audit, User $user, ?string $clientIp = null): AnalysisQuotaUsage
    {
        $allowance = $this->getAllowance($user, $clientIp);
        if (!$allowance['allowed']) {
            throw new AnalysisLimitExceededException($allowance['message']);
        }

        $subscription = $this->subscriptionRepository->findActiveForUser($user);
        $usage = new AnalysisQuotaUsage();
        $usage
            ->setUser($user)
            ->setProject($audit->getProject())
            ->setAudit($audit)
            ->setSubscription($subscription)
            ->setPlanCode($subscription?->getPlan()->value ?? 'FREE')
            ->setIpHash($this->clientIpHasher->hash($clientIp))
            ->setCreditsCharged(PlanCatalog::ESTIMATED_CREDITS_PER_ANALYSIS)
            ->setStatus(AnalysisQuotaStatus::RESERVED);

        $this->entityManager->persist($usage);

        return $usage;
    }

    public function consume(Audit $audit, int $actualCredits): void
    {
        $usage = $this->quotaUsageRepository->findLatestReservedForAudit($audit);
        if (!$usage instanceof AnalysisQuotaUsage) {
            return;
        }

        $usage
            ->setCreditsCharged(max(1, $actualCredits))
            ->setStatus(AnalysisQuotaStatus::CONSUMED)
            ->setFinalizedAt(new \DateTimeImmutable());
    }

    public function release(Audit $audit): void
    {
        $usage = $this->quotaUsageRepository->findLatestReservedForAudit($audit);
        if (!$usage instanceof AnalysisQuotaUsage) {
            return;
        }

        $usage
            ->setCreditsCharged(0)
            ->setStatus(AnalysisQuotaStatus::RELEASED)
            ->setFinalizedAt(new \DateTimeImmutable());
    }
}
